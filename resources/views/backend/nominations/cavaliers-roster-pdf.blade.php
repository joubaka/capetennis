<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} · {{ $categoryEvent->category->name }}</title>
    <style>
        body { font: 10px "DejaVu Sans", sans-serif; color: #17283b; }
        h1 { font-size: 19px; margin-bottom: 3px; }
        h2 { font-size: 13px; color: #155e59; margin: 18px 0 5px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        th, td { border-bottom: 1px solid #d9e3eb; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #eaf3f2; }
        tr { page-break-inside: avoid; }
        .lost { color: #a5252b; }
        .winless { color: #914500; }
    </style>
</head>
<body>
    <h1>{{ $event->name }}</h1>
    <p><strong>{{ $categoryEvent->category->name }} · Private selection review</strong></p>
    <p>Saved roster. Inter-Districts source division: {{ $preview['source_division'] }}.</p>
    <p>Candidate regions: {{ implode(', ', $preview['candidate_regions']) ?: 'None' }}.
        Results excluded: {{ implode(', ', $preview['excluded_result_regions']) ?: 'None' }}.</p>
    @if ($preview['missing_regions'])
        <p>No published team for: {{ implode(', ', $preview['missing_regions']) }}.</p>
    @endif
    @if ($preview['platteland_notice'])
        <p>{{ $preview['platteland_notice'] }}</p>
    @endif

    @foreach (['selected' => 'Selected', 'reserves' => 'Reserves', 'declined' => 'Declined'] as $key => $label)
        <h2>{{ $label }} ({{ count($roster[$key]) }})</h2>
        @if ($roster[$key])
            <table>
                <thead><tr><th>#</th><th>Player</th><th>Region and source</th></tr></thead>
                <tbody>
                    @foreach ($roster[$key] as $player)
                        @php
                            $candidate = $evidence->get($player['id']);
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td><td>{{ $player['name'] }}</td>
                            <td>{{ implode(' · ', array_filter([$candidate['region'] ?? null, $candidate['source'] ?? null])) ?: 'No candidate source in this review' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>None recorded.</p>
        @endif
    @endforeach

    <h2>Inter-Districts candidates left out ({{ $leftOut->count() }})</h2>
    <p>Players outside Selected, Reserves and Declined. Results reflect counted singles under the current setup.
        The draft records roster choices. It does not record an individual reason for leaving a player out.</p>
    @if ($leftOut->isNotEmpty())
        <table>
            <thead><tr><th>Player</th><th>Region / band</th><th>Singles</th><th>Selection evidence</th></tr></thead>
            <tbody>
                @foreach ($leftOut as $candidate)
                    <tr>
                        <td>{{ $candidate['name'] }}</td>
                        <td>{{ $candidate['region'] ?: '—' }} · {{ $candidate['band'] }}</td>
                        <td>{{ $candidate['wins'] === null ? '—' : $candidate['wins'].'–'.$candidate['losses'] }}</td>
                        <td>
                            @if ($candidate['lost_all'])
                                <span class="lost">Lost all counted singles.</span>
                            @elseif ($candidate['only_beat_winless'])
                                <span class="winless">Only beat opponents who lost all counted singles.</span>
                            @elseif ($candidate['wins'] === null)
                                No counted singles results available.
                            @else
                                No exclusion reason recorded.
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No Inter-Districts candidates outside the saved roster for this setup.</p>
    @endif
</body>
</html>
