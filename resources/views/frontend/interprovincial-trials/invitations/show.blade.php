@extends('layouts/layoutMaster')
@section('title', 'Interprovincial Trials invitation')
@section('content')
<div class="card"><div class="card-body"><h4>{{ $invitation->event->name }}</h4><p>{{ $invitation->player->name }} {{ $invitation->player->surname }} has been invited for {{ $invitation->categoryEvent->category->name }}.</p><div class="alert alert-info">Registration will open later. No registration or payment has been created by this invitation.</div></div></div>
@endsection
