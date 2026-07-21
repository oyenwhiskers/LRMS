@extends('layouts.app')

@section('title', ucfirst($type).' Files')

@section('content')
<div class="mx-auto max-w-4xl">
    <p class="eyebrow text-amber-700">File movement</p><h1 class="mt-1 text-3xl font-semibold">{{ ucfirst($type) }} physical file{{ $type === 'borrow' ? 's' : '' }}</h1>
    <p class="mt-2 text-sm text-stone-600">{{ $type === 'borrow' ? 'Scan the borrower, then scan one or more available files.' : 'Scan the actual returner, then scan every file being returned.' }}</p>

    <form class="mt-8 space-y-6" method="POST" action="{{ $type === 'borrow' ? route('movements.borrow.store') : route('movements.return.store') }}">@csrf
        <section class="scan-card">
            <div class="scan-number">1</div><div class="flex-1"><label class="form-label" for="employee_qr">{{ $type === 'borrow' ? 'Borrower' : 'Actual returner' }} employee QR</label>
                <div class="flex flex-col gap-3 sm:flex-row"><input id="employee_qr" class="form-input flex-1 font-mono" name="employee_qr" value="{{ old('employee_qr') }}" required autocomplete="off" autofocus><button class="scan-button" type="button" data-scan-target="employee_qr">Scan employee</button></div>
            </div>
        </section>
        <section class="scan-card">
            <div class="scan-number">2</div><div class="flex-1"><label class="form-label" for="file_qrs">File QR {{ $type === 'borrow' ? 'codes' : 'code(s)' }}</label>
                <div class="flex flex-col gap-3 sm:flex-row"><textarea id="file_qrs" class="form-input min-h-32 flex-1 font-mono" name="file_qrs" required placeholder="Each scan appears on a new line">{{ old('file_qrs') }}</textarea><button class="scan-button" type="button" data-scan-target="file_qrs">Scan file</button></div>
                <p class="form-help">Camera, USB and Bluetooth QR scanners are supported. Duplicate scans are ignored.</p>
            </div>
        </section>
        <div id="qr-reader" class="hidden overflow-hidden border border-amber-500 bg-white"></div><p id="scanner-status" class="text-center text-sm font-medium text-stone-600" aria-live="polite"></p>
        <button class="btn-primary min-h-14 w-full text-sm">{{ $type === 'borrow' ? 'Record borrow' : 'Record return and show location' }}</button>
    </form>
</div>
@endsection
