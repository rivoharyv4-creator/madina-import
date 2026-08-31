<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Return expired or unauthenticated management sessions to the private login page.
     */
    protected function redirectTo(Request $request)
    {
        return route('login', absolute: false);
    }
}
