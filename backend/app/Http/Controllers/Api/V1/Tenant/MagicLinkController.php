<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class MagicLinkController extends Controller
{

    /**
     * Send magic link to user's email.
     *
     * @OA\Post(
     *     path="/api/v1/auth/magic-link",
     *     tags={"Authentication"},
     *     summary="Send magic login link",
     *     description="Send a magic login link to the user's email address",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Magic link sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Magic link sent to your email address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests"
     *     )
     * )
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $key = 'magic_link:' . $request->email;
        $maxAttempts = config('auth.rate_limits.magic_link_attempts', 3);
        $decayMinutes = config('auth.rate_limits.magic_link_decay_minutes', 60);

        // Check rate limiting
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'Too many magic link requests',
                'message' => "Please try again in " . ceil($seconds / 60) . " minutes"
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'Email verification required',
                'message' => 'Please verify your email address before requesting a magic link.',
                'email_verification_required' => true
            ], 403);
        }

        // Generate magic token
        $token = Str::random(60);
        $expiresAt = now()->addMinutes(config('auth.rate_limits.magic_link_expire_minutes', 15));

        // Store magic link token in cache
        cache()->put('magic_link:' . $token, [
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => $expiresAt
        ], $expiresAt);

        // Send magic link notification
        $user->notify(new MagicLinkNotification($token));

        // Increment rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        return response()->json([
            'message' => 'Magic link sent to your email address'
        ]);
    }

    /**
     * Verify magic link and authenticate user.
     *
     * @OA\Get(
     *     path="/api/v1/auth/magic-link/verify/{token}",
     *     tags={"Authentication"},
     *     summary="Verify magic login link",
     *     description="Verify magic login link and authenticate user",
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Authentication successful"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired magic link"
     *     )
     * )
     */
    public function verify(Request $request, string $token): JsonResponse
    {
        // Verify magic link token
        $magicLinkData = cache()->get('magic_link:' . $token);
        if (!$magicLinkData) {
            return response()->json([
                'error' => 'Invalid or expired magic link',
                'message' => 'The magic link is invalid or has expired. Please request a new one.'
            ], 401);
        }

        $user = User::where('email', $magicLinkData['email'])->first();
        if (!$user) {
            cache()->forget('magic_link:' . $token);
            return response()->json([
                'error' => 'User not found',
                'message' => 'The user associated with this magic link was not found.'
            ], 401);
        }

        // Check if 2FA is enabled
        if ($user->hasEnabledTwoFactorAuthentication()) {
            // For magic links with 2FA, we still require 2FA verification
            $challengeToken = Str::random(60);
            cache()->put('2fa_challenge:' . $challengeToken, [
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'magic_link_token' => $token // Keep the magic link token for cleanup
            ], now()->addMinutes(10));

            return response()->json([
                'two_factor_required' => true,
                'message' => 'Two-factor authentication required',
                'challenge_token' => $challengeToken
            ], 200);
        }

        // Clear the magic link token
        cache()->forget('magic_link:' . $token);

        // Generate JWT token
        $jwtToken = auth('api')->login($user);

        // Update login stats
        $user->update([
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ]);

        // Log successful login event
        $this->logSuccessfulLogin($request, $user, 'magic_link');

        return response()->json([
            'message' => 'Authentication successful',
            'access_token' => $jwtToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }

    /**
     * Log successful login event.
     */
    protected function logSuccessfulLogin(Request $request, User $user, string $method = 'magic_link'): void
    {
        LoginEvent::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_method' => $method,
            'successful' => true,
            'attempted_at' => now(),
        ]);
    }
}
