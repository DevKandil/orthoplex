<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookDelivery $delivery
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebhookService $webhookService): void
    {
        try {
            $webhookService->deliverWebhook($this->delivery);
        } catch (\Exception $e) {
            Log::error('Webhook delivery job failed', [
                'delivery_id' => $this->delivery->id,
                'webhook_id' => $this->delivery->webhook_id,
                'error' => $e->getMessage(),
            ]);

            $this->delivery->update([
                'status' => 'failed',
                'error_message' => 'Job execution failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook delivery job permanently failed', [
            'delivery_id' => $this->delivery->id,
            'webhook_id' => $this->delivery->webhook_id,
            'error' => $exception->getMessage(),
        ]);

        $this->delivery->update([
            'status' => 'failed',
            'error_message' => 'Job failed permanently: ' . $exception->getMessage(),
        ]);
    }
}
