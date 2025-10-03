<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Arr;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'lang' => \App\Http\Middleware\LangMiddleware::class,
            'json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->redirectGuestsTo(fn() => throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = 500;

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                } elseif (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                }

                return response()->json([
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => app()->environment('local', 'staging') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->map(function ($trace) {
                            return Arr::except($trace, ['args']);
                        })->all(),
                    ] : null,
                ], $statusCode);
            }
        });
    })->create();
