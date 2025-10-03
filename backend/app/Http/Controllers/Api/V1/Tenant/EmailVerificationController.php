<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{

    /**
     * Send a new email verification notification.
     *
     * @OA\Post(
     *     path="/api/v1/email/verification-notification",
     *     tags={"Email Verification"},
     *     summary="Resend verification email",
     *     description="Send a new email verification notification to the user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Verification email sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification email sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Email already verified"
     *     )
     * )
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 422);
        }

        $request->user()->notify(new VerifyEmailNotification);

        return response()->json([
            'message' => 'Verification email sent successfully'
        ]);
    }

    /**
     * Verify the user's email address.
     *
     * @OA\Get(
     *     path="/api/v1/email/verify/{id}/{hash}",
     *     tags={"Email Verification"},
     *     summary="Verify email address",
     *     description="Verify the user's email address using the verification link",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Email already verified"
     *     )
     * )
     */
    public function verify(Request $request): JsonResponse
    {
        $user = User::query()->findOrFail((int)$request->route('user'));

        if (!$user) {
            return response()->json([
                'error' => 'Invalid verification link'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 422);
        }

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'error' => 'Invalid verification link'
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    /**
     * Get email verification status.
     *
     * @OA\Get(
     *     path="/api/v1/email/verification-status",
     *     tags={"Email Verification"},
     *     summary="Check verification status",
     *     description="Check if the current user's email is verified",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Verification status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="verified", type="boolean", example=true),
     *             @OA\Property(property="verified_at", type="string", format="date-time", nullable=true)
     *         )
     *     )
     * )
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'verified' => $user->hasVerifiedEmail(),
            'verified_at' => $user->email_verified_at,
        ]);
    }
}