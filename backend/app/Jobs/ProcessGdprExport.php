<?php

namespace App\Jobs;

use App\Models\GdprExport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessGdprExport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GdprExport $export
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update status to processing
            $this->export->update(['status' => 'processing']);

            // Load user with all relationships
            $user = User::with([
                'roles',
                'permissions',
                'loginEvents',
                'auditLogs'
            ])->findOrFail($this->export->user_id);

            // Collect all user data
            $userData = [
                'export_info' => [
                    'export_id' => $this->export->export_id,
                    'generated_at' => now()->toISOString(),
                    'format' => $this->export->format,
                ],
                'personal_information' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->timezone,
                    'locale' => $user->locale,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'google2fa_enabled' => $user->google2fa_enabled,
                    'created_at' => $user->created_at->toISOString(),
                    'updated_at' => $user->updated_at->toISOString(),
                    'last_login_at' => $user->last_login_at?->toISOString(),
                    'login_count' => $user->login_count,
                ],
                'roles_and_permissions' => [
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->permissions->pluck('name'),
                ],
                'login_history' => $user->loginEvents->map(function ($event) {
                    return [
                        'attempted_at' => $event->attempted_at->toISOString(),
                        'successful' => $event->successful,
                        'login_method' => $event->login_method,
                        'ip_address' => $event->ip_address,
                        'user_agent' => $event->user_agent,
                        'failure_reason' => $event->failure_reason,
                    ];
                })->toArray(),
                'audit_logs' => $user->auditLogs->map(function ($log) {
                    return [
                        'action' => $log->action,
                        'description' => $log->description,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->created_at->toISOString(),
                    ];
                })->toArray(),
            ];

            // Generate file based on format
            $fileName = 'gdpr-export-' . $this->export->export_id . '.' . $this->export->format;
            $filePath = 'gdpr-exports/' . $fileName;

            if ($this->export->format === 'json') {
                $content = json_encode($userData, JSON_PRETTY_PRINT);
            } elseif ($this->export->format === 'csv') {
                $content = $this->convertToCSV($userData);
            } else {
                throw new \Exception('Unsupported export format: ' . $this->export->format);
            }

            // Store the file
            $disk = config('gdpr.export_disk', 'local');
            Storage::disk($disk)->put($filePath, $content);

            // Update export record
            $this->export->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => strlen($content),
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $this->export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Convert data to CSV format.
     */
    private function convertToCSV(array $data): string
    {
        $csv = "GDPR Data Export\n\n";

        // Personal Information
        $csv .= "PERSONAL INFORMATION\n";
        $csv .= "Field,Value\n";
        foreach ($data['personal_information'] as $key => $value) {
            $csv .= "$key," . json_encode($value) . "\n";
        }

        $csv .= "\nROLES AND PERMISSIONS\n";
        $csv .= "Type,Name\n";
        foreach ($data['roles_and_permissions']['roles'] as $role) {
            $csv .= "Role,$role\n";
        }
        foreach ($data['roles_and_permissions']['permissions'] as $permission) {
            $csv .= "Permission,$permission\n";
        }

        // Login History
        $csv .= "\nLOGIN HISTORY\n";
        $csv .= "Date,Successful,Method,IP Address\n";
        foreach ($data['login_history'] as $login) {
            $csv .= sprintf(
                "%s,%s,%s,%s\n",
                $login['attempted_at'],
                $login['successful'] ? 'Yes' : 'No',
                $login['login_method'],
                $login['ip_address']
            );
        }

        // Audit Logs
        $csv .= "\nAUDIT LOGS\n";
        $csv .= "Date,Action,Description\n";
        foreach ($data['audit_logs'] as $log) {
            $csv .= sprintf(
                "%s,%s,%s\n",
                $log['created_at'],
                $log['action'],
                str_replace(["\n", "\r", ","], [" ", " ", ";"], $log['description'])
            );
        }

        return $csv;
    }
}
