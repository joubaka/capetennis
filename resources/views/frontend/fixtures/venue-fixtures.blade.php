@extends('layouts/layoutMaster')

@section('title', 'Fixtures at ' . $venue->name)

@section('content')
<div class="container-xxl py-4">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Fixtures at {{ $venue->name }}</h2>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            {{-- Hide ID on mobile --}}
                            <th class="d-none d-sm-table-cell">#</th>
                            <th>Home</th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($fixtures as $fx)
                        <tr>
                            {{-- Hide ID cell on mobile --}}
                            <td class="text-muted d-none d-sm-table-cell">{{ $fx->id }}</td>
                            
                            <td class="fw-semibold">{{ $fx->home_side_name ?? $fx->home_team_name }}</td>
                            <td class="fw-semibold">{{ $fx->away_side_name ?? $fx->away_team_name }}</td>
                            
                            <td>
                                @if($fx->fixtureResults->count())
                                    <div class="mb-2">{{ $fx->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->team1_score.'-'.$set->team2_score)->implode(', ') }}</div>
                                @endif
                                @can('team-fixture.saveScore', $fx)
                                    <form method="POST" action="{{ route('frontend.fixtures.score.store', $fx->id) }}">
                                        @csrf
                                        @for($set = 1; $set <= ($fx->draw->team_scoring_rules ? app(\App\Services\TeamRubberResultService::class)->rules($fx)['sets_to_win'] * 2 - 1 : 3); $set++)
                                            @php $existing = $fx->fixtureResults->firstWhere('set_nr', $set); @endphp
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <label class="small mb-0">Set {{ $set }}</label>
                                                <input type="number" min="0" max="99" name="set{{ $set }}_home" aria-label="Home set {{ $set }}" value="{{ old('set'.$set.'_home', $existing?->team1_score) }}" class="form-control form-control-sm" style="width:70px" @required($set === 1)>
                                                <span>-</span>
                                                <input type="number" min="0" max="99" name="set{{ $set }}_away" aria-label="Away set {{ $set }}" value="{{ old('set'.$set.'_away', $existing?->team2_score) }}" class="form-control form-control-sm" style="width:70px" @required($set === 1)>
                                            </div>
                                        @endfor
                                        <button type="submit" class="btn btn-sm btn-primary">Save scores</button>
                                    </form>
                                @endcan
                            </td>
                            
                            <td class="text-end">
                                @if($fx->fixtureResults->count() && auth()->user()?->can('team-fixture.saveScore', $fx))
                                    <form method="POST" action="{{ route('frontend.fixtures.score.delete', $fx->id) }}" onsubmit="return confirm('Delete result?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                            <span class="d-none d-md-inline">Delete</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
