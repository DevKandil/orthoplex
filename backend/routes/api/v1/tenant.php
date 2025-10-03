<?php

use App\Http\Controllers\Api\V1\Tenant\AnalyticsController;
use App\Http\Controllers\Api\V1\Tenant\AuthController;
use App\Http\Controllers\Api\V1\Tenant\EmailVerificationController;
use App\Http\Controllers\Api\V1\Tenant\GdprController;
use App\Http\Controllers\Api\V1\Tenant\MagicLinkController;
use App\Http\Controllers\Api\V1\Tenant\TwoFactorController;
use App\Http\Controllers\Api\V1\Tenant\UserController;
use App\Http\Controllers\Api\V1\Tenant\WebhookController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant App API V1 Routes
|--------------------------------------------------------------------------
|
| These routes are tenant-aware and operate within tenant context.
| All routes automatically have tenant middleware applied.
| Each tenant has isolated data and operations.
|
*/

Route::group([
    'prefix' => 'v1',
    'as' => 'api.v1.tenant.',
    'middleware' => [
        'api',
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
        'lang'
    ]
], function () {

    /*
    |----------------------------------------------------------------
    | Guest routes group
    |----------------------------------------------------------------
    */
    Route::group([
        'middleware' => ['guest'],
        'prefix' => 'auth',
        'as' => 'auth.'
    ], function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify');

        // Magic link routes
        Route::post('/magic-link', [MagicLinkController::class, 'send'])->name('magic-link.send');
        Route::get('/magic-link/verify/{token}', [MagicLinkController::class, 'verify'])->name('magic-link.verify');
    });

    // Email verification endpoint
    Route::get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('email.verify');

    // Token refresh (allows expired tokens within refresh window)
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    /*
    |----------------------------------------------------------------
    | Authenticated routes group
    |----------------------------------------------------------------
    */
    Route::group([
        'middleware' => ['auth:api']
    ], function () {

        /*
         * Auth routes
         */
        Route::group([
            'prefix' => 'auth',
            'as' => 'auth.',
        ], function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });

        /*
         * Email Verification routes
         */
        Route::group([
            'prefix' => 'email',
            'as' => 'email.',
        ], function () {
            Route::get('/verification-status', [EmailVerificationController::class, 'status'])->name('verification-status');
            Route::post('/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification-notification');
        });

        /*
         * Two-Factor Authentication routes
         */
        Route::group([
            'prefix' => '2fa',
            'as' => '2fa.',
            'middleware' => ['verified']
        ], function () {
            Route::post('/enable', [TwoFactorController::class, 'enable'])->name('enable');
            Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
            Route::get('/status', [TwoFactorController::class, 'status'])->name('status');
            Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
        });

        /*
         * Analytics routes
         */
        Route::group([
            'prefix' => 'analytics',
            'as' => 'analytics.',
            'middleware' => ['verified', 'can:analytics.view']
        ], function () {
            Route::get('/overview', [AnalyticsController::class, 'overview'])->name('overview');
            Route::get('/security', [AnalyticsController::class, 'security'])->name('security');
            Route::get('/users', [AnalyticsController::class, 'users'])->name('users');
        });

        /*
         * GDPR routes
         */
        Route::group([
            'prefix' => 'gdpr',
            'as' => 'gdpr.',
            'middleware' => ['verified']
        ], function () {
            Route::post('/export', [GdprController::class, 'requestExport'])->name('export');
            Route::get('/export/{exportId}', [GdprController::class, 'getExportStatus'])->name('export.status');
            Route::get('/download/{exportId}', [GdprController::class, 'downloadExport'])->name('download');
            Route::post('/delete', [GdprController::class, 'requestDeletion'])->name('delete');
            Route::delete('/delete/{requestId}', [GdprController::class, 'cancelDeletion'])->name('delete.cancel');
        });

        /*
         * Webhook routes
         */
        Route::group([
            'prefix' => 'webhooks',
            'as' => 'webhooks.',
            'middleware' => ['verified', 'can:webhooks.manage']
        ], function () {
            Route::get('/events', [WebhookController::class, 'availableEvents'])->name('events');
            Route::apiResource('/', WebhookController::class)->parameters(['' => 'webhook']);
            Route::post('/{webhook}/test', [WebhookController::class, 'test'])->name('test');
            Route::post('/{webhook}/regenerate-secret', [WebhookController::class, 'regenerateSecret'])->name('regenerate-secret');
            Route::get('/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->name('deliveries');
        });

        /*
         * Users
         */
        Route::group([
            'prefix' => 'users',
            'as' => 'users.',
            'middleware' => ['verified']
        ], function () {
            // Special analytics routes
            Route::get('/inactive', [UserController::class, 'inactive'])->middleware('can:users.read')->name('inactive');
            Route::get('/top-logins', [UserController::class, 'topLogins'])->middleware('can:analytics.read')->name('top-logins');

            // Resource routes
            Route::get('/', [UserController::class, 'index'])->middleware('can:users.read')->name('index');
            Route::post('/', [UserController::class, 'store'])->middleware('can:users.create')->name('store');
            Route::get('/{user}', [UserController::class, 'show'])->middleware('can:users.read,user')->name('show');
            Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.update,user')->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete,user')->name('destroy');
            Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('can:users.restore')->name('restore');
        });

    });

});