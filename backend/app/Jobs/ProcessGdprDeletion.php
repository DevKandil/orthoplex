<?php

namespace App\Jobs;

use App\Models\GdprDeleteRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessGdprDeletion implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GdprDeleteRequest $deletionRequest
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if the deletion request is still pending
        if ($this->deletionRequest->status !== 'pending') {
            Log::info('GDPR deletion request is not pending', [
                'request_id' => $this->deletionRequest->request_id,
                'status' => $this->deletionRequest->status
            ]);
            return;
        }

        // Check if we've reached the scheduled deletion time
        if ($this->deletionRequest->scheduled_deletion_at > now()) {
            Log::info('GDPR deletion not yet scheduled', [
                'request_id' => $this->deletionRequest->request_id,
                'scheduled_for' => $this->deletionRequest->scheduled_deletion_at
            ]);
            return;
        }

        try {
            // Update status to processing
            $this->deletionRequest->update(['status' => 'processing']);

            // Load the user
            $user = User::find($this->deletionRequest->user_id);

            if (!$user) {
                Log::warning('User not found for GDPR deletion', [
                    'request_id' => $this->deletionRequest->request_id,
                    'user_id' => $this->deletionRequest->user_id
                ]);

                $this->deletionRequest->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'metadata' => array_merge(
                        $this->deletionRequest->metadata ?? [],
                        ['note' => 'User already deleted']
                    )
                ]);
                return;
            }

            // Perform deletion in a transaction
            DB::transaction(function () use ($user) {
                // Store metadata about what's being deleted
                $deletionMetadata = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'deleted_at' => now()->toISOString(),
                    'data_counts' => [
                        'login_events' => $user->loginEvents()->count(),
                        'audit_logs' => $user->auditLogs()->count(),
                        'gdpr_exports' => $user->gdprExports()->count(),
                    ]
                ];

                // Anonymize or delete related data based on requirements

                // Option 1: Hard delete related data
                $user->loginEvents()->delete();
                $user->gdprExports()->delete();

                // Option 2: Keep audit logs for compliance (anonymize instead)
                $user->auditLogs()->update([
                    'user_id' => null,
                    'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.anonymized', true)")
                ]);

                // Detach roles and permissions
                $user->roles()->detach();
                $user->permissions()->detach();

                // Soft delete the user (or hard delete if required)
                // Using soft delete to maintain referential integrity
                $user->delete();

                // Store deletion metadata
                $this->deletionRequest->metadata = array_merge(
                    $this->deletionRequest->metadata ?? [],
                    $deletionMetadata
                );
            });

            // Update deletion request status
            $this->deletionRequest->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            Log::info('GDPR deletion completed successfully', [
                'request_id' => $this->deletionRequest->request_id,
                'user_id' => $this->deletionRequest->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('GDPR deletion failed', [
                'request_id' => $this->deletionRequest->request_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update status to pending to retry later
            $this->deletionRequest->update([
                'status' => 'pending',
                'metadata' => array_merge(
                    $this->deletionRequest->metadata ?? [],
                    [
                        'last_error' => $e->getMessage(),
                        'last_attempt' => now()->toISOString()
                    ]
                )
            ]);

            throw $e;
        }
    }
}
