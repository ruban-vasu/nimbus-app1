<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $shouldRenderJson = static function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        $exceptions->render(function (ValidationException $exception, Request $request) use ($shouldRenderJson): ?JsonResponse {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => $exception->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($shouldRenderJson): ?JsonResponse {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ],
            ], 404);
        });

        $exceptions->render(function (BusinessRuleException $exception, Request $request) use ($shouldRenderJson): ?JsonResponse {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'BUSINESS_RULE_VIOLATION',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        });
    })->create();
