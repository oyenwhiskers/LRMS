<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1c1917;
            font-size: 12px;
            margin: 0;
        }

        .report-header {
            width: 100%;
            border-bottom: 2px solid #d4af5a;
            margin-bottom: 18px;
            padding-bottom: 10px;
        }

        .report-header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .report-logo {
            width: 48px;
            height: auto;
        }

        .report-brand-wrap {
            width: 100%;
            border-collapse: collapse;
        }

        .report-brand-logo {
            width: 48px;
            vertical-align: middle;
        }

        .report-brand {
            padding-left: 18px;
            vertical-align: middle;
        }

        .report-brand-name {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #b78626;
            line-height: 1.08;
        }

        .report-brand-subtitle {
            margin-top: 4px;
            font-size: 9px;
            letter-spacing: 0.17em;
            text-transform: uppercase;
            color: #57534e;
            line-height: 1.35;
        }

        .report-meta {
            text-align: right;
        }

        .report-kicker {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #78716c;
            line-height: 1.2;
        }

        .report-title {
            margin-top: 5px;
            font-size: 17px;
            font-weight: 700;
            line-height: 1.16;
            color: #1c1917;
        }

        .report-timestamp {
            margin-top: 5px;
            font-size: 10px;
            line-height: 1.35;
            color: #57534e;
        }

        .section-title {
            margin: 24px 0 10px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #44403c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d6d3d1;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        th {
            background: #f5f5f4;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .empty-note {
            padding: 14px 0 0;
            color: #78716c;
        }

        .summary-block {
            margin-top: 6px;
        }

        .detail-table th:first-child,
        .detail-table td:first-child {
            width: 34px;
            text-align: center;
        }

    </style>
</head>
<body>
    <div class="report-header">
        <table class="report-header-table">
            <tr>
                <td style="width: 232px;">
                    <table class="report-brand-wrap">
                        <tr>
                            <td class="report-brand-logo" style="width: 56px;">
                                @if(!empty($logo))
                                    <img class="report-logo" src="{{ $logo }}" alt="Tsang &amp; Co.">
                                @endif
                            </td>
                            <td class="report-brand">
                                <div class="report-brand-name">TSANG &amp; CO</div>
                                <div class="report-brand-subtitle">Legal Records Management</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="report-meta">
                    <div class="report-kicker">Management Report</div>
                    <div class="report-title">{{ $title }}</div>
                    @php
                        $subtitleParts = str_contains($subtitle, ' as of ')
                            ? explode(' as of ', $subtitle, 2)
                            : [$subtitle, null];
                    @endphp
                    @if(!empty($subtitleParts[1]))
                        <div class="report-timestamp">{{ $subtitleParts[1] }}</div>
                    @else
                        <div class="report-timestamp">{{ $subtitle }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($summaryTitle))
        <div class="section-title">{{ $summaryTitle }}</div>
    @endif

    <table class="summary-block">
                <thead>
                    <tr>
                @foreach($columns as $column)
                    <th @class(['text-right' => str($column)->lower()->toString() === 'value'])>{{ $column }}</th>
                @endforeach
                    </tr>
                </thead>
                <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $index => $value)
                        <td @class(['text-right' => ($columns[$index] ?? null) === 'Value'])>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($detailColumns))
        <div class="section-title">{{ $detailTitle ?? 'Detail records' }}</div>

        @if(!empty($detailRows))
            <table class="detail-table">
                <thead>
                    <tr>
                        @foreach($detailColumns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($detailRows as $row)
                        <tr>
                            @foreach($row as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty-note">No records found for this report period.</p>
        @endif
    @endif

</body>
</html>
