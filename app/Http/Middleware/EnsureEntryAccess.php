<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntryAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isApproved()) {
            return redirect()->route('account.status');
        }

        if (! $user->isEntry() && ! $user->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
