@extends('layouts.app')

@section('title', 'Movement History')

@section('content')
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow text-amber-700">Audit trail</p><h1 class="mt-1 text-3xl font-semibold">File movement history</h1></div>@can('exports.movements')<a class="btn-secondary" href="{{ route('exports.movements') }}">Export Excel</a>@endcan</div>
<div class="mt-8 overflow-x-auto border border-stone-200 bg-white"><table class="data-table"><thead><tr><th>Date and time</th><th>File</th><th>Movement</th><th>Employee / returner</th><th>Previous holder</th><th>Location</th><th>Processed by</th></tr></thead><tbody>
@forelse($movements as $movement)<tr>
    <td>{{ $movement->occurred_at?->format('d M Y, H:i:s') }}</td><td>@if($movement->legalFile)<a class="font-semibold text-amber-800" href="{{ route('files.show', $movement->legalFile) }}">{{ $movement->legalFile->reference_number }}</a>@else — @endif</td>
    <td><strong>{{ ucfirst($movement->type) }}</strong><small>{{ ucfirst($movement->previous_status ?? 'none') }} → {{ ucfirst($movement->new_status) }}</small></td><td>{{ $movement->employee?->full_name ?? '—' }}</td><td>{{ $movement->previousHolder?->full_name ?? '—' }}</td><td>{{ $movement->shelf?->full_location ?? '—' }}</td><td>{{ $movement->processor?->name }}</td>
</tr>@empty<tr><td colspan="7" class="py-12 text-center text-stone-500">No movements recorded.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $movements->links() }}</div>
@endsection
