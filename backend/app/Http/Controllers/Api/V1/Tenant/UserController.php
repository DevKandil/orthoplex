<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Filters\Tenants\Users\EmailVerifiedFilter;
use App\Http\Filters\Tenants\Users\RoleFilter;
use App\Http\Filters\Tenants\Users\SearchUsersFilter;
use App\Http\Requests\Tenants\Users\CreateUserRequest;
use App\Http\Requests\Tenants\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * @OA\Get(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="List users with advanced querying",
     *     description="Get paginated list of users with RSQL filtering, cursor pagination, and advanced sorting",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="RSQL filter expression",
     *         @OA\Schema(type="string", example="name=like=john;email!=admin@example.com")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Global text search across name and email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort fields (comma-separated, prefix with - for desc)",
     *         @OA\Schema(type="string", example="name,-created_at")
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Select specific fields to return",
     *         @OA\Schema(type="string", example="id,name,email,created_at")
     *     ),
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         description="Cursor for pagination",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Pagination direction",
     *         @OA\Schema(type="string", enum={"next", "prev"}, default="next")
     *     ),
     *     @OA\Parameter(
     *         name="include_total",
     *         in="query",
     *         description="Include total count (expensive operation)",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter by creation date start",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter by creation date end",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="cursor", type="string", nullable=true),
     *                 @OA\Property(property="next_cursor", type="string", nullable=true),
     *                 @OA\Property(property="prev_cursor", type="string", nullable=true),
     *                 @OA\Property(property="has_more", type="boolean"),
     *                 @OA\Property(property="limit", type="integer"),
     *                 @OA\Property(property="total", type="integer", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->withFilters($this->filters());

        // Apply field selection (sparse fieldsets)
        if ($request->has('fields')) {
            $fields = explode(',', $request->get('fields'));
            $allowedFields = ['id', 'name', 'email', 'email_verified_at', 'google2fa_enabled', 'last_login_at', 'login_count', 'created_at', 'updated_at'];
            $validFields = array_intersect($fields, $allowedFields);
            if (!empty($validFields)) {
                $query->select($validFields);
            }
        }

        // Apply includes (relationships)
        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            $allowedIncludes = ['roles', 'permissions'];
            $validIncludes = array_intersect($includes, $allowedIncludes);
            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $users = $query->cursorPaginate($request->get('limit', 15));

        return response()->json($users);
    }

    /**
     * Store a newly created user.
     *
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Create a new user",
     *     description="Create a new user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="timezone", type="string", example="UTC"),
     *             @OA\Property(property="locale", type="string", example="en"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"member"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'timezone' => $request->get('timezone', 'UTC'),
            'locale' => $request->get('locale', 'en'),
        ]);

        // Assign roles if provided
        if ($request->has('roles')) {
            $user->assignRole($request->roles);
        } else {
            // Default role
            $user->assignRole('member');
        }

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     *
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Get user details",
     *     description="Retrieve detailed information about a specific user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relationships",
     *         @OA\Schema(type="string", example="roles,permissions")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Handle includes parameter
        $includes = $request->get('include');
        $allowedIncludes = ['roles', 'permissions', 'loginEvents'];

        if ($includes) {
            $requestedIncludes = array_map('trim', explode(',', $includes));
            $validIncludes = array_intersect($requestedIncludes, $allowedIncludes);

            if (!empty($validIncludes)) {
                $user->load($validIncludes);
            }
        }

        return response()->json($user);
    }

    /**
     * Update the specified user.
     *
     * @OA\Put(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Update user information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="timezone", type="string", example="America/New_York"),
     *             @OA\Property(property="locale", type="string", example="en"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"admin"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->fill($request->validated());
        $user->save();

        // Update roles if provided
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Soft delete the specified user.
     *
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Soft delete a user (can be restored)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        if (!$user->canBeDeleted()) {
            return response()->json([
                'error' => 'Cannot delete user',
                'message' => 'This user cannot be deleted due to business constraints.'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Restore a soft-deleted user.
     *
     * @OA\Post(
     *     path="/api/v1/users/{id}/restore",
     *     tags={"Users"},
     *     summary="Restore deleted user",
     *     description="Restore a soft-deleted user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User restored successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $user->restore();

        return response()->json([
            'message' => 'User restored successfully',
            'user' => $user
        ]);
    }

    /**
     * Get inactive users.
     *
     * @OA\Get(
     *     path="/api/v1/users/inactive",
     *     tags={"Users"},
     *     summary="Get inactive users",
     *     description="Get users who haven't logged in within the specified time window",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="window",
     *         in="query",
     *         description="Time window (hour, day, week, month)",
     *         @OA\Schema(type="string", enum={"hour", "day", "week", "month"}, example="week")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inactive users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function inactive(Request $request): JsonResponse
    {
        $window = $request->get('window', 'week');

        $users = User::inactive($window)
            ->with('roles')
            ->cursorPaginate(15);

        return response()->json($users);
    }

    /**
     * Get top login users.
     *
     * @OA\Get(
     *     path="/api/v1/users/top-logins",
     *     tags={"Users"},
     *     summary="Get users with most logins",
     *     description="Get users ordered by login count within time window",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="window",
     *         in="query",
     *         description="Time window",
     *         @OA\Schema(type="string", enum={"7d", "30d"}, example="7d")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of users to return",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top login users retrieved successfully"
     *     )
     * )
     */
    public function topLogins(Request $request): JsonResponse
    {
        $window = $request->get('window', '7d');
        $limit = $request->get('limit', 10);

        $date = match($window) {
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subWeek(),
        };

        $users = User::withCount([
                'loginEvents as recent_logins_count' => function ($query) use ($date) {
                    $query->where('attempted_at', '>=', $date)
                          ->where('successful', true);
                }
            ])
            ->having('recent_logins_count', '>', 0)
            ->orderByDesc('recent_logins_count')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $users,
            'meta' => [
                'window' => $window,
                'limit' => $limit,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Define available filters for user queries.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
            'search' => SearchUsersFilter::class,
            'role' => RoleFilter::class,
            'email_verified' => EmailVerifiedFilter::class,
        ];
    }
}