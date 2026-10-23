<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectEntryOperators
{
    /**
     * Keep entry operators inside the /entry mini-app.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isEntry()) {
            return redirect()->route('entry.home');
        }

        return $next($request);
    }
}
