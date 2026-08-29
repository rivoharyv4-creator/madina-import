<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $module ??= (string) $request->route('module');
        abort_unless($request->user()?->active && $request->user()->canAccessModule($module), 403, 'Vous n’avez pas accès à ce module.');

        return $next($request);
    }
}
