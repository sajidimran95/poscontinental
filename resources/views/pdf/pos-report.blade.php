<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        @page { margin: 0.45in 0.4in; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; margin: 0; }
        .meta {
            font-size: 10px; color: #333; margin-bottom: 10px;
            border-bottom: 1px solid #999; padding-bottom: 6px;
        }
        .meta strong { color: #000; }
        h2 { font-size: 12px; margin: 10px 0 4px; text-transform: uppercase; }
        h3 { font-size: 10px; margin: 8px 0 3px; font-weight: bold; }
        .addr { font-size: 9px; margin-bottom: 3px; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th {
            text-align: left; font-weight: bold; border-bottom: 1px solid #000;
            padding: 2px 3px 4px; font-size: 8.5px; white-space: nowrap;
        }
        td { padding: 1px 3px; vertical-align: top; font-size: 8.5px; }
        .num { text-align: right; white-space: nowrap; }
        th.num { text-align: right; }
        tr.totals td { border-top: 1px solid #000; font-weight: bold; padding-top: 3px; }
        tr.grand td { border-top: 2px solid #000; font-weight: bold; padding-top: 4px; }
        .section { page-break-inside: avoid; margin-bottom: 12px; }
        .empty { text-align: center; color: #666; padding: 16px; }
    </style>
</head>
<body>
@php
    $companyName = $company?->name ?? 'Continental Wholesale Inc';
@endphp
<div class="meta">
    <strong>{{ $companyName }}</strong>
    &nbsp;·&nbsp; {{ $title }}
    @if (! empty($period))
        &nbsp;·&nbsp; {{ $period }}
    @endif
    &nbsp;·&nbsp; Generated {{ now()->format('M j, Y g:i A') }}
</div>

@if (empty($sections) || count($sections) === 0)
    <div class="empty">No data for the selected criteria.</div>
@else
    @foreach ($sections as $section)
        <div class="section">
            @if (! empty($section['title']))
                <h2>
                    {{ $section['title'] }}
                    @if (! empty($section['subtitle']))
                        <span style="float:right;font-weight:bold;text-transform:none;">{{ $section['subtitle'] }}</span>
                    @endif
                </h2>
            @endif
            @if (! empty($section['lines']))
                @foreach ($section['lines'] as $line)
                    <div class="addr">{{ $line }}</div>
                @endforeach
            @endif
            @if (! empty($section['heading']))
                <h3>{{ $section['heading'] }}</h3>
            @endif
            @if (! empty($section['headers']) && isset($section['rows']))
                <table>
                    <thead>
                        <tr>
                            @foreach ($section['headers'] as $i => $h)
                                <th class="{{ in_array($i, $section['numCols'] ?? [], true) ? 'num' : '' }}">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            @php
                                $isTotals = ! empty($row['_totals']);
                                $isGrand = ! empty($row['_grand']);
                                $cells = $row;
                                unset($cells['_totals'], $cells['_grand']);
                                // Re-index if assoc with string keys mixed - keep values in order
                                $cells = array_values(array_filter(
                                    $cells,
                                    fn ($v, $k) => ! (is_string($k) && str_starts_with((string) $k, '_')),
                                    ARRAY_FILTER_USE_BOTH
                                ));
                            @endphp
                            <tr class="{{ $isGrand ? 'grand' : ($isTotals ? 'totals' : '') }}">
                                @foreach ($cells as $i => $cell)
                                    <td class="{{ in_array($i, $section['numCols'] ?? [], true) ? 'num' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endif
</body>
</html>
