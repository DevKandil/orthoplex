<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Filters\Tenants\Webhooks\ActiveFilter;
use App\Http\Filters\Tenants\Webhooks\SearchWebhooksFilter;
use App\Http\Requests\Tenants\Webhooks\StoreWebhookRequest;
use App\Http\Requests\Tenants\Webhooks\UpdateWebhookRequest;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{

    public function __construct(
        private WebhookService $webhookService
    ) {
    }

    /**
     * List webhooks.
     *
     * @OA\Get(
     *     path="/api/v1/webhooks",
     *     tags={"Webhooks"},
     *     summary="List all webhooks",
     *     description="Retrieve a paginated list of all webhooks with optional filtering and sorting. Supports cursor-based pagination for efficient browsing of large datasets.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (default: 15)",
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Field to sort by (default: created_at)",
     *         @OA\Schema(type="string", default="created_at", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Sort direction (asc or desc, default: desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search webhooks by name or URL",
     *         @OA\Schema(type="string", example="production")
     *     ),
     *     @OA\Parameter(
     *         name="filter[active]",
     *         in="query",
     *         description="Filter by active status (true/false)",
     *         @OA\Schema(type="string", enum={"true", "false"}, example="true")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhooks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Webhook")),
     *             @OA\Property(property="path", type="string", example="http://app.orthoplex.test/api/v1/webhooks"),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="next_cursor", type="string", nullable=true),
     *             @OA\Property(property="next_page_url", type="string", nullable=true),
     *             @OA\Property(property="prev_cursor", type="string", nullable=true),
     *             @OA\Property(property="prev_page_url", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Webhook::query()->withFilters($this->filters());

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $webhooks = $query->cursorPaginate($request->get('limit', 15));

        return response()->json($webhooks);
    }

    public function filters(): array
    {
        return [
            'search' => SearchWebhooksFilter::class,
            'active' => ActiveFilter::class,
        ];
    }

    /**
     * Create a new webhook.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks",
     *     tags={"Webhooks"},
     *     summary="Create a new webhook",
     *     description="Register a new webhook endpoint to receive real-time event notifications. The webhook will be automatically assigned a secret for HMAC signature verification if one is not provided.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Webhook configuration",
     *         @OA\JsonContent(
     *             required={"name", "url", "events"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 description="Descriptive name for the webhook",
     *                 example="Production User Events"
     *             ),
     *             @OA\Property(
     *                 property="url",
     *                 type="string",
     *                 format="url",
     *                 maxLength=2048,
     *                 description="HTTPS endpoint URL to receive webhook POST requests",
     *                 example="https://api.example.com/webhooks/orthoplex"
     *             ),
     *             @OA\Property(
     *                 property="events",
     *                 type="array",
     *                 description="Array of event types to subscribe to",
     *                 @OA\Items(
     *                     type="string",
     *                     enum={"user.created", "user.updated", "user.deleted", "user.login", "user.logout", "user.email_verified", "user.2fa_enabled", "user.2fa_disabled", "tenant.created", "tenant.updated", "tenant.deleted", "webhook.test", "gdpr.export_completed", "gdpr.deletion_requested", "gdpr.deletion_completed"}
     *                 ),
     *                 example={"user.created", "user.updated", "user.login"}
     *             ),
     *             @OA\Property(
     *                 property="secret",
     *                 type="string",
     *                 maxLength=255,
     *                 nullable=true,
     *                 description="Optional custom HMAC secret key (auto-generated if not provided)",
     *                 example="my-custom-secret-key"
     *             ),
     *             @OA\Property(
     *                 property="headers",
     *                 type="object",
     *                 nullable=true,
     *                 description="Optional custom HTTP headers to include in webhook requests",
     *                 example={"X-Custom-Header": "value", "Authorization": "Bearer token"}
     *             ),
     *             @OA\Property(
     *                 property="max_retries",
     *                 type="integer",
     *                 minimum=0,
     *                 maximum=10,
     *                 default=3,
     *                 description="Maximum number of retry attempts for failed deliveries",
     *                 example=3
     *             ),
     *             @OA\Property(
     *                 property="retry_delay",
     *                 type="integer",
     *                 minimum=5,
     *                 maximum=3600,
     *                 default=60,
     *                 description="Base delay in seconds between retry attempts (uses exponential backoff)",
     *                 example=60
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Webhook created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Webhook created successfully"),
     *             @OA\Property(property="webhook", ref="#/components/schemas/Webhook")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The events field is required."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="events", type="array", @OA\Items(type="string", example="The events field is required."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $webhook = Webhook::create([
            'name' => $request->name,
            'url' => $request->url,
            'events' => $request->events,
            'secret' => $request->secret ?: Str::random(32),
            'headers' => $request->input('headers'),
            'max_retries' => $request->get('max_retries', 3),
            'retry_delay' => $request->get('retry_delay', 60),
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Webhook created successfully',
            'webhook' => $webhook
        ], 201);
    }

    /**
     * Get webhook details.
     *
     * @OA\Get(
     *     path="/api/v1/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Get webhook details",
     *     description="Retrieve detailed information about a specific webhook, including its configuration and the 10 most recent delivery attempts.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="webhook",
     *                 type="object",
     *                 allOf={@OA\Schema(ref="#/components/schemas/Webhook")},
     *                 @OA\Property(
     *                     property="deliveries",
     *                     type="array",
     *                     description="10 most recent delivery attempts",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="event_type", type="string", example="user.created"),
     *                         @OA\Property(property="status", type="string", enum={"pending", "success", "failed"}, example="success"),
     *                         @OA\Property(property="attempts", type="integer", example=1),
     *                         @OA\Property(property="response_status", type="integer", nullable=true, example=200),
     *                         @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function show(Webhook $webhook): JsonResponse
    {
        return response()->json([
            'webhook' => $webhook->load(['deliveries' => function ($query) {
                $query->latest()->limit(10);
            }])
        ]);
    }

    /**
     * Update webhook.
     *
     * @OA\Put(
     *     path="/api/v1/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Update webhook configuration",
     *     description="Modify an existing webhook's settings. All fields are optional - only provided fields will be updated. The secret cannot be updated here - use the regenerate-secret endpoint instead.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, description="Webhook name", example="Updated Webhook Name"),
     *             @OA\Property(property="url", type="string", format="url", maxLength=2048, description="Endpoint URL", example="https://api.example.com/webhooks/new-endpoint"),
     *             @OA\Property(property="events", type="array", description="Event subscriptions", @OA\Items(type="string"), example={"user.created", "user.deleted"}),
     *             @OA\Property(property="active", type="boolean", description="Enable or disable webhook", example=true),
     *             @OA\Property(property="headers", type="object", nullable=true, description="Custom HTTP headers", example={"X-API-Key": "new-key"}),
     *             @OA\Property(property="max_retries", type="integer", minimum=0, maximum=10, description="Maximum retry attempts", example=5),
     *             @OA\Property(property="retry_delay", type="integer", minimum=5, maximum=3600, description="Retry delay in seconds", example=120)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Webhook updated successfully"),
     *             @OA\Property(property="webhook", ref="#/components/schemas/Webhook")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function update(UpdateWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $webhook->update($request->only([
            'name', 'url', 'events', 'active', 'headers', 'max_retries', 'retry_delay'
        ]));

        return response()->json([
            'message' => 'Webhook updated successfully',
            'webhook' => $webhook
        ]);
    }

    /**
     * Delete webhook.
     *
     * @OA\Delete(
     *     path="/api/v1/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Delete webhook",
     *     description="Permanently delete a webhook endpoint. This action cannot be undone. All associated delivery history will also be deleted.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Webhook deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully'
        ]);
    }

    /**
     * Test webhook endpoint.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/{webhook}/test",
     *     tags={"Webhooks"},
     *     summary="Send test webhook",
     *     description="Trigger a test webhook delivery with a 'webhook.test' event. This allows you to verify your endpoint is correctly configured to receive and process webhooks. The delivery is queued and processed asynchronously.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test webhook queued successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Test webhook sent"),
     *             @OA\Property(property="delivery_id", type="integer", description="ID of the created delivery record", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function test(Webhook $webhook): JsonResponse
    {
        $testPayload = [
            'event' => 'webhook.test',
            'data' => [
                'webhook_id' => $webhook->id,
                'timestamp' => now()->toISOString(),
                'test' => true
            ]
        ];

        $delivery = $this->webhookService->sendWebhook($webhook, 'webhook.test', $testPayload);

        return response()->json([
            'message' => 'Test webhook sent',
            'delivery_id' => $delivery->id
        ]);
    }

    /**
     * Get webhook deliveries.
     *
     * @OA\Get(
     *     path="/api/v1/webhooks/{webhook}/deliveries",
     *     tags={"Webhooks"},
     *     summary="Get webhook delivery history",
     *     description="Retrieve a paginated list of all delivery attempts for a specific webhook, including status, response details, and error messages. Useful for debugging and monitoring webhook reliability.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (default: 20)",
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Field to sort by (default: created_at)",
     *         @OA\Schema(type="string", default="created_at", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Sort direction (asc or desc, default: desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliveries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="webhook_id", type="integer", example=1),
     *                     @OA\Property(property="event_type", type="string", example="user.created"),
     *                     @OA\Property(property="payload", type="object", description="Event payload sent to webhook"),
     *                     @OA\Property(property="status", type="string", enum={"pending", "success", "failed"}, example="success"),
     *                     @OA\Property(property="attempts", type="integer", description="Number of delivery attempts", example=1),
     *                     @OA\Property(property="response_status", type="integer", nullable=true, description="HTTP response code", example=200),
     *                     @OA\Property(property="response_body", type="string", nullable=true, description="Response body from endpoint"),
     *                     @OA\Property(property="error_message", type="string", nullable=true, description="Error message if failed"),
     *                     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="next_retry_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="signature", type="string", nullable=true, description="HMAC signature sent with request"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="path", type="string"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="next_cursor", type="string", nullable=true),
     *             @OA\Property(property="prev_cursor", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function deliveries(Request $request, Webhook $webhook): JsonResponse
    {
        $query = $webhook->deliveries();

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $deliveries = $query->cursorPaginate($request->get('limit', 20));

        return response()->json($deliveries);
    }

    /**
     * Get available webhook events.
     *
     * @OA\Get(
     *     path="/api/v1/webhooks/events",
     *     tags={"Webhooks"},
     *     summary="List available webhook events",
     *     description="Retrieve a complete list of all webhook event types available in the system, along with their descriptions. Use these event names when creating or updating webhooks.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="events",
     *                 type="object",
     *                 description="Map of event names to descriptions",
     *                 example={
     *                     "user.created": "User account created",
     *                     "user.updated": "User profile updated",
     *                     "user.deleted": "User account deleted",
     *                     "user.login": "User logged in",
     *                     "user.logout": "User logged out",
     *                     "user.email_verified": "User email verified",
     *                     "user.2fa_enabled": "Two-factor authentication enabled",
     *                     "user.2fa_disabled": "Two-factor authentication disabled",
     *                     "tenant.created": "New tenant created",
     *                     "tenant.updated": "Tenant updated",
     *                     "tenant.deleted": "Tenant deleted",
     *                     "webhook.test": "Test webhook event",
     *                     "gdpr.export_completed": "GDPR data export completed",
     *                     "gdpr.deletion_requested": "GDPR account deletion requested",
     *                     "gdpr.deletion_completed": "GDPR account deletion completed"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function availableEvents(): JsonResponse
    {
        return response()->json([
            'events' => $this->webhookService->getAvailableEventsWithDescriptions()
        ]);
    }

    /**
     * Regenerate webhook secret.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/{webhook}/regenerate-secret",
     *     tags={"Webhooks"},
     *     summary="Regenerate webhook HMAC secret",
     *     description="Generate a new random HMAC secret key for webhook signature verification. This immediately invalidates the old secret - make sure to update your endpoint to use the new secret before the next webhook is delivered. The new secret is returned in the response.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secret regenerated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Webhook secret regenerated successfully"),
     *             @OA\Property(property="secret", type="string", description="The new HMAC secret (store this securely)", example="XoCJuTjapNOD1YwPZVASiPLc2uAGbdxH")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Missing webhooks.manage permission"
     *     )
     * )
     */
    public function regenerateSecret(Webhook $webhook): JsonResponse
    {
        $webhook->update([
            'secret' => Str::random(32)
        ]);

        return response()->json([
            'message' => 'Webhook secret regenerated successfully',
            'secret' => $webhook->secret
        ]);
    }
}
