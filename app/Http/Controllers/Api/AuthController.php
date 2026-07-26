<?php
// app/Http/Controllers/Api/AuthController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: API Controllers
//
// This controller is THIN. Each method does three things only:
//   1. Validate the request (using Form Request classes)
//   2. Call the service
//   3. Return a JSON response
//
// CONCEPT: HTTP Status Codes
// These are standardized codes that tell the client what happened:
//   200 OK           — success, data returned
//   201 Created      — success, new resource created
//   204 No Content   — success, nothing to return (e.g. logout)
//   400 Bad Request  — client sent invalid data
//   401 Unauthorized — not logged in / bad token
//   403 Forbidden    — logged in but no permission
//   404 Not Found    — resource doesn't exist
//   422 Unprocessable— validation failed (Laravel's default for this)
//   500 Server Error — something crashed on our end
//
// CONCEPT: Response format
// We always return the same JSON shape:
//   { "success": true/false, "message": "...", "data": {...} }
// This makes it easy for the frontend to handle any response.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\AuthenticationException;

class AuthController extends Controller
{
    // Constructor injection — Laravel provides AuthService automatically
    public function __construct(private AuthService $authService) {}

    /**
     * POST /api/auth/register
     *
     * Register a new customer account.
     * Returns the user object and a JWT token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // $request->validated() returns ONLY the fields that passed validation
        // (defined in RegisterRequest). Never use $request->all() — it includes
        // any field the client decides to send, which is a security risk.
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data'    => [
                'user'  => $result['user'],
                'token' => $result['token'],
            ],
        ], 201); // 201 Created
    }

    /**
     * POST /api/auth/login
     *
     * Authenticate with email + password.
     * Returns a JWT token on success.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->email,
                $request->password
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'    => $result,
            ]);

        } catch (AuthenticationException $e) {
            // Return 401, not 422 — credentials wrong, not a validation error
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * POST /api/auth/logout
     *
     * Invalidate the current JWT token.
     * Requires: Authorization: Bearer <token>
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * POST /api/auth/refresh
     *
     * Get a new token (old one is blacklisted).
     * Call this before the token expires to keep the user logged in.
     */
    public function refresh(): JsonResponse
    {
        $newToken = $this->authService->refresh();

        return response()->json([
            'success' => true,
            'data'    => [
                'token'      => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ],
        ]);
    }

    /**
     * GET /api/auth/me
     *
     * Return the currently authenticated user's profile.
     * Requires a valid JWT token.
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->me();

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user],
        ]);
    }
}
