<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $rack->code }} {{ $kind === 'rack' ? 'Rack Sign' : 'Shelf Labels' }}</title>
    <style>
        @page { size: {{ $kind === 'rack' ? 'A4 portrait' : '80mm 40mm' }}; margin: 0; }
        body { margin: 0; color: #172b3a; font-family: Arial, Helvetica, sans-serif; background: {{ $pdf || request()->boolean('embed') ? '#fff' : '#f3f5f6' }}; }
        .actions { padding: 16px; }
        .actions a, .actions button { margin-right: 12px; }
        .rack-sign, .shelf-label { background: #fff; }
        .rack-sign { width: 172mm; height: 259mm; padding: 18mm; }
        .rack-sign .frame { position: relative; width: 137mm; height: 224mm; border: 1.2mm solid #172b3a; padding: 16mm; }
        .rack-sign .brand { font-size: 18pt; font-weight: bold; letter-spacing: 2pt; }
        .rack-sign .rule { height: 1mm; margin-top: 9mm; background: #172b3a; }
        .rack-sign .room { margin-top: 16mm; color: #526473; font-size: 15pt; text-transform: uppercase; letter-spacing: 1pt; }
        .rack-sign .heading { margin-top: 24mm; font-size: 18pt; font-weight: bold; letter-spacing: 3pt; }
        .rack-sign .code { margin-top: 7mm; font-size: 108pt; font-weight: bold; line-height: 1.1; overflow-wrap: break-word; }
        .rack-sign .name { margin-top: 14mm; font-size: 28pt; font-weight: bold; line-height: 1.25; overflow-wrap: break-word; }
        .rack-sign .footer { position: absolute; right: 16mm; bottom: 16mm; left: 16mm; padding-top: 5mm; border-top: 0.4mm solid #bdc9d0; color: #526473; font-size: 12pt; }
        .shelf-label { position: relative; width: 80mm; height: 40mm; overflow: hidden; }
        .shelf-label__qr { position: absolute; left: 44.5mm; top: 2.5mm; display: block; width: 35mm; height: 35mm; }
        .shelf-label__code, .shelf-label__room, .shelf-label__brand { position: absolute; text-align: center; overflow: hidden; white-space: nowrap; transform: rotate(90deg); transform-origin: top left; }
        .shelf-label__code { left: 44mm; top: 1mm; width: 38mm; height: 15mm; font-size: 20pt; font-weight: bold; line-height: 15mm; }
        .shelf-label__room { left: 27mm; top: 1.5mm; width: 37mm; height: 9mm; color: #555; font-size: 9pt; font-weight: bold; line-height: 9mm; text-transform: uppercase; }
        .shelf-label__brand { left: 15mm; top: 5.5mm; width: 29mm; height: 8mm; color: #555; font-size: 8pt; font-weight: bold; letter-spacing: 0.4pt; line-height: 8mm; text-transform: uppercase; }
        @media print { .actions { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
@unless($pdf || request()->boolean('embed'))
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ $kind === 'rack' ? route('storage.racks.label.pdf', $rack) : $download }}">Download PDF</a>
        <a href="{{ route('storage.racks.show', $rack) }}">Back</a>
    </div>
@endunless
@if($kind === 'rack')
    <div class="rack-sign">
        <div class="frame">
            <div class="brand">TSANG &amp; CO.</div>
            <div class="rule"></div>
            <div class="room">{{ config('lrms.storage.room_name') }}</div>
            <div class="heading">RACK</div>
            <div class="code">{{ $rack->code }}</div>
            <div class="name">{{ $rack->name }}</div>
            <div class="footer">Records storage &nbsp;|&nbsp; Check the shelf label for the exact location</div>
        </div>
    </div>
@else
    @foreach($labels as $label)
        <div @if(! $loop->first) style="page-break-before: always" @endif>
            @include('storage.labels.partials.shelf-sticker')
        </div>
    @endforeach
@endif
</body>
</html>
