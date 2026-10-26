<?php

namespace App\Http\Controllers\Entry;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureManageOperatorUnlocked;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ManageOperatorController extends Controller
{
    public function unlockForm(Request $request): View|RedirectResponse
    {
        if ((int) $request->session()->get(EnsureManageOperatorUnlocked::SESSION_KEY) === (int) $request->user()->id) {
            return redirect()->route('entry.manage-operators.index');
        }

        return view('entry.manage-operators.unlock');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:100'],
        ]);

        $expected = (string) config('lrms.manage_operator_password');
        if ($expected === '' || ! hash_equals($expected, $data['password'])) {
            return back()->withErrors(['password' => 'Kata laluan salah.']);
        }

        $request->session()->put(EnsureManageOperatorUnlocked::SESSION_KEY, $request->user()->id);

        return redirect()->route('entry.manage-operators.index');
    }

    public function index(): View
    {
        $users = User::query()
            ->where('role', User::ROLE_ENTRY)
            ->orderBy('name')
            ->paginate(20);

        return view('entry.manage-operators.index', compact('users'));
    }

    public function editPassword(User $user): View
    {
        abort_unless($user->isEntry(), 404);

        return view('entry.manage-operators.password', compact('user'));
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isEntry(), 404);

        $data = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user->update([
            'password' => $data['password'],
        ]);

        return redirect()
            ->route('entry.manage-operators.index')
            ->with('success', 'Kata laluan operator dikemas kini.');
    }
}
