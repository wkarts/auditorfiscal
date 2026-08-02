<?php
use App\Console\Commands\DeclareRabbitMqQueues;
use App\Http\Middleware\AuditRequest;
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
    ->withCommands([DeclareRabbitMqQueues::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [AuditRequest::class]);
        $middleware->alias(['role' => \Spatie\Permission\Middleware\RoleMiddleware::class, 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => request()->is('api/*'));
    })->create();
