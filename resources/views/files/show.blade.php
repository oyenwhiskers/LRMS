@extends('layouts.app')

@section('title', $file->reference_number)

@section('content')
<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div><p class="eyebrow text-amber-700">{{ $file->file_identifier }}</p><h1 class="mt-1 text-3xl font-semibold">{{ $file->reference_number }}</h1><p class="mt-2 text-stone-600">{{ $file->purchaser }}</p></div>
    <div class="flex flex-wrap gap-2">@can('files.update')<a class="btn-secondary" href="{{ route('files.edit', $file) }}">Edit</a>@endcan @can('labels.print')<a class="btn-primary" href="{{ route('files.label', $file) }}">Print label</a>@endcan</div>
</div>
<div class="mt-8 grid gap-6 lg:grid-cols-3">
    <section class="border border-stone-200 bg-white p-6 lg:col-span-2"><h2 class="text-xl font-semibold">File information</h2>
        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            @foreach(['Loan reference'=>$file->loan_reference,'Vendor'=>$file->vendor,'Property'=>$file->property,'Matter type'=>$file->matter_type,'Important date'=>$file->important_date?->format('d M Y'),'Person in charge'=>$file->personInCharge?->full_name] as $label=>$value)
            <div><dt class="form-label">{{ $label }}</dt><dd class="text-sm font-medium">{{ $value ?: '—' }}</dd></div>@endforeach
        </dl>
    </section>
    <aside class="border border-stone-200 bg-stone-100 p-6"><h2 class="text-xl font-semibold">Current state</h2>
        <dl class="mt-6 space-y-5"><div><dt class="form-label">Status</dt><dd><span class="status-badge status-{{ $file->status === 'available' ? 'approved' : ($file->status === 'missing' ? 'rejected' : 'pending') }}">{{ ucfirst($file->status) }}</span></dd></div><div><dt class="form-label">Current holder</dt><dd class="font-semibold">{{ $file->currentHolder?->full_name ?? 'None' }}</dd></div><div><dt class="form-label">Storage location</dt><dd class="font-semibold">{{ $file->shelf?->full_location ?? 'Unassigned' }}</dd></div></dl>
        <div class="mt-6 space-y-3">
            @can('missing.manage')
                @if($file->status !== 'missing')<form method="POST" action="{{ route('files.missing', $file) }}">@csrf @method('PATCH')<textarea class="form-input" name="notes" placeholder="Reason or circumstances" required></textarea><button class="btn-danger mt-2 w-full">Mark missing</button></form>
                @else<form method="POST" action="{{ route('files.found', $file) }}">@csrf @method('PATCH')<input class="form-input" name="notes" placeholder="Found notes"><button class="btn-primary mt-2 w-full">Mark found</button></form>@endif
            @endcan
            @can('files.archive')<form method="POST" action="{{ route('files.archive', $file) }}">@csrf @method('PATCH')<button class="btn-secondary w-full">Archive file</button></form>@endcan
        </div>
    </aside>
</div>
<section class="mt-8 border border-stone-200 bg-white p-6"><div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Movement history</h2>@can('exports.movements')<a class="text-sm font-semibold text-amber-800" href="{{ route('exports.movements') }}">Export history</a>@endcan</div>
    <div class="mt-5 overflow-x-auto"><table class="data-table"><thead><tr><th>When</th><th>Movement</th><th>Employee / returner</th><th>Previous holder</th><th>Processed by</th><th>Notes</th></tr></thead><tbody>
    @forelse($file->movements as $movement)<tr><td>{{ $movement->occurred_at?->format('d M Y, H:i') }}</td><td>{{ ucfirst($movement->type) }}</td><td>{{ $movement->employee?->full_name ?? '—' }}</td><td>{{ $movement->previousHolder?->full_name ?? '—' }}</td><td>{{ $movement->processor?->name }}</td><td>{{ $movement->notes ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-stone-500">No movement recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
