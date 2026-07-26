@extends('layouts.app')

@section('title', $file->reference_number)

@section('content')
<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div><p class="eyebrow text-amber-700">{{ $file->file_identifier }}</p><h1 class="mt-1 text-3xl font-semibold">{{ $file->reference_number }}</h1><p class="mt-2 text-stone-600">{{ $file->purchaser }}</p></div>
    <div class="flex flex-wrap gap-2">@can('files.update')<a class="btn-secondary" href="{{ route('files.edit', $file) }}">Edit</a>@endcan @can('labels.print')<button class="btn-primary" type="button" data-modal-open="file-label-{{ $file->id }}">Print label</button>@endcan</div>
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
    @forelse($movements as $movement)<tr><td>{{ $movement->occurred_at?->format('d M Y, H:i') }}</td><td>{{ ucfirst($movement->type) }}</td><td>{{ $movement->employee?->full_name ?? '—' }}</td><td>{{ $movement->previousHolder?->full_name ?? '—' }}</td><td>{{ $movement->processor?->name }}</td><td>{{ $movement->notes ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-stone-500">No movement recorded.</td></tr>@endforelse
    </tbody></table></div>
    <div class="mt-6">{{ $movements->links() }}</div>
</section>
@can('labels.print')
<div class="modal-overlay hidden" data-modal="file-label-{{ $file->id }}" aria-hidden="true">
    <div class="modal-panel max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="file-label-title-{{ $file->id }}">
        <div class="modal-header">
            <div><p class="eyebrow text-amber-700">File QR</p><h2 id="file-label-title-{{ $file->id }}" class="mt-1 text-2xl font-semibold">File identity label</h2></div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
        </div>
        @include('files.partials.label-card')
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('files.label.pdf', $file) }}">Download PDF</a>
            <a class="btn-primary" href="{{ route('files.label', $file) }}" target="_blank" rel="noopener">Print view</a>
        </div>
    </div>
</div>
@endcan
@endsection
