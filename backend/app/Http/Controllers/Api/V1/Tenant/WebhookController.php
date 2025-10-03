<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Filters\Tenants\Webhooks\ActiveFilter;
use App\Http\Filters\Tenants\Webhooks\SearchWebhooksFilter;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
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
     *     path="/api/webhooks",
     *     tags={"Webhooks"},
     *     summary="List webhooks",
     *     description="Get paginated list of webhooks with advanced filtering",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="RSQL filter expression",
     *         @OA\Schema(type="string", example="active==true;events=in=user.created")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhooks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Webhook"))
     *         )
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
     *     path="/api/webhooks",
     *     tags={"Webhooks"},
     *     summary="Create webhook",
     *     description="Create a new webhook endpoint",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "url", "events"},
     *             @OA\Property(property="name", type="string", example="User Registration Hook"),
     *             @OA\Property(property="url", type="string", format="url", example="https://api.example.com/webhooks"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"user.created", "user.updated"}),
     *             @OA\Property(property="secret", type="string", nullable=true),
     *             @OA\Property(property="headers", type="object", nullable=true),
     *             @OA\Property(property="max_retries", type="integer", minimum=0, maximum=10, default=3),
     *             @OA\Property(property="retry_delay", type="integer", minimum=5, maximum=3600, default=60)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Webhook created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Webhook")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', $this->webhookService->getAvailableEvents()),
            'secret' => 'nullable|string|max:255',
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:1000',
            'max_retries' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:5|max:3600'
        ]);

        $webhook = Webhook::create([
            'name' => $request->name,
            'url' => $request->url,
            'events' => $request->events,
            'secret' => $request->secret ?: Str::random(32),
            'headers' => $request->headers,
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
     *     path="/api/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Get webhook",
     *     description="Get webhook details by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Webhook")
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
     *     path="/api/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Update webhook",
     *     description="Update webhook configuration",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="url", type="string", format="url"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="active", type="boolean"),
     *             @OA\Property(property="headers", type="object"),
     *             @OA\Property(property="max_retries", type="integer"),
     *             @OA\Property(property="retry_delay", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'url' => 'url|max:2048',
            'events' => 'array|min:1',
            'events.*' => 'string|in:' . implode(',', $this->webhookService->getAvailableEvents()),
            'active' => 'boolean',
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:1000',
            'max_retries' => 'integer|min:0|max:10',
            'retry_delay' => 'integer|min:5|max:3600'
        ]);

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
     *     path="/api/webhooks/{webhook}",
     *     tags={"Webhooks"},
     *     summary="Delete webhook",
     *     description="Delete a webhook endpoint",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook deleted successfully"
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
     *     path="/api/webhooks/{webhook}/test",
     *     tags={"Webhooks"},
     *     summary="Test webhook",
     *     description="Send a test payload to webhook endpoint",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test webhook sent"
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
     *     path="/api/webhooks/{webhook}/deliveries",
     *     tags={"Webhooks"},
     *     summary="Get webhook deliveries",
     *     description="Get delivery history for a webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliveries retrieved successfully"
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
     *     path="/api/webhooks/events",
     *     tags={"Webhooks"},
     *     summary="Get available events",
     *     description="Get list of available webhook events",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Events retrieved successfully"
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
     *     path="/api/webhooks/{webhook}/regenerate-secret",
     *     tags={"Webhooks"},
     *     summary="Regenerate webhook secret",
     *     description="Generate a new HMAC secret for webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="webhook",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secret regenerated successfully"
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
