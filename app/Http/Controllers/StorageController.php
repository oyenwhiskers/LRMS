<?php

namespace App\Http\Controllers;

use App\Models\Cabinet;
use App\Models\Room;
use App\Models\Shelf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('storage.view'), 403);
        $rooms = Room::with('cabinets.shelves')->orderBy('code')->get();

        return view('storage.index', compact('rooms'));
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        Room::create($request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:rooms,code'],
            'name' => ['required', 'string', 'max:100'],
        ]));

        return back()->with('success', 'Room created.');
    }

    public function storeCabinet(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('cabinets')->where('room_id', $request->integer('room_id'))],
            'name' => ['required', 'string', 'max:100'],
        ]);
        abort_unless(Room::findOrFail($data['room_id'])->is_active, 422, 'The selected room is inactive.');
        Cabinet::create($data);

        return back()->with('success', 'Cabinet created.');
    }

    public function storeShelf(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $data = $request->validate([
            'cabinet_id' => ['required', 'exists:cabinets,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('shelves')->where('cabinet_id', $request->integer('cabinet_id'))],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $cabinet = Cabinet::with('room')->findOrFail($data['cabinet_id']);
        abort_unless($cabinet->is_active && $cabinet->room->is_active, 422, 'The selected cabinet or room is inactive.');
        Shelf::create($data);

        return back()->with('success', 'Shelf created.');
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $room->update($request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('rooms')->ignore($room)],
            'name' => ['required', 'string', 'max:100'],
        ]));

        return back()->with('success', 'Room updated.');
    }

    public function updateCabinet(Request $request, Cabinet $cabinet): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $cabinet->update($request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('cabinets')->where('room_id', $cabinet->room_id)->ignore($cabinet)],
            'name' => ['required', 'string', 'max:100'],
        ]));

        return back()->with('success', 'Cabinet updated.');
    }

    public function updateShelf(Request $request, Shelf $shelf): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $shelf->update($request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('shelves')->where('cabinet_id', $shelf->cabinet_id)->ignore($shelf)],
            'name' => ['required', 'string', 'max:100'],
        ]));

        return back()->with('success', 'Shelf updated.');
    }

    public function toggleRoom(Request $request, Room $room): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        if ($room->is_active && $room->cabinets()->where('is_active', true)->exists()) {
            return back()->withErrors(['room' => 'Deactivate active cabinets before deactivating this room.']);
        }
        $room->update(['is_active' => ! $room->is_active]);

        return back()->with('success', 'Room status updated.');
    }

    public function toggleCabinet(Request $request, Cabinet $cabinet): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        if ($cabinet->is_active && $cabinet->shelves()->where('is_active', true)->exists()) {
            return back()->withErrors(['cabinet' => 'Deactivate active shelves before deactivating this cabinet.']);
        }
        if (! $cabinet->is_active && ! $cabinet->room->is_active) {
            return back()->withErrors(['cabinet' => 'Activate the parent room first.']);
        }
        $cabinet->update(['is_active' => ! $cabinet->is_active]);

        return back()->with('success', 'Cabinet status updated.');
    }

    public function toggleShelf(Request $request, Shelf $shelf): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        if ($shelf->is_active && $shelf->legalFiles()->active()->exists()) {
            return back()->withErrors(['shelf' => 'Move active files before deactivating this shelf.']);
        }
        if (! $shelf->is_active && (! $shelf->cabinet->is_active || ! $shelf->cabinet->room->is_active)) {
            return back()->withErrors(['shelf' => 'Activate the parent room and cabinet first.']);
        }
        $shelf->update(['is_active' => ! $shelf->is_active]);

        return back()->with('success', 'Shelf status updated.');
    }
}
