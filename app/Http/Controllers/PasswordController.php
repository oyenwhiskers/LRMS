<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.password', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
