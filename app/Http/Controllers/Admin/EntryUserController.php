<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EntryUserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $users = User::query()
            ->where('role', User::ROLE_ENTRY)
            ->orderBy('name')
            ->paginate(20);

        return view('admin.entry-users.index', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_ENTRY,
            'status' => User::STATUS_APPROVED,
            'staff_id' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.entry-users.index')
            ->with('success', 'Entry operator account created.');
    }

    public function deactivate(Request $request, User $entryUser): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($entryUser->isEntry(), 404);

        $entryUser->update([
            'status' => User::STATUS_REJECTED,
            'rejection_reason' => 'Deactivated by administrator.',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.entry-users.index')
            ->with('success', 'Entry operator deactivated.');
    }

    public function reactivate(Request $request, User $entryUser): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($entryUser->isEntry(), 404);

        $entryUser->update([
            'status' => User::STATUS_APPROVED,
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.entry-users.index')
            ->with('success', 'Entry operator reactivated.');
    }
}
