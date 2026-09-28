@extends('layouts/layoutMaster')
@section('title', 'My Interprovincial Trials invitations')
@section('content')
<h4>My Interprovincial Trials invitations</h4>
@forelse($invitations as $invitation)<div class="card mb-3"><div class="card-body"><h5>{{ $invitation->event->name }}</h5><p>{{ $invitation->player->name }} {{ $invitation->player->surname }} — {{ $invitation->categoryEvent->category->name }}</p><a class="btn btn-primary" href="{{ URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addMinutes(30), ['invitation'=>$invitation]) }}">View invitation</a></div></div>@empty<p>No invitations are available for your linked player profiles.</p>@endforelse
{{ $invitations->links() }}
@endsection
