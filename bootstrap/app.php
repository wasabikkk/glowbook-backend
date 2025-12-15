<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Using custom Bearer token authentication
        // Tokens are stored in personal_access_tokens table (hashed)
        
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Disable CSRF for all API routes (using Bearer tokens instead)
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle authentication exceptions for API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                // Try to delete tampered/invalid token if present
                $token = $request->bearerToken();
                if ($token) {
                    \App\Models\PersonalAccessToken::where('token', $token)->delete();
                }
                
                return response()->json([
                    'message' => 'Unauthenticated. Invalid or missing token.',
                ], 401);
            }
        });
    })->create();
