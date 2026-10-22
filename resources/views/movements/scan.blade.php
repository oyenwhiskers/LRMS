@extends('layouts.app')

@section('title', match ($type) {
    'borrow' => 'Borrow File',
    'store' => 'Store File',
    'return' => 'Return File',
    default => 'File Movement',
})
@if(($type ?? null) === 'store' && ! empty($returnFile))
    @section('back', route('files.show', $returnFile))
@endif

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">File movement</p>
            <h1 class="mt-1 text-3xl font-semibold">
                {{ match ($type) {
                    'borrow' => 'Borrow file',
                    'store' => 'Store file',
                    'return' => 'Return file',
                    default => 'Process file movement',
                } }}
            </h1>
            <p class="mt-2 text-sm text-stone-600">
                {{ match ($type) {
                    'borrow' => 'Scan the borrower, then scan every file they are borrowing.',
                    'store' => 'Scan a completed file that is ready for storage, then scan its shelf.',
                    'return' => 'Scan the receiver, each returned file, then the returner. Store the files separately afterward.',
                    default => 'Scan the required QR codes in order.',
                } }}
            </p>
        </div>
        @if(($type ?? null) === 'store' && ! empty($returnFile))
            <a class="btn-secondary hidden shrink-0 lg:inline-flex" href="{{ route('files.show', $returnFile) }}">Back</a>
        @endif
    </div>

    <form class="mt-8 space-y-6" method="POST" action="{{ match ($type) {
        'borrow' => route('movements.borrow.store'),
        'store' => route('movements.pic-intake.store'),
        'return' => route('movements.return.store'),
    } }}">@csrf
        @if(($type ?? null) === 'store' && ! empty($returnFile))
            <input type="hidden" name="return_file" value="{{ $returnFile->id }}">
        @endif
        @if($type === 'borrow')
            <section class="scan-card">
                <div class="scan-number">1</div><div class="scan-card__content"><label class="form-label" for="employee_qr">Borrower QR</label>
                    <div class="scan-field-group"><input id="employee_qr" class="form-input scan-input flex-1 font-mono" name="employee_qr" value="{{ old('employee_qr') }}" data-qr-kind="staff" required autocomplete="off" autofocus><button class="scan-button" type="button" data-scan-target="employee_qr">Scan staff</button></div>
                </div>
            </section>
        @endif

        @if($type === 'return')
            <section class="scan-card">
                <div class="scan-number">1</div><div class="scan-card__content"><label class="form-label" for="pic_qr">Receiver QR</label>
                    <div class="scan-field-group"><input id="pic_qr" class="form-input scan-input flex-1 font-mono" name="pic_qr" value="{{ old('pic_qr') }}" data-qr-kind="staff" required autocomplete="off" autofocus><button class="scan-button" type="button" data-scan-target="pic_qr">Scan staff</button></div>
                </div>
            </section>
        @endif

        <section class="scan-card">
            <div class="scan-number">{{ $type === 'store' ? '1' : '2' }}</div><div class="scan-card__content"><label class="form-label" for="file_qrs">File QR code(s)</label>
                <div class="scan-field-group"><textarea id="file_qrs" class="form-input scan-input scan-input--textarea flex-1 font-mono" name="file_qrs" data-qr-kind="file" required placeholder="Each scan appears on a new line">{{ old('file_qrs') }}</textarea><button class="scan-button scan-button--stacked" type="button" data-scan-target="file_qrs">Scan file</button></div>
                @error('file_qrs')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <p class="form-help">Scan all files for this {{ $type === 'borrow' ? 'borrower' : ($type === 'return' ? 'return' : 'storage batch') }}. Camera, USB, and Bluetooth scanners are supported. Duplicate scans are ignored.</p>
            </div>
        </section>

        @if($type === 'return')
            <section class="scan-card">
                <div class="scan-number">3</div><div class="scan-card__content"><label class="form-label" for="returner_qr">Returner QR</label>
                    <div class="scan-field-group"><input id="returner_qr" class="form-input scan-input flex-1 font-mono" name="returner_qr" value="{{ old('returner_qr') }}" data-qr-kind="staff" required autocomplete="off"><button class="scan-button" type="button" data-scan-target="returner_qr">Scan staff</button></div>
                </div>
            </section>
        @endif

        @if($type === 'store')
            <section class="scan-card">
                <div class="scan-number">2</div><div class="scan-card__content"><label class="form-label" for="shelf_scan">Shelf QR</label>
                    <div class="scan-field-group"><input id="shelf_scan" class="form-input scan-input flex-1" data-qr-kind="shelf" data-lookup-url="{{ route('scan.shelves', ['identifier' => '__IDENTIFIER__']) }}" data-initial-qr="{{ old('shelf_qr') }}" required autocomplete="off" placeholder="Scan shelf QR"><button class="scan-button" type="button" data-scan-target="shelf_scan">Scan shelf</button></div>
                    <input id="shelf_qr" type="hidden" name="shelf_qr" value="{{ old('shelf_qr') }}">
                    @error('shelf_qr')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>
        @endif

        <div id="qr-reader" class="hidden overflow-hidden border border-amber-500 bg-white"></div><p id="scanner-status" class="text-center text-sm font-medium text-stone-600" aria-live="polite"></p>
        <button class="btn-primary min-h-14 w-full text-sm">{{ match ($type) {
            'borrow' => 'Record borrow',
            'store' => 'Store file',
            'return' => 'Record return',
        } }}</button>
    </form>
</div>
@endsection
