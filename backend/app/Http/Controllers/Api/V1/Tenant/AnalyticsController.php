<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Filters\Tenants\Analytics\AnalyticsPeriodFilter;
use App\Http\Filters\Tenants\Analytics\IpAddressFilter;
use App\Http\Filters\Tenants\Analytics\LoginMethodFilter;
use App\Http\Filters\Tenants\Analytics\SuccessfulFilter;
use App\Http\Filters\Tenants\Analytics\UserIdFilter;
use App\Models\LoginEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{


    /**
     * Get login analytics overview.
     *
     * @OA\Get(
     *     path="/api/analytics/overview",
     *     tags={"Analytics"},
     *     summary="Get login analytics overview",
     *     description="Get comprehensive login analytics including totals, trends, and distributions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Analytics period",
     *         @OA\Schema(type="string", enum={"7d", "30d", "90d", "1y"}, default="30d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_logins", type="integer", example=1250),
     *             @OA\Property(property="unique_users", type="integer", example=89),
     *             @OA\Property(property="successful_logins", type="integer", example=1200),
     *             @OA\Property(property="failed_logins", type="integer", example=50),
     *             @OA\Property(property="success_rate", type="number", format="float", example=96.0),
     *             @OA\Property(property="daily_trends", type="array", @OA\Items(
     *                 @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                 @OA\Property(property="logins", type="integer", example=45),
     *                 @OA\Property(property="unique_users", type="integer", example=12)
     *             )),
     *             @OA\Property(property="method_distribution", type="array", @OA\Items(
     *                 @OA\Property(property="method", type="string", example="password"),
     *                 @OA\Property(property="count", type="integer", example=800),
     *                 @OA\Property(property="percentage", type="number", format="float", example=64.0)
     *             )),
     *             @OA\Property(property="geographic_distribution", type="array", @OA\Items(
     *                 @OA\Property(property="country", type="string", example="United States"),
     *                 @OA\Property(property="count", type="integer", example=450),
     *                 @OA\Property(property="percentage", type="number", format="float", example=36.0)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function overview(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'string|in:7d,30d,90d,1y'
        ]);

        // Basic metrics
        $totalLogins = LoginEvent::query()->withFilters($this->filters())->count();
        $uniqueUsers = LoginEvent::query()->withFilters($this->filters())->distinct('user_id')->count('user_id');
        $successfulLogins = LoginEvent::query()->withFilters($this->filters())->where('successful', true)->count();
        $failedLogins = LoginEvent::query()->withFilters($this->filters())->where('successful', false)->count();
        $successRate = $totalLogins > 0 ? round(($successfulLogins / $totalLogins) * 100, 2) : 0.0;

        // Daily trends
        $dailyTrends = LoginEvent::query()->withFilters($this->filters())
            ->select(
                DB::raw('DATE(attempted_at) as date'),
                DB::raw('COUNT(*) as logins'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Method distribution
        $methodDistribution = LoginEvent::query()->withFilters($this->filters())
            ->select('login_method', DB::raw('COUNT(*) as count'))
            ->groupBy('login_method')
            ->get()
            ->map(function ($item) use ($totalLogins) {
                return [
                    'method' => $item->login_method,
                    'count' => $item->count,
                    'percentage' => $totalLogins > 0 ? round(($item->count / $totalLogins) * 100, 2) : 0.0
                ];
            });

        // Basic geographic distribution (simplified - would need IP geolocation service in production)
        $geographicDistribution = LoginEvent::query()->withFilters($this->filters())
            ->select('ip_address', DB::raw('COUNT(*) as count'))
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) use ($totalLogins) {
                return [
                    'location' => 'Location ' . ($index + 1), // Placeholder - would use actual geolocation
                    'ip_range' => substr($item->ip_address, 0, strrpos($item->ip_address, '.')) . '.x',
                    'count' => $item->count,
                    'percentage' => $totalLogins > 0 ? round(($item->count / $totalLogins) * 100, 2) : 0.0
                ];
            });

        return response()->json([
            'total_logins' => $totalLogins,
            'unique_users' => $uniqueUsers,
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'success_rate' => $successRate,
            'daily_trends' => $dailyTrends,
            'method_distribution' => $methodDistribution,
            'geographic_distribution' => $geographicDistribution,
            'end_date' => now()->toDateString()
        ]);
    }

    /**
     * Get security analytics.
     *
     * @OA\Get(
     *     path="/api/analytics/security",
     *     tags={"Analytics"},
     *     summary="Get security analytics",
     *     description="Get security-focused analytics including failed login attempts, suspicious activity",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Analytics period",
     *         @OA\Schema(type="string", enum={"7d", "30d", "90d"}, default="30d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Security analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_failed_attempts", type="integer", example=125),
     *             @OA\Property(property="rate_limited_attempts", type="integer", example=45),
     *             @OA\Property(property="suspicious_ips", type="array", @OA\Items(
     *                 @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
     *                 @OA\Property(property="failed_attempts", type="integer", example=15),
     *                 @OA\Property(property="last_attempt", type="string", format="datetime")
     *             )),
     *             @OA\Property(property="failed_by_reason", type="array", @OA\Items(
     *                 @OA\Property(property="reason", type="string", example="invalid_credentials"),
     *                 @OA\Property(property="count", type="integer", example=80)
     *             ))
     *         )
     *     )
     * )
     */
    public function security(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'string|in:7d,30d,90d'
        ]);

        $period = $request->get('period', '30d');
        $startDate = $this->getStartDate($period);

        $failedLoginsQuery = LoginEvent::where('attempted_at', '>=', $startDate)
            ->where('successful', false);

        // Basic metrics
        $totalFailedAttempts = $failedLoginsQuery->count();
        $rateLimitedAttempts = $failedLoginsQuery->where('failure_reason', 'rate_limited')->count();

        // Suspicious IPs (more than 5 failed attempts)
        $suspiciousIps = $failedLoginsQuery
            ->select(
                'ip_address',
                DB::raw('COUNT(*) as failed_attempts'),
                DB::raw('MAX(attempted_at) as last_attempt')
            )
            ->groupBy('ip_address')
            ->having('failed_attempts', '>', 5)
            ->orderByDesc('failed_attempts')
            ->limit(20)
            ->get();

        // Failed attempts by reason
        $failedByReason = $failedLoginsQuery
            ->select('failure_reason', DB::raw('COUNT(*) as count'))
            ->whereNotNull('failure_reason')
            ->groupBy('failure_reason')
            ->get();

        // Hourly distribution of failed attempts
        $hourlyDistribution = $failedLoginsQuery
            ->select(DB::raw('HOUR(attempted_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'total_failed_attempts' => $totalFailedAttempts,
            'rate_limited_attempts' => $rateLimitedAttempts,
            'suspicious_ips' => $suspiciousIps,
            'failed_by_reason' => $failedByReason,
            'hourly_distribution' => $hourlyDistribution,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => now()->toDateString()
        ]);
    }

    /**
     * Get user behavior analytics.
     *
     * @OA\Get(
     *     path="/api/analytics/users",
     *     tags={"Analytics"},
     *     summary="Get user behavior analytics",
     *     description="Get user-focused analytics including activity patterns, device usage",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Analytics period",
     *         @OA\Schema(type="string", enum={"7d", "30d", "90d"}, default="30d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User analytics retrieved successfully"
     *     )
     * )
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'string|in:7d,30d,90d'
        ]);

        $period = $request->get('period', '30d');
        $startDate = $this->getStartDate($period);

        // Active users (users who logged in during the period)
        $activeUsers = User::whereHas('loginEvents', function ($query) use ($startDate) {
            $query->where('attempted_at', '>=', $startDate)
                  ->where('successful', true);
        })->count();

        // User activity patterns
        $userActivityPatterns = LoginEvent::where('attempted_at', '>=', $startDate)
            ->where('successful', true)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as login_count'),
                DB::raw('MIN(attempted_at) as first_login'),
                DB::raw('MAX(attempted_at) as last_login')
            )
            ->groupBy('user_id')
            ->get()
            ->groupBy(function ($item) {
                if ($item->login_count == 1) return 'single_login';
                if ($item->login_count <= 5) return 'low_activity';
                if ($item->login_count <= 20) return 'medium_activity';
                return 'high_activity';
            })
            ->map(function ($group) {
                return $group->count();
            });

        // Device/browser distribution
        $deviceDistribution = LoginEvent::where('attempted_at', '>=', $startDate)
            ->where('successful', true)
            ->select('user_agent', DB::raw('COUNT(*) as count'))
            ->groupBy('user_agent')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                // Simplified browser detection
                $browser = 'Unknown';
                if (str_contains($item->user_agent, 'Chrome')) $browser = 'Chrome';
                elseif (str_contains($item->user_agent, 'Firefox')) $browser = 'Firefox';
                elseif (str_contains($item->user_agent, 'Safari')) $browser = 'Safari';
                elseif (str_contains($item->user_agent, 'Edge')) $browser = 'Edge';

                return [
                    'browser' => $browser,
                    'count' => $item->count
                ];
            })
            ->groupBy('browser')
            ->map(function ($group) {
                return $group->sum('count');
            });

        return response()->json([
            'active_users' => $activeUsers,
            'user_activity_patterns' => $userActivityPatterns,
            'device_distribution' => $deviceDistribution,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => now()->toDateString()
        ]);
    }


    public function filters(): array
    {
        return [
            'period' => AnalyticsPeriodFilter::class,
            'method' => LoginMethodFilter::class,
            'successful' => SuccessfulFilter::class,
            'user_id' => UserIdFilter::class,
            'ip_address' => IpAddressFilter::class,
        ];
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30)
        };
    }
}
