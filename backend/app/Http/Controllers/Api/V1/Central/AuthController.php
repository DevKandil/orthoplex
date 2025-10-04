<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Login to central application.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/login",
     *     tags={"Central - Auth"},
     *     summary="Central admin login",
     *     description="Login for central application administrators",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@system.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $centralUser = User::where('email', $request->email)->first();

        if (!$centralUser || !Hash::check($request->password, $centralUser->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$centralUser->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Use central guard for JWT
        $token = auth()->login($centralUser);

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $centralUser->id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'roles' => $centralUser->getRoleNames()->toArray(),
                'permissions' => $centralUser->getPermissionsViaRoles()->pluck('name')->toArray(),
                'direct_permissions' => $centralUser->getDirectPermissions()->pluck('name')->toArray()
            ]
        ]);
    }

    /**
     * Get authenticated central user.
     *
     * @OA\Get(
     *     path="/api/v1/central/auth/me",
     *     tags={"Central - Auth"},
     *     summary="Get authenticated central user",
     *     description="Get details of the authenticated central user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $user->getPermissionsViaRoles()->pluck('name')->toArray(),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->toArray(),
                'roles' => $user->getRoleNames()->toArray(),
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at
            ]
        ]);
    }

    /**
     * Logout from central application.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/logout",
     *     tags={"Central - Auth"},
     *     summary="Central admin logout",
     *     description="Logout from central application",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Refresh JWT token.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/refresh",
     *     tags={"Central - Auth"},
     *     summary="Refresh JWT token",
     *     description="Refresh the JWT token for central users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     )
     * )
     */
    public function refresh(): JsonResponse
    {
        $token = auth()->refresh();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);
    }
}