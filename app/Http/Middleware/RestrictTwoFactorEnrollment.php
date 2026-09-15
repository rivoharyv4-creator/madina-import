<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictTwoFactorEnrollment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! (bool) $request->session()->get('two_factor_enrollment_only', false)) {
            return $next($request);
        }

        $target = $request->route('user');
        $isOwnSetup = $request->routeIs([
            'admin.users.two-factor.show',
            'admin.users.two-factor.setup',
            'admin.users.two-factor.confirm',
        ]) && (int) ($target?->id ?? $target) === $request->user()->id;

        if ($request->user()->isSuperAdmin() && $isOwnSetup) {
            return $next($request);
        }

        return redirect()->route('admin.users.two-factor.show', $request->user());
    }
}
