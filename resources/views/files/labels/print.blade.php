<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $file->reference_number }} · {{ strtoupper($type) }} Label</title>
    <style>
        @include('files.labels.styles', ['printDocument' => true])

        body {
            margin: 0;
            padding: {{ isset($pdf) ? '0' : '16px' }};
            background: {{ isset($pdf) ? '#fff' : '#f5f5f4' }};
        }
    </style>
</head>
<body>
@unless(isset($pdf))
    <div class="file-label-actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('files.label.pdf', [$file, 'type' => $type]) }}">Download PDF</a>
        <a href="{{ route('files.show', $file) }}">Back</a>
    </div>
@endunless

@foreach($types as $labelType)
    @include($labelType === 'side' ? 'files.labels.side' : 'files.labels.front', [
        'file' => $file,
        'qr' => $qr,
    ])
@endforeach
</body>
</html>
