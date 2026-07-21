@extends('layouts.app')

@section('title', 'Positions')

@section('content')
<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="eyebrow text-amber-700">Access control</p>
        <h1 class="mt-2 text-3xl font-semibold">Positions & permissions</h1>
        <p class="mt-2 text-sm text-stone-600">Configure one authoritative position and action-level access set for each staff member.</p>
    </div>
    @can('create', App\Models\Position::class)
        <a class="btn-primary" href="{{ route('admin.positions.create') }}">Create position</a>
    @endcan
</div>

<div class="overflow-hidden border border-stone-200 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-180 text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-100 text-xs uppercase tracking-[0.12em] text-stone-600">
                <tr><th class="px-5 py-4">Position</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Permissions</th><th class="px-5 py-4">Staff</th><th class="px-5 py-4 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse($positions as $position)
                    <tr>
                        <td class="px-5 py-4"><strong class="block">{{ $position->name }}</strong><span class="text-xs text-stone-500">{{ $position->slug }}</span></td>
                        <td class="px-5 py-4"><span class="status-badge {{ $position->is_active ? 'status-approved' : 'status-rejected' }}">{{ $position->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="px-5 py-4">{{ $position->permissions_count }}</td>
                        <td class="px-5 py-4">{{ $position->staff_count }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-3">
                                @can('update', $position)
                                    <a class="font-semibold text-amber-800 hover:text-amber-700" href="{{ route('admin.positions.edit', $position) }}">Edit</a>
                                @endcan
                                @can('delete', $position)
                                    @if($position->is_active)<form method="POST" action="{{ route('admin.positions.destroy', $position) }}" onsubmit="return confirm('Deactivate this position? Assigned users will lose its permissions.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-semibold text-red-700 hover:text-red-600" type="submit">Deactivate</button>
                                    </form>@endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-10 text-center text-stone-500" colspan="5">No positions configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-6">{{ $positions->links() }}</div>
@endsection
