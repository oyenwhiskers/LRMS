<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EntryAuthController extends Controller
{
    public function createLogin(): View
    {
        return view('entry.auth.login');
    }

    public function storeLogin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->isEntry()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akaun ini bukan untuk Entry. Sila guna log masuk firma.',
            ]);
        }

        if (! $user->isApproved()) {
            return redirect()->route('account.status');
        }

        return redirect()->intended(route('entry.home', absolute: false));
    }

    public function createRegister(): View
    {
        return view('entry.auth.register');
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_ENTRY,
            'status' => User::STATUS_APPROVED,
            'staff_id' => null,
            'reviewed_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('entry.home');
    }
}
