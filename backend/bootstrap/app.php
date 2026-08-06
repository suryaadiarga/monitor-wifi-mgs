<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as PermissionUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            CorrelationId::class,
            SecureHeaders::class,
        ]);
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(fn (ValidationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Validasi gagal', 'data' => null, 'meta' => (object) [], 'errors' => $exception->errors()], 422)
            : null);
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Autentikasi diperlukan', 'data' => null, 'meta' => (object) [], 'errors' => null], 401)
            : null);
        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Akses ditolak', 'data' => null, 'meta' => (object) [], 'errors' => null], 403)
            : null);
        $exceptions->render(fn (PermissionUnauthorizedException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Permission tidak mencukupi', 'data' => null, 'meta' => (object) [], 'errors' => null], 403)
            : null);
    })->create();
