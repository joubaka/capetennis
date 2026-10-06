@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player performance pilot')

@section('content')
<div class="card mb-4">
    <div class="card-body">
        <h1 class="h3">Player performance pilot</h1>
        <a class="btn btn-primary mb-3" href="{{ route('backend.player-performance.directory') }}">Find any player's rating</a>
        <p class="alert alert-warning text-dark">
            Private Super Admin preview. Every score is provisional and uncalibrated. This measures tournament finishes,
            not an official UTR or a universal playing ability rating. Compare only equivalent age/category cohorts.
            Doubles scores reflect partnership finishes.
        </p>
        <form method="get" action="{{ route('backend.player-performance.index') }}" class="row g-3">
            @foreach(['a' => 'A category (required to calculate)', 'b' => 'Comparable B category (optional)'] as $tier => $label)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="{{ $tier }}_category_id">{{ $label }}</label>
                    <select class="form-select" name="{{ $tier }}_category_id" id="{{ $tier }}_category_id">
                        <option value="">Choose category</option>
                        @foreach($categories->take(500) as $category)
                            <option value="{{ $category->id }}" @selected((string)($settings[$tier.'_category_id'] ?? '') === (string)$category->id)>
                                {{ $category->name }} (#{{ $category->id }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-12 col-md-4">
                <label class="form-label" for="discipline">Discipline</label>
                <select name="discipline" id="discipline" class="form-select">
                    @foreach(['singles', 'doubles'] as $discipline)
                        <option @selected($settings['discipline'] === $discipline)>{{ $discipline }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label" for="months">Recent months (1–24)</label>
                <input class="form-control" id="months" name="months" type="number" min="1" max="24" value="{{ $settings['months'] }}">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label" for="as_of">As of</label>
                <input class="form-control" id="as_of" name="as_of" type="date" max="{{ today()->toDateString() }}" value="{{ $settings['as_of'] }}">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Preview scores</button>
            </div>
        </form>
        @if($errors->any())
            <div class="alert alert-danger mt-3">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if($categories->count() > 500)
            <p class="mt-3">Only the first 500 categories are listed.</p>
        @endif
        <details class="mt-3">
            <summary>Proposed rules and exclusions</summary>
            <p class="mt-2">
                A finishes score 50–100; B finishes score 0–50. These bands are arbitrary pilot assumptions,
                not calibrated player strengths. Score = band minimum + 50 × (ranked field − position) / (ranked field − 1).
                Recent results receive weight 0.5^(days since event end / 180); the displayed score is their weighted
                average, rounded once to one decimal.
            </p>
            <p>
                Only published events and published results ending in the selected window count. The latest saved
                correction is used. Every field must have at least two unique consecutive positions and match its
                saved ranked registrations to recorded category membership. Extra unranked entrants do not erase published finishes. Missing ranked membership, repeated players, incomplete
                positions, and mixed disciplines exclude the whole field. Ranked field means saved finishing records,
                not an independently verified starter count. Qualification is not assessed and earns no bonus.
                Team-event results are excluded, including their singles subdraws. No existing ranking or player
                record is changed.
            </p>
        </details>
    </div>
</div>

@if($preview)
    @if($preview['truncated'])
        <div class="alert alert-warning text-dark">
            Preview limited to the most recent 50 category fields. Older fields within the window were omitted;
            scores describe this limited sample.
        </div>
    @endif
    <p>
        {{ $preview['summary']['included_fields'] }} eligible fields · {{ $preview['summary']['skipped_fields'] }} skipped fields ·
        {{ $preview['summary']['scored_players'] }} scored players of {{ $players->total() }} with saved results.
    </p>
    <p>{{ $settings['discipline'] }} cohort preview · {{ $players->total() }} players · qualification history: not assessed.</p>
    @forelse($players as $player)
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5"><a href="{{ route('backend.player-performance.show', $player['id']) }}">{{ $player['name'] }}</a></h2>
                <p>
                    <strong>{{ $player['score'] === null ? 'No eligible score' : number_format($player['score'], 1).'/100 — provisional' }}</strong> ·
                    {{ $player['count'] }} contributing tournament {{ $player['count'] === 1 ? 'result' : 'results' }} ·
                    Last eligible event: {{ $player['last_played'] ?? 'None' }}
                </p>
                <details>
                    <summary>Finishes and scoring evidence ({{ $player['results']->count() }})</summary>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-nowrap">Event / date</th>
                                    <th class="text-nowrap">Category / division</th>
                                    <th class="text-nowrap">Finish / ranked field</th>
                                    <th class="text-nowrap">Points / weight</th>
                                    <th class="text-nowrap">Included / skipped</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($player['results'] as $result)
                                    <tr>
                                        <td>{{ $result['event'] }}<br>{{ $result['date'] }}</td>
                                        <td>{{ $result['category'] }}<br>{{ $result['tier'] }}</td>
                                        <td>{{ $result['position'] }} / {{ $result['field_capped'] ? 'at least ' : '' }}{{ $result['field_size'] }}</td>
                                        <td>{{ $result['points'] === null ? '—' : number_format($result['points'], 1) }} / {{ number_format($result['weight'], 3) }}</td>
                                        <td>{{ $result['reason'] ?? 'Included' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    @empty
        <div class="alert alert-info">No player results in this published cohort and date window.</div>
    @endforelse
    {{ $players->links() }}
    <details class="card p-3 mt-3">
        <summary>Excluded fields, including fields without players</summary>
        <ul class="mt-2">
            @forelse($preview['evidence']->whereNotNull('reason')->unique(fn ($row) => $row['event_id'].':'.$row['category_id']) as $result)
                <li>{{ $result['event'] }} · {{ $result['date'] }} · {{ $result['category'] }} ({{ $result['tier'] }}): {{ $result['reason'] }}</li>
            @empty
                <li>No fields excluded.</li>
            @endforelse
        </ul>
    </details>
@else
    <p>
        Choose the A category to begin. Pair B only when it represents the same age and competition cohort;
        division labels are selected explicitly and never inferred from names.
    </p>
@endif
@endsection
