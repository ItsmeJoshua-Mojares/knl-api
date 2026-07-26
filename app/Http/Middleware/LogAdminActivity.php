<?php
// app/Http/Middleware/LogAdminActivity.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Cross-cutting concerns via middleware
//
// "Log every admin write action" is a CROSS-CUTTING CONCERN —
// it applies to many unrelated controllers (Products, Orders,
// Coupons, Categories...) equally. Without middleware, you'd
// have to remember to call ActivityLog::record() in every single
// controller method. Forget once, and that action goes unaudited.
//
// By putting it in middleware attached to the whole admin route
// group, EVERY write request (POST/PUT/PATCH/DELETE) under
// /api/admin/* gets logged automatically — no controller code
// needed, no chance of forgetting.
//
// We only log AFTER $next($request) runs, so we know the request
// actually succeeded (didn't throw a validation error) before
// recording it. We extract the subject info from the route's
// bound model when possible.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    // Only log these HTTP methods — GET requests aren't "activity"
    private const LOGGABLE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful writes (2xx status codes)
        if (
            in_array($request->method(), self::LOGGABLE_METHODS)
            && $response->getStatusCode() < 300
        ) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    private function logActivity(Request $request, Response $response): void
    {
        $event = match ($request->method()) {
            'POST'           => 'created',
            'PUT', 'PATCH'   => 'updated',
            'DELETE'         => 'deleted',
            default          => 'unknown',
        };

        // Try to figure out which resource this route touches
        // from the route name, e.g. "admin.products.store" → "Product"
        $routeName  = $request->route()?->getName() ?? '';
        $subjectType = $this->guessSubjectType($routeName);

        // Try to extract the subject ID from route parameters
        // or from the JSON response (newly created resources)
        $subjectId = $this->extractSubjectId($request, $response);

        ActivityLog::create([
            'user_id'      => $request->user()?->id,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId ?? 0,
            'event'        => $event,
            'properties'   => [
                'route'  => $routeName,
                'method' => $request->method(),
                'path'   => $request->path(),
            ],
            'ip_address'   => $request->ip(),
        ]);
    }

    private function guessSubjectType(string $routeName): string
    {
        // 'admin.products.store' → 'Product'
        $parts = explode('.', $routeName);
        $resource = $parts[1] ?? 'Unknown';
        return 'App\\Models\\' . ucfirst(rtrim($resource, 's'));
    }

    private function extractSubjectId(Request $request, Response $response): ?int
    {
        // Prefer route-bound model ID (e.g. PUT /products/{product})
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if (is_object($param) && isset($param->id)) {
                return (int) $param->id;
            }
            if (is_numeric($param)) {
                return (int) $param;
            }
        }

        // Fall back to the created resource's ID in the JSON response
        $content = json_decode($response->getContent(), true);
        return $content['data']['id'] ?? null;
    }
}
