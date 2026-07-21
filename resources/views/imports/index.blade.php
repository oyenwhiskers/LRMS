@extends('layouts.app')

@section('title', 'Excel Import & Export')

@section('content')
<div><p class="eyebrow text-amber-700">Data exchange</p><h1 class="mt-1 text-3xl font-semibold">Excel import and export</h1></div>
<div class="mt-8 grid gap-6 lg:grid-cols-2">
    @can('imports.create')
    <section class="border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Import existing registry</h2><p class="mt-2 text-sm text-stone-500">Download the template, populate it, then preview validation results before importing.</p>
        <a class="mt-5 inline-block text-sm font-semibold text-amber-800 underline" href="{{ route('imports.template') }}">Download import template</a>
        <form class="mt-5" method="POST" enctype="multipart/form-data" action="{{ route('imports.preview') }}">@csrf<label><span class="form-label">Excel workbook</span><input class="form-input" type="file" name="workbook" accept=".xlsx,.xls,.csv" required></label><button class="btn-primary mt-4">Validate and preview</button></form>
    </section>
    @endcan
    <section class="border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Export records</h2><p class="mt-2 text-sm text-stone-500">Exports are generated from the current database and do not contain passwords or QR payloads.</p><div class="mt-5 flex flex-wrap gap-3">@can('exports.files')<a class="btn-secondary" href="{{ route('exports.files') }}">Export file registry</a>@endcan @can('exports.movements')<a class="btn-secondary" href="{{ route('exports.movements') }}">Export movements</a>@endcan</div></section>
</div>

@isset($preview)
<section class="mt-8 border border-stone-200 bg-white p-6"><div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Import preview</h2><span class="status-badge {{ empty($previewErrors) ? 'status-approved' : 'status-rejected' }}">{{ $total }} rows</span></div>
    @if($previewErrors)<div class="mt-5 border-l-4 border-red-700 bg-red-50 p-4"><strong>Import cannot continue</strong><ul class="mt-2 list-disc pl-5 text-sm">@foreach(array_slice($previewErrors, 0, 25) as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @else<form class="mt-5" method="POST" action="{{ route('imports.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn-primary">Confirm import</button></form>@endif
    <div class="mt-5 overflow-x-auto"><table class="data-table"><thead><tr>@foreach(array_keys($preview->first() ?? []) as $heading)<th>{{ str($heading)->headline() }}</th>@endforeach</tr></thead><tbody>@foreach($preview as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></div>
</section>
@endisset

<section class="mt-8"><h2 class="text-xl font-semibold">Import audit</h2><div class="mt-4 overflow-x-auto border border-stone-200 bg-white"><table class="data-table"><thead><tr><th>Workbook</th><th>Status</th><th>Rows</th><th>Uploaded by</th><th>Date</th></tr></thead><tbody>@forelse($runs as $run)<tr><td>{{ $run->original_filename }}</td><td>{{ ucfirst($run->status) }} @if($run->errors)<small><a class="font-semibold text-red-700 underline" href="{{ route('imports.errors', $run) }}">Download errors</a></small>@endif</td><td>{{ $run->imported_rows }} imported / {{ $run->failed_rows }} failed</td><td>{{ $run->creator?->name }}</td><td>{{ $run->created_at->format('d M Y, H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-stone-500">No imports recorded.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $runs->links() }}</div></section>
@endsection
