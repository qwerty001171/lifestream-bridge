<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: isset($_ENV['APP_BASE_PATH']) ? $_ENV['APP_BASE_PATH'] : dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'error'   => 'Validation failed',
                    'details' => $e->errors(),
                ], 422);
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            if (config('app.debug')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ], $status);
            }

            return response()->json([
                'error' => $status >= 500 ? 'Internal server error' : $e->getMessage(),
            ], $status);
        });
    })
    ->create();
