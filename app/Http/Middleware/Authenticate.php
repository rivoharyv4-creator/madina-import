<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    public function handle($request, \Closure $next, ...$guards)
    {
        return parent::handle($request, function ($request) use ($next) {
            abort_unless($request->user()->active, 403);
            abort_unless(in_array($request->user()->role, ['customer', 'super_admin', 'admin', 'assistant', 'user'], true), 403);
            if ($request->user()->role === 'customer') {
                abort_unless($request->routeIs('customer.*', 'verification.*', 'logout'), 403);
            }

            return $next($request);
        }, ...$guards);
    }

    /**
     * Keep unauthenticated visitors on the public customer login route.
     */
    protected function redirectTo(Request $request)
    {
        return route('customer.login', absolute: false);
    }
}
