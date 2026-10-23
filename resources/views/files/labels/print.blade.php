<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $file->reference_number }} · {{ strtoupper($type) }} Label</title>
    <style>
        @include('files.labels.styles', ['printDocument' => true])

        body {
            margin: 0;
            padding: {{ isset($pdf) ? '0' : (! empty($embed) ? '0' : '16px') }};
            background: {{ isset($pdf) ? '#fff' : '#f5f5f4' }};
        }

        @if (! empty($embed))
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            display: grid;
            place-items: center;
        }

        .label-embed-stage {
            position: relative;
            overflow: hidden;
            line-height: 0;
        }

        .label-embed-stage .file-label {
            transform-origin: top left;
        }
        @endif
    </style>
</head>
<body>
@unless(isset($pdf) || ! empty($embed))
    <div class="file-label-actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('files.label.pdf', [$file, 'type' => $type]) }}">Download PDF</a>
        <a href="{{ route('files.show', $file) }}">Back</a>
    </div>
@endunless

@if (! empty($embed))
    <div class="label-embed-stage" data-label-embed-stage>
@endif
@foreach($types as $labelType)
    @include($labelType === 'side' ? 'files.labels.side' : 'files.labels.front', [
        'file' => $file,
        'qr' => $qr,
    ])
@endforeach
@if (! empty($embed))
    </div>
    <script>
        (() => {
            const stage = document.querySelector('[data-label-embed-stage]');
            const label = stage?.querySelector('.file-label');
            if (! stage || ! label) {
                return;
            }

            const fit = () => {
                label.style.transform = 'none';
                const naturalWidth = label.offsetWidth;
                const naturalHeight = label.offsetHeight;
                if (! naturalWidth || ! naturalHeight) {
                    return;
                }

                const pad = 12;
                const scale = Math.min(
                    (window.innerWidth - pad) / naturalWidth,
                    (window.innerHeight - pad) / naturalHeight,
                    1,
                );

                label.style.transform = `scale(${scale})`;
                stage.style.width = `${Math.ceil(naturalWidth * scale)}px`;
                stage.style.height = `${Math.ceil(naturalHeight * scale)}px`;
            };

            fit();
            window.addEventListener('resize', fit);
        })();
    </script>
@endif
</body>
</html>
