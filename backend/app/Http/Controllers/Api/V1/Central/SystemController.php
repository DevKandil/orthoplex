<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SystemController extends Controller
{

    /**
     * Get system health status.
     *
     * @OA\Get(
     *     path="/api/v1/central/system/health",
     *     tags={"Central - System"},
     *     summary="System health check",
     *     description="Get comprehensive system health status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System health retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="checks", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="datetime")
     *         )
     *     )
     * )
     */
    public function health(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'healthy';

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'unhealthy', 'message' => 'Database connection failed'];
            $overallStatus = 'unhealthy';
        }

        // Cache check
        try {
            Cache::put('health_check', 'test', 10);
            $retrieved = Cache::get('health_check');
            if ($retrieved === 'test') {
                $checks['cache'] = ['status' => 'healthy', 'message' => 'Cache is working'];
            } else {
                throw new \Exception('Cache test failed');
            }
        } catch (\Exception $e) {
            $checks['cache'] = ['status' => 'unhealthy', 'message' => 'Cache is not working'];
            $overallStatus = 'degraded';
        }

        // Queue check
        try {
            $queueSize = \Illuminate\Support\Facades\Queue::size();
            $checks['queue'] = [
                'status' => 'healthy',
                'message' => "Queue is operational",
                'size' => $queueSize
            ];
        } catch (\Exception $e) {
            $checks['queue'] = ['status' => 'unhealthy', 'message' => 'Queue check failed'];
            $overallStatus = 'degraded';
        }

        // Storage check
        try {
            Storage::disk('local')->put('health_check.txt', 'test');
            $content = Storage::disk('local')->get('health_check.txt');
            Storage::disk('local')->delete('health_check.txt');

            if ($content === 'test') {
                $checks['storage'] = ['status' => 'healthy', 'message' => 'Storage is working'];
            } else {
                throw new \Exception('Storage test failed');
            }
        } catch (\Exception $e) {
            $checks['storage'] = ['status' => 'unhealthy', 'message' => 'Storage check failed'];
            $overallStatus = 'degraded';
        }

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getUptime(),
            'version' => config('app.version', '1.0.0')
        ]);
    }

    /**
     * Get system information.
     *
     * @OA\Get(
     *     path="/api/v1/central/system/info",
     *     tags={"Central - System"},
     *     summary="System information",
     *     description="Get system configuration and environment info",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System info retrieved successfully"
     *     )
     * )
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'application' => [
                'name' => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'laravel' => [
                'version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'database' => [
                'default' => config('database.default'),
                'connections' => array_keys(config('database.connections')),
            ],
            'cache' => [
                'default' => config('cache.default'),
                'stores' => array_keys(config('cache.stores')),
            ],
            'queue' => [
                'default' => config('queue.default'),
                'connections' => array_keys(config('queue.connections')),
            ],
            'mail' => [
                'default' => config('mail.default'),
                'mailers' => array_keys(config('mail.mailers')),
            ]
        ]);
    }

    /**
     * Get system metrics.
     *
     * @OA\Get(
     *     path="/api/v1/central/system/metrics",
     *     tags={"Central - System"},
     *     summary="System metrics",
     *     description="Get system performance and usage metrics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System metrics retrieved successfully"
     *     )
     * )
     */
    public function metrics(): JsonResponse
    {
        $metrics = [
            'system' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'execution_time' => microtime(true) - LARAVEL_START,
            ],
            'tenants' => [
                'total_count' => Tenant::count(),
                'created_today' => Tenant::whereDate('created_at', today())->count(),
                'created_this_week' => Tenant::where('created_at', '>=', now()->startOfWeek())->count(),
                'created_this_month' => Tenant::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'database' => [
                'connections' => DB::getConnections(),
                'queries_count' => count(DB::getQueryLog()),
            ]
        ];

        return response()->json([
            'metrics' => $metrics,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear system caches.
     *
     * @OA\Post(
     *     path="/api/v1/central/system/cache/clear",
     *     tags={"Central - System"},
     *     summary="Clear system caches",
     *     description="Clear application caches",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Caches cleared successfully"
     *     )
     * )
     */
    public function clearCache(): JsonResponse
    {
        $cleared = [];

        try {
            Artisan::call('cache:clear');
            $cleared[] = 'application_cache';
        } catch (\Exception $e) {
            // Handle error
        }

        try {
            Artisan::call('config:clear');
            $cleared[] = 'config_cache';
        } catch (\Exception $e) {
            // Handle error
        }

        try {
            Artisan::call('route:clear');
            $cleared[] = 'route_cache';
        } catch (\Exception $e) {
            // Handle error
        }

        try {
            Artisan::call('view:clear');
            $cleared[] = 'view_cache';
        } catch (\Exception $e) {
            // Handle error
        }

        return response()->json([
            'message' => 'System caches cleared',
            'cleared' => $cleared,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get application uptime.
     */
    private function getUptime(): array
    {
        $uptimeFile = storage_path('app/uptime.txt');

        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, now()->timestamp);
        }

        $startTime = (int) file_get_contents($uptimeFile);
        $uptime = now()->timestamp - $startTime;

        return [
            'seconds' => $uptime,
            'human' => gmdate('H:i:s', $uptime),
            'started_at' => date('Y-m-d H:i:s', $startTime)
        ];
    }
}
