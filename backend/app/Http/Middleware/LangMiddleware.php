<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LangMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $acceptLanguage = $request->header('Accept-Language');

        // Default to English
        $locale = 'en';

        if ($acceptLanguage) {
            // Parse Accept-Language header and check for Arabic
            if (str_contains(strtolower($acceptLanguage), 'ar')) {
                $locale = 'ar';
            } elseif (str_contains(strtolower($acceptLanguage), 'en')) {
                $locale = 'en';
            }
        }

        // Set the application locale
        app()->setLocale($locale);

        return $next($request);
    }
}
