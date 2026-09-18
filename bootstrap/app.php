<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureBackOfficeAccount;
use App\Http\Middleware\EnsureCustomerAccount;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->role === 'customer'
            ? route('customer.orders.index')
            : route('dashboard'));
        $middleware->alias([
            'auth' => Authenticate::class,
            'customer.account' => EnsureCustomerAccount::class,
            'backoffice.account' => EnsureBackOfficeAccount::class,
            'super.admin' => EnsureSuperAdmin::class,
            'module.access' => EnsureModuleAccess::class,
        ]);

        $middleware->web(append: [
            AddSecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
