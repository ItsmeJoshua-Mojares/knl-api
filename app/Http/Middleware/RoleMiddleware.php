<?php
// app/Http/Middleware/RoleMiddleware.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Middleware
//
// Middleware sits BETWEEN the incoming HTTP request and your
// controller. Think of it as a security checkpoint.
//
// Request lifecycle:
//   HTTP Request
//     → Global middleware (CORS, auth parsing)
//     → Route middleware (auth:api checks JWT)
//     → THIS middleware (checks role)
//     → Controller method
//     → Response
//
// If any middleware returns early (with a 403/401), the
// controller is never reached. The chain is broken.
//
// $next($request) passes the request to the next middleware
// or controller. If you don't call it, nothing runs after yours.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin')
     *                  ->middleware('role:admin,super_admin')
     *
     * @param string $roles Comma-separated list of allowed roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // At this point, auth:api has already run, so the user is authenticated.
        $user = $request->user();

        // If no roles specified, allow any authenticated user
        if (empty($roles)) {
            return $next($request);
        }

        // Check if user's role name is in the allowed list
        $userRole = $user?->role?->name;

        if (!$userRole || !in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403); // 403 Forbidden — authenticated but not authorized
        }

        return $next($request);
    }
}
