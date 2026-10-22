<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Shelf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorageController extends Controller
{
    public function lookupShelf(Request $request, string $identifier): JsonResponse
    {
        abort_unless($request->user()->can('movements.pic_intake'), 403);

        $shelf = Shelf::with('rack')->where('qr_identifier', $identifier)->firstOrFail();
        abort_unless($shelf->is_active && $shelf->rack?->is_active, 404);

        return response()->json(['location' => $shelf->rack->code.'/'.$shelf->code]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('storage.view'), 403);

        $query = Rack::withCount('shelves')
            ->withCount(['shelves as active_shelves_count' => fn ($builder) => $builder->where('is_active', true)]);

        if ($search = trim((string) $request->input('search'))) {
            $contains = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function ($builder) use ($contains): void {
                $builder->where('code', 'like', $contains)
                    ->orWhere('name', 'like', $contains);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $racks = $query->orderBy('code')->get();

        return view('storage.index', compact('racks'));
    }

    public function show(Request $request, Rack $rack): View
    {
        abort_unless($request->user()->can('storage.view'), 403);
        $rack->load(['shelves' => fn ($query) => $query->orderBy('code')]);

        return view('storage.show', compact('rack'));
    }

    public function storeRack(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        Rack::create($request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:racks,code'],
            'name' => ['required', 'string', 'max:100'],
        ]));

        return back()->with('success', 'Rack created.');
    }

    public function updateRack(Request $request, Rack $rack): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('racks')->ignore($rack)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($rack->is_active && ! $data['is_active'] && $rack->shelves()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => 'Deactivate active shelves before deactivating this rack.'])->withInput();
        }

        $rack->update($data);

        return back()->with('success', 'Rack updated.');
    }

    public function toggleRack(Request $request, Rack $rack): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        if ($rack->is_active && $rack->shelves()->where('is_active', true)->exists()) {
            return back()->withErrors(['rack' => 'Deactivate active shelves before deactivating this rack.']);
        }
        $rack->update(['is_active' => ! $rack->is_active]);

        return back()->with('success', 'Rack status updated.');
    }

    public function destroyRack(Request $request, Rack $rack): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);

        try {
            $deleted = DB::transaction(function () use ($rack): bool {
                $rack = Rack::query()->lockForUpdate()->findOrFail($rack->id);
                $shelves = $rack->shelves()->lockForUpdate()->get();
                $shelfIds = $shelves->modelKeys();

                if ($shelfIds !== [] && $this->shelvesHaveReferences($shelfIds)) {
                    return false;
                }

                foreach ($shelves as $shelf) {
                    $shelf->delete();
                }
                $rack->delete();

                return true;
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['19', '23000', '23503'], true)) {
                throw $exception;
            }

            return back()->withErrors(['rack' => 'This rack has linked files or history and cannot be deleted.']);
        }

        if (! $deleted) {
            return back()->withErrors(['rack' => 'This rack has shelves linked to files or movement history. Move the files and keep the historical shelves.']);
        }

        return redirect()->route('storage.index')->with('success', 'Rack and its shelves deleted.');
    }

    public function storeShelf(Request $request, Rack $rack): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        abort_unless($rack->is_active, 422, 'The selected rack is inactive.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('shelves')->where('rack_id', $rack->id)],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $rack->shelves()->create($data);

        return back()->with('success', 'Shelf created.');
    }

    public function updateShelf(Request $request, Shelf $shelf): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('shelves')->where('rack_id', $shelf->rack_id)->ignore($shelf)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($shelf->is_active && ! $data['is_active'] && $shelf->legalFiles()->active()->exists()) {
            return back()->withErrors(['is_active' => 'Move active files before deactivating this shelf.'])->withInput();
        }
        if (! $shelf->is_active && $data['is_active'] && ! $shelf->rack->is_active) {
            return back()->withErrors(['is_active' => 'Activate the parent rack first.'])->withInput();
        }

        $shelf->update($data);

        return back()->with('success', 'Shelf updated.');
    }

    public function toggleShelf(Request $request, Shelf $shelf): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);
        if ($shelf->is_active && $shelf->legalFiles()->active()->exists()) {
            return back()->withErrors(['shelf' => 'Move active files before deactivating this shelf.']);
        }
        if (! $shelf->is_active && ! $shelf->rack->is_active) {
            return back()->withErrors(['shelf' => 'Activate the parent rack first.']);
        }
        $shelf->update(['is_active' => ! $shelf->is_active]);

        return back()->with('success', 'Shelf status updated.');
    }

    public function destroyShelf(Request $request, Shelf $shelf): RedirectResponse
    {
        abort_unless($request->user()->can('storage.manage'), 403);

        try {
            $deleted = DB::transaction(function () use ($shelf): bool {
                $shelf = Shelf::query()->lockForUpdate()->findOrFail($shelf->id);

                if ($this->shelvesHaveReferences([$shelf->id])) {
                    return false;
                }

                $shelf->delete();

                return true;
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['19', '23000', '23503'], true)) {
                throw $exception;
            }

            return back()->withErrors(['shelf' => 'This shelf has linked files or history and cannot be deleted.']);
        }

        if (! $deleted) {
            return back()->withErrors(['shelf' => 'This shelf is linked to files or movement history. Move the files and keep the historical shelf.']);
        }

        return back()->with('success', 'Shelf deleted.');
    }

    private function shelvesHaveReferences(array $shelfIds): bool
    {
        return DB::table('legal_files')->whereIn('shelf_id', $shelfIds)->exists()
            || DB::table('file_movements')->whereIn('shelf_id', $shelfIds)->exists();
    }
}
