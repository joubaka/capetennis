@extends('layouts/layoutMaster')

@section('title', 'Fixtures at ' . $venue->name)

@section('content')
@extends('layouts/layoutMaster')

@section('title', 'Fixtures at ' . $venue->name)

@section('content')
<div class="container-xxl py-4">
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
                            
                            <td class="fw-semibold">{{ $fx->homeTeam->name ?? $fx->home_team_name }}</td>
                            <td class="fw-semibold">{{ $fx->awayTeam->name ?? $fx->away_team_name }}</td>
                            
                            <td>
                                @if($fx->fixtureResults->count())
                                    <span class="badge bg-success text-white px-2 py-2">
                                        {{ $fx->fixtureResults->last()->team1_score }} - {{ $fx->fixtureResults->last()->team2_score }}
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('frontend.fixtures.score.store', $fx->id) }}" class="d-flex align-items-center gap-1">
                                        @csrf
                                        <input type="number" name="team1_score" required class="form-control form-control-sm score-input" placeholder="0">
                                        <span class="text-muted">-</span>
                                        <input type="number" name="team2_score" required class="form-control form-control-sm score-input" placeholder="0">
                                        <button type="submit" class="btn btn-sm btn-primary ms-1">
                                            <i class="bi bi-check-lg d-md-none"></i>
                                            <span class="d-none d-md-inline">Save</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                            
                            <td class="text-end">
                                @if($fx->fixtureResults->count())
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

<style>
th, td { vertical-align: middle !important; }

/* Fixed width for score inputs to prevent jumping */
.score-input {
    width: 45px !important;
    text-align: center;
    padding: 0.25rem 0.2rem;
}

@media (max-width: 576px) {
    .table th, .table td { 
        font-size: 0.8rem; 
        padding: 0.5rem 0.25rem; 
    }
    h2 { font-size: 1.2rem; }
    
    /* Ensure the inputs don't make the row too tall */
    .form-control-sm {
        height: 28px;
    }
}
</style>

<div class="container-xxl py-4">
    <h2>Fixtures at {{ $venue->name }}</h2>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Home</th>
                            <th>Away</th>
                            <th>Result</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($fixtures as $fx)
                        <tr>
                            <td>{{ $fx->id }}</td>
                            <td>{{ $fx->homeTeam->name ?? $fx->home_team_name }}</td>
                            <td>{{ $fx->awayTeam->name ?? $fx->away_team_name }}</td>
                            <td>
                                @if($fx->fixtureResults->count())
                                    {{ $fx->fixtureResults->last()->team1_score }} - {{ $fx->fixtureResults->last()->team2_score }}
                                @else
                                    <form method="POST" action="{{ route('frontend.fixtures.score.store', $fx->id) }}">
                                        @csrf
                                        <input type="number" name="team1_score" required style="width:60px;">
                                        -
                                        <input type="number" name="team2_score" required style="width:60px;">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                @if($fx->fixtureResults->count())
                                    <form method="POST" action="{{ route('frontend.fixtures.score.delete', $fx->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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
