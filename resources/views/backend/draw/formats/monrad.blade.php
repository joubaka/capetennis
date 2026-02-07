@extends('layouts.layoutMaster')

@section('title', 'Bracket Test')

@section('content')
    <div class="container">
        <h2>Bracket Preview</h2>
        <div class="border p-3" style="background:white; overflow-x:auto;">
          @php
            $matches = [
        ['player1' => 'Player A1', 'player2' => 'Player A2'],
        ['player1' => 'Player B1', 'player2' => 'Player B2'],
        ['player1' => 'Player C1', 'player2' => 'Player C2'],
        ['player1' => 'Player D1', 'player2' => 'Player D2'],
        ['player1' => 'Player E1', 'player2' => 'Player E2'],
        ['player1' => 'Player F1', 'player2' => 'Player F2'],
        ['player1' => 'Player G1', 'player2' => 'Player G2'],
        ['player1' => 'Player H1', 'player2' => 'Player H2'],
    ];
    use App\Services\CtBracket;
    $bracketSvg = app(CtBracket::class)->build(16, $matches);
          @endphp

        </div>


        <div class="container mt-5">
          <h2 class="mb-4">Tournament Draw</h2>

          <div class="bracket-wrapper" style="overflow-x:auto; background:#fff; padding:20px; border:1px solid #ccc;">
              {!! $bracketSvg !!}
          </div>
      </div>
    </div>
@endsection
