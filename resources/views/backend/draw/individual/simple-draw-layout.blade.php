@extends('layouts/layoutMaster')

@section('title', 'Simple Draw – ' . $draw->drawName)

@section('content')

<div class="card">
    <div class="card-header">
        <h4>{{ $draw->drawName }} – Simple Draw</h4>
        <p class="text-muted">{{ $event->name }}</p>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>Player 1</th>
                    <th></th>
                    <th>Player 2</th>
                    <th>Score</th>
                </tr>
            </thead>

            <tbody>
                @foreach($fixtures as $fixture)
                    <tr>
                        <td>{{ $fixture->id }}</td>

                        <td>{{ $fixture->match_nr }}</td>

                        <td>
                            @if($fixture->registration1)
                                {{ $fixture->registration1->players[0]->getFullNameAttribute() }}
                            @else
                                BYE
                            @endif
                        </td>

                        <td class="text-center">vs</td>

                        <td>
                            @if($fixture->registration2)
                                {{ $fixture->registration2->players[0]->getFullNameAttribute() }}
                            @else
                                BYE
                            @endif
                        </td>

                        <td>{{ $fixture->fixtureResults->map(fn($r) => $r->score_line)->join(', ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
