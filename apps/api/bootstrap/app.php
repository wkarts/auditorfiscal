<?php

use App\Console\Commands\DeclareRabbitMqQueues;
use App\Console\Commands\VerifyObjectStorage;
use App\Http\Middleware\AuditRequest;
use App\Models\AnalysisBatch;
use App\Services\AnalysisFailure;
use App\Services\ApplicationLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([DeclareRabbitMqQueues::class, VerifyObjectStorage::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [AuditRequest::class]);
        $middleware->alias(['role' => \Spatie\Permission\Middleware\RoleMiddleware::class, 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => request()->is('api/*'));
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = AnalysisFailure::httpStatus($exception);
            if ($status < 500) {
                return null;
            }
            $failure = AnalysisFailure::from($exception);
            $routeBatch = $request->route('batch');
            $batch = $routeBatch instanceof AnalysisBatch ? $routeBatch : null;
            ApplicationLogger::record('error', 'api', 'unhandled_exception', $failure['message'], $failure, $batch,
                $request->user()?->id, $request->attributes->get('request_id'), $failure['incident_id']);

            return response()->json([
                'message' => $failure['message'],
                'error_code' => $failure['code'],
                'incident_id' => $failure['incident_id'],
            ], $status);
        });
    })->create();
