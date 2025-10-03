<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Http\Filters\Central\Tenants\SearchTenantsFilter;
use App\Http\Requests\Central\Tenants\StoreTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{


    /**
     * List all tenants (central app only).
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants",
     *     tags={"Central - Tenants"},
     *     summary="List all tenants",
     *     description="Get paginated list of all tenants in the system (central app)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="RSQL filter expression",
     *         @OA\Schema(type="string", example="status==active;created_at=gt=2024-01-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenants retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->withFilters($this->filters())
            ->with('domains');

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $tenants = $query->cursorPaginate($request->get('limit', 20));

        // Transform the data
        $tenants->getCollection()->transform(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'Unnamed Tenant',
                'username' => $tenant->username,
                'email' => $tenant->email,
                'domain' => $tenant->domains->first()?->domain ?? null,
                'database' => $tenant->id,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
                'created_by' => $tenant->created_by,
                'domains' => $tenant->domains,
                'data' => $tenant->data,
            ];
        });

        return response()->json($tenants);
    }

    public function filters(): array
    {
        return [
            'search' => SearchTenantsFilter::class,
        ];
    }

    /**
     * Create a new tenant.
     *
     * @OA\Post(
     *     path="/api/v1/central/tenants",
     *     tags={"Central - Tenants"},
     *     summary="Create tenant",
     *     description="Create a new tenant with subdomain and database",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "name"},
     *             @OA\Property(property="subdomain", type="string", example="company", description="Unique username for subdomain (company.localhost)"),
     *             @OA\Property(property="name", type="string", example="Company XYZ", description="Display name for the tenant"),
     *             @OA\Property(property="manager_name", type="string", example="Manager Name", description="Admin email for the tenant manager"),
     *             @OA\Property(property="manager_email", type="string", format="email", example="admin@company.com", description="Admin email for the tenant"),
     *             @OA\Property(property="manager_password", type="string", example="password", description="Admin password for the tenant"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant created successfully"
     *     )
     * )
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::create([
            'name' => $request->name
        ]);

        $subdomain = Str::slug(str_replace(' ', '', $request->subdomain), '_') . '.' . config('app.domain');
        $tenant->domains()->create([
            'domain' => $subdomain
        ]);

        $tenant->run(function () use ($request) {
            $mgr = User::query()->create([
                'name' => $request->manager_name,
                'email' => $request->manager_email,
                'password' => $request->manager_password,
            ]);

            $ownerRole = Role::query()->where('name', 'owner')->first();
            $mgr->roles()->attach($ownerRole->id);

            $mgr->notify(new VerifyEmailNotification);
            return $mgr;
        });

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $subdomain,
                'database' => $tenant->id,
                'created_at' => $tenant->created_at,
                'domains' => $tenant->domains
            ],
            'status' => 'Tenant created with automatic database setup'
        ], 201);
    }

    /**
     * Get tenant details.
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants/{tenant}",
     *     tags={"Central - Tenants"},
     *     summary="Get tenant",
     *     description="Get tenant details and statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tenant",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant retrieved successfully"
     *     )
     * )
     */
    public function show(string $tenantId): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($tenantId);

        // Get tenant statistics
        tenancy()->initialize($tenant);

        $stats = [
            'user_count' => \App\Models\User::count(),
            'login_events_count' => \App\Models\LoginEvent::count(),
            'created_at' => $tenant->created_at,
            'last_activity' => \App\Models\LoginEvent::latest()->first()?->created_at,
        ];

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'Unnamed Tenant',
                'domain' => $tenant->domains->first()?->domain,
                'database' => $tenant->id,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
                'domains' => $tenant->domains,
                'data' => $tenant->data,
            ],
            'statistics' => $stats
        ]);
    }

    /**
     * Delete tenant.
     *
     * @OA\Delete(
     *     path="/api/v1/central/tenants/{tenant}",
     *     tags={"Central - Tenants"},
     *     summary="Delete tenant",
     *     description="Delete a tenant and all its data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tenant",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant deleted successfully"
     *     )
     * )
     */
    public function destroy(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        // This will also drop the tenant database
        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully'
        ]);
    }

    /**
     * Get system-wide tenant statistics.
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants/statistics",
     *     tags={"Central - Tenants"},
     *     summary="Get tenant statistics",
     *     description="Get system-wide tenant statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully"
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::count(), // All tenants are active unless deleted
            'tenants_created_today' => Tenant::whereDate('created_at', today())->count(),
            'tenants_created_this_month' => Tenant::whereMonth('created_at', now()->month)->count(),
        ];

        return response()->json([
            'statistics' => $stats
        ]);
    }
}
