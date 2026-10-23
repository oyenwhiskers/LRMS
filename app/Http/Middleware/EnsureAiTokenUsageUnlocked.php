<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiTokenUsageUnlocked
{
    public const SESSION_KEY = 'ai_token_usage_unlocked_for';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (int) $request->session()->get(self::SESSION_KEY) === (int) $user->id) {
            return $next($request);
        }

        return redirect()->route('entry.ai-usage.unlock');
    }
}
