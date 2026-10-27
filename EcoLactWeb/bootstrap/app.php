<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IdempotenciaApi;
use App\Http\Middleware\RequiereAsistencia;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Support\SesionWeb;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => app(SesionWeb::class)->redirigir($request)->getTargetUrl());

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'idempotente' => IdempotenciaApi::class,
            // Personal sin asistencia válida: consulta sí, opera no (ver EstadoAsistenciaActual).
            'requiere.asistencia' => RequiereAsistencia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
