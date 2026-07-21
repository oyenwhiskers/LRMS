@extends('layouts.app')

@section('title', 'Dashboard')

@section('hero')
<section class="border-b border-amber-400/20 bg-neutral-950 text-white">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <p class="eyebrow">Workspace</p>
        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-semibold">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1>
                <p class="mt-2 text-sm text-stone-400">{{ auth()->user()->isAdmin() ? 'System Administrator' : ($position?->name ?? 'Position awaiting assignment') }}</p>
            </div>
            <p class="text-xs uppercase tracking-[0.14em] text-stone-500">{{ now()->format('l, d F Y') }}</p>
        </div>
    </div>
</section>
@endsection

@section('content')
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach(['total'=>'Total Files','available'=>'Available','borrowed'=>'Borrowed','missing'=>'Missing'] as $key=>$label)
    <a class="border border-stone-200 bg-white p-5 transition hover:border-amber-500" href="{{ route('files.index', $key === 'total' ? [] : ['status'=>$key]) }}"><p class="form-label">{{ $label }}</p><p class="font-display text-3xl font-semibold">{{ $counts[$key] }}</p></a>
    @endforeach
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="border border-stone-200 bg-white p-6 lg:col-span-2">
        <p class="eyebrow text-amber-700">Recent activity</p><h2 class="mt-1 text-xl font-semibold">Physical file movements</h2>
        <div class="mt-5 divide-y divide-stone-200">@forelse($activities as $activity)<div class="flex items-start justify-between gap-4 py-4"><div><p class="text-sm"><strong>{{ $activity->employee?->full_name ?? 'System' }}</strong> {{ $activity->type }} <a class="font-semibold text-amber-800" href="{{ $activity->legalFile ? route('files.show', $activity->legalFile) : '#' }}">{{ $activity->legalFile?->reference_number ?? 'a file' }}</a></p><p class="mt-1 text-xs text-stone-500">Processed by {{ $activity->processor?->name }}</p></div><time class="whitespace-nowrap text-xs text-stone-500">{{ $activity->occurred_at?->diffForHumans() }}</time></div>@empty<p class="py-8 text-center text-sm text-stone-500">No file activity yet.</p>@endforelse</div>
    </section>

    <aside class="border border-stone-200 bg-stone-100 p-6">
        <p class="eyebrow text-stone-600">Account</p>
        <dl class="mt-5 space-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-stone-500">Status</dt><dd class="mt-1 font-semibold text-emerald-700">Approved</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-stone-500">Role</dt><dd class="mt-1 font-semibold">{{ ucfirst(auth()->user()->role) }}</dd></div>
            @if(!auth()->user()->isAdmin())
                <div><dt class="text-xs uppercase tracking-wider text-stone-500">Staff number</dt><dd class="mt-1 font-semibold">{{ auth()->user()->staff?->staff_number }}</dd></div>
            @endif
        </dl>
    </aside>
</div>
<section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @can('movements.borrow')<a class="module-card" href="{{ route('movements.borrow') }}"><span class="module-icon">BQ</span><span><strong>Borrow files</strong><small>Scan employee and file QR</small></span></a>@endcan
    @can('movements.return')<a class="module-card" href="{{ route('movements.return') }}"><span class="module-icon">RQ</span><span><strong>Return files</strong><small>Record return and location</small></span></a>@endcan
    @can('files.create')<a class="module-card" href="{{ route('files.create') }}"><span class="module-icon">FI</span><span><strong>Register file</strong><small>Create identity and label</small></span></a>@endcan
    @can('files.view')<a class="module-card" href="{{ route('files.index') }}"><span class="module-icon">FS</span><span><strong>Search files</strong><small>Find status and holder</small></span></a>@endcan
</section>
@endsection
