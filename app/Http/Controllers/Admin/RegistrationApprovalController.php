<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Registration\ReviewRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRegistrationRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('registrations.view');

        $isFeed = $request->boolean('feed');
        $perPage = $isFeed ? 10 : 15;
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

        $registrations = $query->paginate($perPage)->withQueryString();
        $positions = Position::query()->where('is_active', true)->orderBy('name')->get();

        if ($isFeed) {
            return response()->json([
                'html' => view('admin.registrations.partials.registration-cards', [
                    'registrations' => $registrations,
                ])->render(),
                'modals' => view('admin.registrations.partials.registration-modals', [
                    'registrations' => $registrations,
                    'positions' => $positions,
                ])->render(),
                'next_page' => $registrations->hasMorePages() ? $registrations->currentPage() + 1 : null,
            ]);
        }

        return view('admin.registrations.index', [
            'registrations' => $registrations,
            'positions' => $positions,
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
