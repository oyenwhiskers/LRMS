<?php

use App\Http\Middleware\EnsureAccountIsApproved;
use App\Http\Middleware\EnsureAiTokenUsageUnlocked;
use App\Http\Middleware\EnsureEntryAccess;
use App\Http\Middleware\RedirectEntryOperators;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->alias([
            'approved' => EnsureAccountIsApproved::class,
            'ai_usage' => EnsureAiTokenUsageUnlocked::class,
            'entry' => EnsureEntryAccess::class,
            'not_entry' => RedirectEntryOperators::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('entry', 'entry/*')
            ? route('entry.login')
            : route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            return ($user && $user->isEntry())
                ? route('entry.home')
                : route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
