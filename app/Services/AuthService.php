<?php
// app/Services/AuthService.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Service Layer
//
// Controllers should be THIN — they receive the request,
// call a service, and return a response. That's it.
//
// Services contain the BUSINESS LOGIC. This means:
// - Hashing passwords
// - Creating users
// - Issuing tokens
// - Sending emails
//
// Why separate services from controllers?
// 1. You can call the same logic from multiple places
//    (web controller, API controller, CLI command, queue job)
// 2. Services are easier to unit test — no HTTP involved
// 3. Controllers stay readable and short
//
// CONCEPT: Dependency Injection
// PHP type-hints in the constructor tell Laravel what to inject.
// When AuthService is instantiated anywhere, Laravel automatically
// passes in a JWTAuth instance. You never call `new JWTAuth()`.
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\AuthenticationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Register a new customer account.
     *
     * Returns the new user and their JWT token.
     *
     * @throws \Exception
     */
    public function register(array $data): array
    {
        // Create the user — password is auto-hashed by the model's $casts
        $user = User::create([
            'role_id'    => 3,              // default: customer
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'password'   => $data['password'], // $casts => ['password' => 'hashed']
            'phone'      => $data['phone'] ?? null,
        ]);

        // Issue a JWT token for the new user immediately
        // (they're logged in right after registering)
        $token = JWTAuth::fromUser($user);

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    /**
     * Log in with email + password.
     *
     * @throws AuthenticationException if credentials are wrong
     */
    public function login(string $email, string $password): array
    {
        // auth()->guard('api') uses our JWT guard (configured in config/auth.php)
        // attempt() checks the credentials AND issues a token if they match
        $token = auth()->guard('api')->attempt([
            'email'     => $email,
            'password'  => $password,
            'is_active' => true,           // also check account isn't disabled
        ]);

        if (!$token) {
            // Don't reveal which field was wrong — say "credentials" generically
            throw new AuthenticationException('Invalid email or password.');
        }

        // Update last_login_at timestamp
        $user = auth()->guard('api')->user();
        $user->update(['last_login_at' => now()]);

        return [
            'user'       => $user->load('role'), // eager-load role relationship
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, // seconds
        ];
    }

    /**
     * Log out — invalidate the current token.
     * After this, the token is blacklisted and can't be reused.
     */
    public function logout(): void
    {
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    /**
     * Refresh a token before it expires.
     * Returns a new token, old one is blacklisted.
     */
    public function refresh(): string
    {
        return JWTAuth::refresh(JWTAuth::getToken());
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(): User
    {
        return auth()->guard('api')->user()->load('role');
    }

    /**
     * Send a password reset email.
     * Phase 3: implement with Laravel's built-in Password facade.
     */
    public function sendPasswordResetLink(string $email): void
    {
        // TODO Phase 3: \Password::sendResetLink(['email' => $email]);
    }
}
