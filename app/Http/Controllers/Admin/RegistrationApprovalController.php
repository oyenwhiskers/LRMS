<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Registration\ReviewRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRegistrationRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('registrations.view');

        return view('admin.registrations.index', [
            'registrations' => User::query()
                ->where('role', User::ROLE_USER)
                ->with(['staff', 'requestedPosition', 'reviewer'])
                ->latest()
                ->paginate(15),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(
        ReviewRegistrationRequest $request,
        User $registration,
        ReviewRegistration $review,
    ): RedirectResponse {
        $review->handle($registration, $request->user(), $request->validated());

        return back()->with('success', 'Registration review saved.');
    }
}
