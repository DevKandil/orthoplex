<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Available webhook events.
     */
    protected array $availableEvents = [
        'user.created',
        'user.updated',
        'user.deleted',
        'user.login',
        'user.logout',
        'user.email_verified',
        'user.2fa_enabled',
        'user.2fa_disabled',
        'tenant.created',
        'tenant.updated',
        'tenant.deleted',
        'webhook.test',
        'gdpr.export_completed',
        'gdpr.deletion_requested',
        'gdpr.deletion_completed',
    ];

    /**
     * Send webhook to all subscribed endpoints for a given event.
     *
     * @param string $eventType
     * @param array $payload
     * @return int Number of webhooks triggered
     */
    public function triggerEvent(string $eventType, array $payload): int
    {
        $webhooks = Webhook::where('active', true)
            ->whereJsonContains('events', $eventType)
            ->get();

        $triggered = 0;

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $eventType, $payload);
            $triggered++;
        }

        return $triggered;
    }

    /**
     * Send webhook to a specific endpoint.
     *
     * @param Webhook $webhook
     * @param string $eventType
     * @param array $payload
     * @return WebhookDelivery
     */
    public function sendWebhook(Webhook $webhook, string $eventType, array $payload): WebhookDelivery
    {
        // Create delivery record
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        // Dispatch delivery job
        DeliverWebhook::dispatch($delivery);

        // Update webhook last triggered time
        $webhook->update(['last_triggered_at' => now()]);

        return $delivery;
    }

    /**
     * Deliver webhook payload to endpoint.
     *
     * @param WebhookDelivery $delivery
     * @return bool
     */
    public function deliverWebhook(WebhookDelivery $delivery): bool
    {
        $webhook = $delivery->webhook;

        if (!$webhook->active) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => 'Webhook is inactive'
            ]);
            return false;
        }

        $delivery->increment('attempts');

        try {
            // Prepare payload
            $fullPayload = [
                'event' => $delivery->event_type,
                'data' => $delivery->payload,
                'webhook_id' => $webhook->id,
                'delivery_id' => $delivery->id,
                'timestamp' => now()->toISOString(),
            ];

            // Generate HMAC signature
            $signature = $this->generateSignature($fullPayload, $webhook->secret);

            // Prepare headers
            $headers = array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Orthoplex-Webhooks/1.0',
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $delivery->event_type,
                'X-Webhook-Delivery' => $delivery->id,
            ], $webhook->headers ?? []);

            // Send webhook
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($webhook->url, $fullPayload);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => $response->body(),
                'signature' => $signature,
            ]);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'delivered_at' => now(),
                ]);

                Log::info('Webhook delivered successfully', [
                    'webhook_id' => $webhook->id,
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'status_code' => $response->status(),
                ]);

                return true;
            } else {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }

        } catch (\Exception $e) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::warning('Webhook delivery failed', [
                'webhook_id' => $webhook->id,
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'error' => $e->getMessage(),
                'attempts' => $delivery->attempts,
            ]);

            // Schedule retry if under max attempts
            if ($delivery->attempts < $webhook->max_retries) {
                $this->scheduleRetry($delivery, $webhook->retry_delay);
            }

            return false;
        }
    }

    /**
     * Schedule webhook retry.
     *
     * @param WebhookDelivery $delivery
     * @param int $delaySeconds
     */
    protected function scheduleRetry(WebhookDelivery $delivery, int $delaySeconds): void
    {
        $retryAt = now()->addSeconds($delaySeconds * $delivery->attempts); // Exponential backoff

        $delivery->update([
            'status' => 'pending',
            'next_retry_at' => $retryAt,
        ]);

        DeliverWebhook::dispatch($delivery)->delay($retryAt);

        Log::info('Webhook retry scheduled', [
            'delivery_id' => $delivery->id,
            'retry_at' => $retryAt,
            'attempt' => $delivery->attempts,
        ]);
    }

    /**
     * Generate HMAC signature for webhook payload.
     *
     * @param array $payload
     * @param string $secret
     * @return string
     */
    protected function generateSignature(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get available webhook events.
     *
     * @return array
     */
    public function getAvailableEvents(): array
    {
        return $this->availableEvents;
    }

    /**
     * Get available webhook events with descriptions.
     *
     * @return array
     */
    public function getAvailableEventsWithDescriptions(): array
    {
        return [
            'user.created' => 'User account created',
            'user.updated' => 'User profile updated',
            'user.deleted' => 'User account deleted',
            'user.login' => 'User logged in',
            'user.logout' => 'User logged out',
            'user.email_verified' => 'User email verified',
            'user.2fa_enabled' => 'Two-factor authentication enabled',
            'user.2fa_disabled' => 'Two-factor authentication disabled',
            'tenant.created' => 'New tenant created',
            'tenant.updated' => 'Tenant updated',
            'tenant.deleted' => 'Tenant deleted',
            'webhook.test' => 'Test webhook event',
            'gdpr.export_completed' => 'GDPR data export completed',
            'gdpr.deletion_requested' => 'GDPR account deletion requested',
            'gdpr.deletion_completed' => 'GDPR account deletion completed',
        ];
    }

    /**
     * Get webhook delivery statistics.
     *
     * @param Webhook $webhook
     * @param int $days
     * @return array
     */
    public function getDeliveryStats(Webhook $webhook, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $deliveries = $webhook->deliveries()
            ->where('created_at', '>=', $startDate)
            ->get();

        $total = $deliveries->count();
        $successful = $deliveries->where('status', 'success')->count();
        $failed = $deliveries->where('status', 'failed')->count();
        $pending = $deliveries->where('status', 'pending')->count();

        return [
            'total_deliveries' => $total,
            'successful_deliveries' => $successful,
            'failed_deliveries' => $failed,
            'pending_deliveries' => $pending,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'average_response_time' => $deliveries->where('status', 'success')
                ->avg('response_time') ?? 0,
        ];
    }
}