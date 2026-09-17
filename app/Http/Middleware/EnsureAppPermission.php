<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppPermission
{
    /**
     * Lets the request through only when the user is allowed one of the given screens of the sales application,
     * such as app.checkout. A user who is not allowed any of them is refused with 403 and a message in their language.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $allowed = $request->user()->appPermissions();

        foreach ($permissions as $permission) {
            if (in_array($permission, $allowed, true)) {
                return $next($request);
            }
        }

        abort(403, __('api.forbidden'));
    }
}
