<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Staff;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('staff.view'), 403);
        $staff = Staff::query()->with(['position', 'user'])->orderBy('full_name')->paginate(20);

        return view('staff.index', compact('staff'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('staff.create'), 403);

        return view('staff.form', ['staffMember' => new Staff, 'positions' => Position::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('staff.create'), 403);
        Staff::create($this->validated($request));

        return redirect()->route('staff.index')->with('success', 'Staff record created.');
    }

    public function edit(Request $request, Staff $staff): View
    {
        abort_unless($request->user()->can('staff.update'), 403);

        return view('staff.form', ['staffMember' => $staff, 'positions' => Position::orderBy('name')->get()]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()->can('staff.update'), 403);
        $staff->update($this->validated($request, $staff));

        return redirect()->route('staff.index')->with('success', 'Staff record updated.');
    }

    public function toggle(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()->can('staff.deactivate'), 403);
        if ($staff->is_active && $staff->heldFiles()->exists()) {
            return back()->withErrors(['staff' => 'Return or reassign held files before deactivating this employee.']);
        }
        $staff->update(['is_active' => ! $staff->is_active]);

        return back()->with('success', $staff->is_active ? 'Staff activated.' : 'Staff deactivated.');
    }

    public function qr(Request $request, Staff $staff): View
    {
        abort_unless($request->user()->can('staff.qr_print'), 403);
        $qr = (new SvgWriter)->write(new QrCode(data: $staff->qr_identifier))->getDataUri();

        return view('staff.qr', compact('staff', 'qr'));
    }

    public function lookup(Request $request, string $identifier): JsonResponse
    {
        abort_unless($request->user()->can('movements.borrow') || $request->user()->can('movements.return'), 403);
        $staff = Staff::with('position')->where('qr_identifier', $identifier)->firstOrFail();

        return response()->json([
            'staff_number' => $staff->staff_number,
            'full_name' => $staff->full_name,
            'position' => $staff->position?->name,
            'is_active' => $staff->is_active,
        ]);
    }

    private function validated(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'staff_number' => ['required', 'string', 'max:50', 'unique:staff,staff_number'.($staff ? ','.$staff->id : '')],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
