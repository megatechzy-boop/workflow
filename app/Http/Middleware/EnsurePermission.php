<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Usage: ->middleware('permission:manage_clients')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermission(Permission::from($permission))) {
            abort(403, 'Aapko is page ka access nahi hai.');
        }

        return $next($request);
    }
}
