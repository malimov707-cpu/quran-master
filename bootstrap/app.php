<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdministration;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\SetInterfaceLocale;
use App\Http\Middleware\VerifyPaymeAuth;
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
        $middleware->web(append: [SetInterfaceLocale::class]);
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'active-account' => EnsureActiveAccount::class,
            'administration' => EnsureAdministration::class,
            'payme.auth' => VerifyPaymeAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
