<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::group([
    'middleware' => [
        'api',
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
//        'lang',
//        'verifyApiKey'
    ],
    'as' => 'api.'
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
//        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });


    /*
    |----------------------------------------------------------------
    | Auth routes group
    |----------------------------------------------------------------
    */
    Route::group([
        'middleware' => ['auth:sanctum']
    ], function () {

        /*
         * Auth routes
         */
        Route::group([
            'prefix' => 'auth',
            'as' => 'auth.',
        ], function () {
//            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });

        /*
         * Users
         * This group contains routes for managing users.
         */
        Route::group([
            'prefix' => 'users',
            'as' => 'users.'
        ], function () {
//            Route::get('/me', [UserController::class, 'me'])->name('me');
//            Route::post('/update', [UserController::class, 'update'])->name('update');
        });

    });

});
