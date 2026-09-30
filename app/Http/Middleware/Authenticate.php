<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request)
    {
        // API requests should never redirect to a login page.
        if ($request->is('api/*')) {
            return null;
        }

        // Web requests can still redirect to login.
        if (! $request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}
