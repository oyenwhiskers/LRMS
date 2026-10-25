@if(! empty($printDocument))
@page {
    size: {{ $type === 'side' ? '80mm 40mm' : '150mm 100mm' }};
    margin: 0;
}
@endif

.file-label-actions {
    margin: 0 0 12px;
    font-family: Arial, Helvetica, sans-serif;
}

.file-label-actions a,
.file-label-actions button {
    margin-right: 8px;
}

.file-label {
    width: 150mm;
    height: 100mm;
    box-sizing: border-box;
    overflow: hidden;
    padding: 8mm;
    background: #fff;
    color: #111;
    font-family: Arial, Helvetica, sans-serif;
}

.file-label--front {
    height: 100mm;
}

.file-label__table,
.file-label--side__table {
    width: 100%;
    height: 84mm;
    border-collapse: collapse;
    table-layout: fixed;
}

.file-label__brand,
.file-label--side__brand {
    height: auto;
    padding: 0;
    border-bottom: 0.5mm solid #c8a24a;
    color: #444;
    font-size: 11pt;
    font-weight: 700;
    letter-spacing: 0.08em;
    line-height: 1.1;
    text-transform: uppercase;
    vertical-align: top;
}

.file-label__info,
.file-label--side__info {
    padding: 8mm 0 0;
    vertical-align: top;
}

.file-label__reference,
.file-label--side__reference {
    margin: 0 0 7mm;
    color: #111;
    font-size: 21pt;
    font-weight: 700;
    line-height: 1.15;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.file-label__purchaser,
.file-label--side__purchaser {
    margin: 0 0 5mm;
    color: #111;
    font-size: 14pt;
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.file-label__property,
.file-label__detail {
    margin: 0 0 7mm;
    color: #222;
    font-size: 11pt;
    line-height: 1.35;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.file-label__file-id,
.file-label--side__file-id {
    margin: 0;
    padding-top: 3mm;
    border-top: 0.3mm solid #d6d3d1;
    color: #333;
    font-size: 12pt;
    font-weight: 700;
    letter-spacing: 0.05em;
    line-height: 1.2;
}

.file-label__qr-cell,
.file-label--side__qr-cell {
    height: 43mm;
    padding: 4mm 0 0;
    border-top: 0.3mm solid #d6d3d1;
    text-align: center;
    vertical-align: middle;
}

.file-label__qr-group,
.file-label--side__qr-group {
    display: inline-block;
    width: 34mm;
    text-align: center;
    line-height: 0;
}

.file-label__qr,
.file-label--side__qr {
    display: block;
    width: 34mm;
    height: 34mm;
    margin: 0 auto;
}

.file-label__qr-caption,
.file-label--side__qr-caption {
    display: block;
    margin-top: 2mm;
    color: #555;
    font-size: 9pt;
    font-weight: 600;
    letter-spacing: 0.08em;
    line-height: 1.1;
    text-transform: uppercase;
}

.file-label--front .file-label__qr-cell {
    width: 44mm;
    height: auto;
    padding: 3mm 0 0 2mm;
    border: 0;
}

.file-label--front .file-label__brand {
    font-size: 15pt;
}

.file-label--front .file-label__info {
    padding-top: 8mm;
}

.file-label--front .file-label__reference {
    margin-bottom: 3mm;
    font-size: 20pt;
    white-space: nowrap;
    overflow-wrap: normal;
    word-break: normal;
}

.file-label--front .file-label__purchaser {
    margin-bottom: 3mm;
    font-size: 18pt;
}

.file-label--front .file-label__property,
.file-label--front .file-label__detail {
    margin-bottom: 3mm;
    font-size: 14pt;
    line-height: 1.25;
}

.file-label--front .file-label__detail {
    color: #333;
    font-weight: 700;
}

.file-label--front .file-label__qr-group,
.file-label--front .file-label__qr {
    width: 42mm;
}

.file-label--front .file-label__qr {
    height: 42mm;
}

.file-label--front .file-label__qr-caption {
    font-size: 11pt;
}

/* Keep the side label at its original 80 x 40 mm size and orientation. */
.file-label--side {
    position: relative;
    width: 80mm;
    height: 40mm;
    padding: 0;
}

.file-label--side__canvas {
    position: absolute;
    width: 40mm;
    height: 80mm;
    left: 20mm;
    top: -20mm;
    box-sizing: border-box;
    padding: 2.5mm 2mm;
    transform: rotate(90deg);
    transform-origin: center center;
    background: #fff;
    overflow: hidden;
}

.file-label--side__table {
    width: 100%;
    height: 100%;
}

.file-label--side__brand {
    height: auto;
    padding: 0 0 1.2mm;
    border-bottom-width: 0.35mm;
    color: #555;
    font-size: 6.5pt;
    font-weight: 600;
    text-align: center;
}

.file-label--side__reference {
    max-width: 36mm;
    padding: 3.5mm 0 0;
    margin: 0;
    font-size: 13pt;
    line-height: 1.15;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-label--side__purchaser {
    max-width: 36mm;
    padding: 2.8mm 0 0;
    margin: 0;
    font-size: 8.5pt;
    line-height: 1.15;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-label--side__file-id {
    padding: 2.5mm 0 0;
    margin: 0;
    border: 0;
    font-size: 7.5pt;
    line-height: 1.15;
    text-align: center;
}

.file-label--side__qr-cell {
    height: auto;
    padding: 3.5mm 0 0;
    border: 0;
}

.file-label--side__qr-group,
.file-label--side__qr {
    width: 19mm;
}

.file-label--side__qr {
    height: 19mm;
}

.file-label--side__qr-caption {
    margin-top: 1mm;
    font-size: 6pt;
}

@if(! empty($printDocument))
@media print {
    html, body {
        width: {{ $type === 'side' ? '80mm' : '150mm' }};
        margin: 0 !important;
        padding: 0 !important;
        background: #fff;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .file-label-actions {
        display: none !important;
    }

    .file-label,
    .file-label * {
        visibility: visible !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .file-label {
        margin: 0;
        border: 0;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .file-label__qr,
    .file-label--side__qr {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
@endif

@if(! empty($pdf))
/* Dompdf counts padding outside fixed dimensions; keep the printable area on one sheet. */
.file-label--front {
    width: 134mm;
    height: 84mm;
}

.file-label__table {
    height: 80mm;
}
@endif
