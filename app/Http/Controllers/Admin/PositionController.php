<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PositionRequest;
use App\Models\Permission;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Position::class);

        return view('admin.positions.index', [
            'positions' => Position::query()
                ->withCount(['permissions', 'staff'])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        Gate::authorize('create', Position::class);

        return view('admin.positions.form', [
            'position' => new Position,
            'permissions' => Permission::query()->orderBy('module')->orderBy('action')->get()->groupBy('module'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PositionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Position::class);

        DB::transaction(function () use ($request): void {
            $position = Position::query()->create($request->safe()->except('permissions'));
            $position->permissions()->sync($request->validated('permissions', []));
        });

        return redirect()->route('admin.positions.index')->with('success', 'Position created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Position $position): View
    {
        Gate::authorize('update', $position);

        return view('admin.positions.form', [
            'position' => $position->load('permissions'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('action')->get()->groupBy('module'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        Gate::authorize('update', $position);

        DB::transaction(function () use ($request, $position): void {
            $position->update($request->safe()->except('permissions'));
            $position->permissions()->sync($request->validated('permissions', []));
        });

        return redirect()->route('admin.positions.index')->with('success', 'Position updated.');
    }

    /**
     * Deactivate the specified position while preserving history.
     */
    public function destroy(Position $position): RedirectResponse
    {
        Gate::authorize('delete', $position);
        $position->update(['is_active' => false]);

        return back()->with('success', 'Position deactivated.');
    }
}
