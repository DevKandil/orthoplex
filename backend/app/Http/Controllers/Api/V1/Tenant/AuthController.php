<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Orthoplex API",
 *     version="1.0.0",
 *     description="A Laravel 12 multi-tenant SaaS API with comprehensive authentication, RBAC, and user management.",
 *     @OA\Contact(
 *         email="support@orthoplex.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\LoginEvent;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Create a new user account and return JWT token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors"
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Send email verification notification
        $user->notify(new VerifyEmailNotification);

        return response()->json([
            'message' => 'User registered successfully. Please check your email to verify your account.',
            'user' => $user,
            'email_verification_required' => true
        ], 201);
    }

    /**
     * Authenticate user and return JWT token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login_attempts:' . $request->ip();
        $maxAttempts = config('auth.rate_limits.login_attempts', 5);
        $decayMinutes = config('auth.rate_limits.login_decay_minutes', 15);

        // Check rate limiting
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            $this->logFailedAttempt($request, 'rate_limited');

            return response()->json([
                'error' => 'Too many login attempts',
                'message' => "Please try again in {$seconds} seconds"
            ], 429);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            RateLimiter::hit($key, $decayMinutes * 60);

            $this->logFailedAttempt($request, 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Clear rate limiting on successful login
        RateLimiter::clear($key);

        $user = auth()->user();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            JWTAuth::setToken($token)->invalidate();

            return response()->json([
                'error' => 'Email verification required',
                'message' => 'Please verify your email address before logging in.',
                'email_verification_required' => true
            ], 403);
        }

        // Check if 2FA is enabled
        if ($user->hasEnabledTwoFactorAuthentication()) {
            JWTAuth::setToken($token)->invalidate();

            // Generate a temporary challenge token
            $challengeToken = Str::random(60);
            cache()->put('2fa_challenge:' . $challengeToken, [
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], now()->addMinutes(10)); // 10 minute expiry

            return response()->json([
                'two_factor_required' => true,
                'message' => 'Two-factor authentication required',
                'challenge_token' => $challengeToken
            ], 200);
        }

        // Update login stats
        $user->update([
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ]);

        // Log successful login event
        $this->logSuccessfulLogin($request, $user);

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(JWTAuth::parseToken()->refresh());
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }

    /**
     * Log successful login event.
     */
    protected function logSuccessfulLogin(Request $request, User $user): void
    {
        LoginEvent::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_method' => 'password',
            'successful' => true,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Log failed login attempt.
     */
    protected function logFailedAttempt(Request $request, string $reason): void
    {
        $user = User::where('email', $request->email)->first();

        LoginEvent::create([
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_method' => 'password',
            'successful' => false,
            'failure_reason' => $reason,
            'attempted_at' => now(),
        ]);
    }
}