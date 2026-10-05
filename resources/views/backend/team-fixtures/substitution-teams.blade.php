@extends('layouts.backend')
@section('content')
<div class="container-xxl"><div class="card"><div class="card-body">
<h4>Choose the source team</h4>
<p>Use the replacement wizard to preserve completed matches and financial history.</p>
@foreach($teams as $team)
<a class="d-block p-2 border rounded mb-2" href="{{ route('backend.team-substitutions.show', $team) }}">{{ $team->name }} · {{ $team->category->event->name }}</a>
@endforeach
{{ $teams->links() }}
</div></div></div>
@endsection
