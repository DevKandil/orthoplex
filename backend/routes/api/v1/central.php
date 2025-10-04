<?php

use App\Http\Controllers\Api\V1\Central\AuthController;
use App\Http\Controllers\Api\V1\Central\SystemController;
use App\Http\Controllers\Api\V1\Central\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central App API V1 Routes
|--------------------------------------------------------------------------
|
| These routes are for the central application management.
| They are NOT tenant-aware and operate on the central database.
| Access should be restricted to system administrators.
|
*/

/*
|--------------------------------------------------------------------------
| Public Central Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::group([
    'prefix' => 'v1/central',
    'as' => 'api.v1.central.',
    'middleware' => ['api', 'lang']
], function () {

    /*
    |----------------------------------------------------------------
    | Authentication Routes
    |----------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'auth',
        'as' => 'auth.'
    ], function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });
});

/*
|--------------------------------------------------------------------------
| Protected Central Routes (Authentication Required)
|--------------------------------------------------------------------------
*/
Route::group([
    'prefix' => 'v1/central',
    'as' => 'api.v1.central.',
    'middleware' => ['auth:api']
], function () {

    /*
    |----------------------------------------------------------------
    | Authenticated Auth Routes
    |----------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'auth',
        'as' => 'auth.'
    ], function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    });

    /*
    |----------------------------------------------------------------
    | System Management Routes
    |----------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'system',
        'as' => 'system.',
        'middleware' => ['can:system.manage']
    ], function () {
        Route::get('/health', [SystemController::class, 'health'])->name('health');
        Route::get('/info', [SystemController::class, 'info'])->name('info');
        Route::get('/metrics', [SystemController::class, 'metrics'])->name('metrics');
        Route::post('/cache/clear', [SystemController::class, 'clearCache'])->name('cache.clear');
    });

    /*
    |----------------------------------------------------------------
    | Tenant Management Routes
    |----------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'tenants',
        'as' => 'tenants.',
        'middleware' => ['can:tenants.manage']
    ], function () {
        Route::get('/statistics', [TenantController::class, 'statistics'])->name('statistics');
        Route::apiResource('/', TenantController::class)->parameters(['' => 'tenant']);
    });

});