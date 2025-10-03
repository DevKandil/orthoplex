<?php

namespace App\Jobs;

use App\Models\LoginDaily;
use App\Models\LoginEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateLoginDailies implements ShouldQueue
{
    use Queueable;

    protected ?Carbon $date;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::yesterday();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = $this->date->toDateString();

        Log::info("Aggregating login data for {$date}");

        // Group login events by user_id and count them
        $loginCounts = LoginEvent::whereDate('attempted_at', $date)
            ->select('user_id', DB::raw('count(*) as total_logins'))
            ->groupBy('user_id')
            ->get();

        if ($loginCounts->isEmpty()) {
            Log::info("No login events found for {$date}");
            return;
        }

        // Update or create daily records for each user
        foreach ($loginCounts as $loginCount) {
            LoginDaily::updateOrCreate(
                [
                    'user_id' => $loginCount->user_id,
                    'date' => $date,
                ],
                [
                    'total_logins' => $loginCount->total_logins,
                ]
            );
        }

        Log::info("Successfully aggregated login data for {$date}", [
            'users_processed' => $loginCounts->count(),
        ]);
    }
}
