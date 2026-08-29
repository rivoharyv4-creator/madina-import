<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Hide private management routes instead of revealing the login URL.
     */
    protected function redirectTo(Request $request)
    {
        abort(404);
    }
}
