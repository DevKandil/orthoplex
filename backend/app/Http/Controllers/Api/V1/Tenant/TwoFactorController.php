<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tymon\JWTAuth\Facades\JWTAuth;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * Enable 2FA for the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/2fa/enable",
     *     tags={"Two-Factor Authentication"},
     *     summary="Enable 2FA",
     *     description="Enable two-factor authentication and generate QR code",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="2FA enabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication enabled successfully"),
     *             @OA\Property(property="qr_code_url", type="string", example="data:image/png;base64,..."),
     *             @OA\Property(property="secret", type="string", example="ABCDEFGHIJKLMNOP"),
     *             @OA\Property(property="recovery_codes", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="2FA already enabled"
     *     )
     * )
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled'
            ], 422);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();

        // Generate QR Code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Save to user
        $user->update([
            'google2fa_secret' => encrypt($secret),
            'google2fa_enabled' => false, // Will be enabled after verification
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes->toArray())),
        ]);

        return response()->json([
            'message' => 'Two-factor authentication secret generated. Please verify to enable.',
            'qr_code_url' => $qrCodeUrl,
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Verify and confirm 2FA setup.
     *
     * @OA\Post(
     *     path="/api/v1/2fa/confirm",
     *     tags={"Two-Factor Authentication"},
     *     summary="Confirm 2FA setup",
     *     description="Verify TOTP code and enable 2FA",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA confirmed and enabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication enabled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid verification code"
     *     )
     * )
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled'
            ], 422);
        }

        $secret = decrypt($user->google2fa_secret);

        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        // Enable 2FA
        $user->update([
            'google2fa_enabled' => true,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully'
        ]);
    }

    /**
     * Disable 2FA for the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/2fa/disable",
     *     tags={"Two-Factor Authentication"},
     *     summary="Disable 2FA",
     *     description="Disable two-factor authentication",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="current_password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA disabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication disabled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid password or 2FA not enabled"
     *     )
     * )
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], 422);
        }

        if (!password_verify($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Disable 2FA
        $user->update([
            'google2fa_enabled' => false,
            'google2fa_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully'
        ]);
    }

    /**
     * Get 2FA status for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/2fa/status",
     *     tags={"Two-Factor Authentication"},
     *     summary="Get 2FA status",
     *     description="Check if 2FA is enabled for the current user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="2FA status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="enabled", type="boolean", example=true),
     *             @OA\Property(property="recovery_codes_count", type="integer", example=8)
     *         )
     *     )
     * )
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $recoveryCodesCount = 0;
        if ($user->two_factor_recovery_codes) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $recoveryCodesCount = count($recoveryCodes);
        }

        return response()->json([
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'recovery_codes_count' => $recoveryCodesCount,
        ]);
    }

    /**
     * Generate new recovery codes.
     *
     * @OA\Post(
     *     path="/api/v1/2fa/recovery-codes",
     *     tags={"Two-Factor Authentication"},
     *     summary="Generate new recovery codes",
     *     description="Generate new recovery codes (invalidates old ones)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="current_password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recovery codes generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="New recovery codes generated"),
     *             @OA\Property(property="recovery_codes", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="2FA not enabled or invalid password"
     *     )
     * )
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], 422);
        }

        if (!password_verify($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Generate new recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes->toArray())),
        ]);

        return response()->json([
            'message' => 'New recovery codes generated',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Verify 2FA code during login.
     *
     * @OA\Post(
     *     path="/api/v1/2fa/verify",
     *     tags={"Two-Factor Authentication"},
     *     summary="Verify 2FA code",
     *     description="Verify TOTP or recovery code during login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456"),
     *             @OA\Property(property="recovery_code", type="string", example="abcd-1234")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication verified"),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="token_type", type="string", example="bearer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid code"
     *     )
     * )
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required_without:recovery_code|string|size:6',
            'recovery_code' => 'required_without:code|string',
            'challenge_token' => 'required|string'
        ]);

        // Verify the challenge token
        $challengeData = cache()->get('2fa_challenge:' . $request->challenge_token);
        if (!$challengeData || $challengeData['email'] !== $request->email) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Invalid or expired 2FA challenge token.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'email' => ['Two-factor authentication is not enabled for this user.'],
            ]);
        }

        $verified = false;

        // Verify TOTP code
        if ($request->filled('code')) {
            $secret = decrypt($user->google2fa_secret);
            $verified = $this->google2fa->verifyKey($secret, $request->code);
        }

        // Verify recovery code
        if (!$verified && $request->filled('recovery_code')) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            foreach ($recoveryCodes as $index => $recoveryCode) {
                if (hash_equals($recoveryCode, $request->recovery_code)) {
                    $verified = true;

                    // Remove used recovery code
                    unset($recoveryCodes[$index]);
                    $user->update([
                        'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes)))
                    ]);
                    break;
                }
            }
        }

        if (!$verified) {
            throw ValidationException::withMessages([
                'code' => ['The provided verification code is invalid.'],
            ]);
        }

        // Clear the challenge
        cache()->forget('2fa_challenge:' . $request->challenge_token);

        // Clean up magic link token if it was used
        if (isset($challengeData['magic_link_token'])) {
            cache()->forget('magic_link:' . $challengeData['magic_link_token']);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Update login stats
        $user->update([
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ]);

        // Log successful login event
        $this->logSuccessfulLogin($request, $user);

        return response()->json([
            'message' => 'Two-factor authentication verified',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user
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
            'login_method' => '2fa',
            'successful' => true,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Generate recovery codes.
     *
     * @return Collection
     */
    protected function generateRecoveryCodes(): Collection
    {
        return collect(range(1, 8))->map(function () {
            return Str::random(4) . '-' . Str::random(4);
        });
    }
}