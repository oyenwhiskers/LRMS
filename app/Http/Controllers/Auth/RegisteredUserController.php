<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Registration\RegisterApplicant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.gateway', [
            'authMode' => 'register',
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(RegisterRequest $request, RegisterApplicant $register): RedirectResponse
    {
        $user = $register->handle($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.status');
    }
}
