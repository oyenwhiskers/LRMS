<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Registration\ReviewRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRegistrationRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('registrations.view');

        $query = User::query()
            ->where('role', User::ROLE_USER)
            ->with(['staff', 'requestedPosition', 'reviewer']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('staff', fn ($staff) => $staff->where('staff_number', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort') === 'oldest' ? 'oldest' : 'latest';
        $query->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc');

        return view('admin.registrations.index', [
            'registrations' => $query->paginate(15)->withQueryString(),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
            'pendingCount' => User::query()
                ->where('role', User::ROLE_USER)
                ->where('status', User::STATUS_PENDING)
                ->count(),
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
