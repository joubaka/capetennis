@extends('layouts.backend')

@section('title', 'Player Profile')

@section('vendor-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')


@endsection

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

<div class="card">
    <div class="card-header"><a href="{{ URL::previous() }}" class="btn btn-primary">Back</a></div>

</div>
<div class="card">
    <div class="card-header"><h3>{{$player->getFullNameAttribute()}}</h3></div>
    <div class="card-body">
      @forelse($results['fixture'] as $key => $result)

@php [$homeClass, $awayClass] = \App\Support\ResultPresentation::classes($result->fixture); @endphp
<p>{{$result->created_at->format('j F , Y') }} <span class="badge bg-label-success"> {{$result->fixture->draw->events->name}}</span> <span class="{{ $homeClass }}">{{$result->team1->getFullNameAttribute()}}<x-result-label :outcome="$homeClass" /></span> vs <span class="{{ $awayClass }}">{{$result->team2->getFullNameAttribute()}}<x-result-label :outcome="$awayClass" /></span> @foreach($result->fixture->teamResults as $r) {{$r->team1_score. '-'.$r->team2_score.';'}} @endforeach </p>

    @empty<p class="text-muted">No recorded team results for this player.</p>
    @endforelse
    </div>

</div>



</div>
@endsection