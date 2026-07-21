@extends('layouts.app')

@section('title', 'File Registry')

@section('content')
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="eyebrow text-amber-700">Physical records</p><h1 class="mt-1 text-3xl font-semibold">File registry</h1></div>
    @can('files.create')<a class="btn-primary" href="{{ route('files.create') }}">Register file</a>@endcan
</div>
<form class="mt-7 grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_180px_auto]" method="GET">
    <input class="form-input" type="search" name="search" value="{{ request('search') }}" placeholder="Reference, purchaser, vendor, property or holder">
    <select class="form-input" name="status"><option value="">All statuses</option>@foreach(['available','borrowed','missing'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <button class="btn-primary">Search</button>
</form>
<div class="mt-6 overflow-x-auto border border-stone-200 bg-white">
    <table class="data-table"><thead><tr><th>Reference</th><th>Purchaser / Property</th><th>Location</th><th>Current holder</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($files as $file)<tr>
        <td><a class="font-semibold text-amber-800 hover:underline" href="{{ route('files.show', $file) }}">{{ $file->reference_number }}</a><small>{{ $file->file_identifier }}</small></td>
        <td><strong>{{ $file->purchaser }}</strong><small class="max-w-md truncate">{{ $file->property }}</small></td>
        <td>{{ $file->shelf?->full_location ?? 'Unassigned' }}</td><td>{{ $file->currentHolder?->full_name ?? '—' }}</td>
        <td><span class="status-badge status-{{ $file->status === 'available' ? 'approved' : ($file->status === 'missing' ? 'rejected' : 'pending') }}">{{ ucfirst($file->status) }}</span></td>
        <td class="text-right"><a class="btn-secondary" href="{{ route('files.show', $file) }}">Open</a></td>
    </tr>@empty<tr><td colspan="6" class="py-12 text-center text-stone-500">No files match this search.</td></tr>@endforelse</tbody></table>
</div>
<div class="mt-6">{{ $files->links() }}</div>
@endsection
