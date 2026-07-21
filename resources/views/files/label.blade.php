<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><title>{{ $file->reference_number }} File Label</title>
    <style>
        @page{margin:12mm}body{font-family:Arial,sans-serif;color:#111;margin:0;padding:20px}.label{position:relative;width:130mm;min-height:82mm;border:1px solid #d9d4c7;padding:10mm;box-sizing:border-box}.gold{height:3px;background:#c8a24a;margin-bottom:7mm}.brand{font-family:Georgia,serif;font-size:13px;letter-spacing:.09em;text-transform:uppercase}.row{margin-top:6mm;max-width:86mm}.key{font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.12em;margin-bottom:2mm}.value{font-size:16px;font-weight:bold;line-height:1.25}.reference{font-size:22px;font-family:Georgia,serif}.qr{position:absolute;right:9mm;bottom:9mm;text-align:center}.qr img{width:34mm;height:34mm}.qr small{display:block;font-size:8px;margin-top:1mm;color:#555}.file-id{position:absolute;left:10mm;bottom:9mm;font-size:9px;color:#555}.actions{margin-bottom:16px}.actions a,.actions button{margin-right:8px}@media print{body{padding:0}.actions{display:none}.label{border:0}}
    </style>
</head>
<body>
@unless(isset($pdf))<div class="actions"><button onclick="window.print()">Print label</button><a href="{{ route('files.label.pdf', $file) }}">Download PDF</a><a href="{{ route('files.show', $file) }}">Back</a></div>@endunless
<div class="label">
    <div class="gold"></div><div class="brand">Legal Records Management System</div>
    <div class="row"><div class="key">Reference No.</div><div class="value reference">{{ $file->reference_number }}</div></div>
    <div class="row"><div class="key">Purchaser</div><div class="value">{{ $file->purchaser }}</div></div>
    <div class="row"><div class="key">Property</div><div class="value">{{ $file->property }}</div></div>
    <div class="file-id">{{ $file->file_identifier }}</div>
    <div class="qr"><img src="{{ $qr }}" alt="File QR code"><small>Scan file identity</small></div>
</div>
</body>
</html>
