<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCustomerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'customer') {
            return redirect()->route('customer.orders.index');
        }

        return $next($request);
    }
}
