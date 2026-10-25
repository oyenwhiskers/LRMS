<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Stesen cetak · {{ count($labels) }} label</title>
    <style>
        @include('files.labels.styles', ['printDocument' => true, 'type' => $type])

        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .file-label + .file-label {
            page-break-before: always;
            break-before: page;
        }
    </style>
</head>
<body>
@foreach ($labels as $label)
    @include($label['type'] === 'side' ? 'files.labels.side' : 'files.labels.front', [
        'file' => $label['file'],
        'qr' => $label['qr'],
    ])
@endforeach
</body>
</html>
