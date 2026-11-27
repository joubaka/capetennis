@extends('layouts.backend')
@section('title', 'Playoff lucky loser')
@section('content')
<div class="card"><div class="card-body">
  <a href="{{ route('backend.draw.roundrobin.show', $draw) }}">Back to draw</a>
  <h1 class="h3 mt-3">Playoff lucky loser</h1>
  <p>Withdraw a player from an unstarted playoff place and choose an active entrant from this event. Their original round-robin results stay unchanged. The replacement will show LL beside their name.</p>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="get" class="mb-3">
    <label class="form-label" for="ll-search">Find an event entrant</label>
    <div class="d-flex gap-2"><input class="form-control" id="ll-search" name="q" value="{{ $query }}" maxlength="100"><button class="btn btn-outline-primary">Search</button></div>
  </form>
  <form method="post" action="{{ route('draw.playoff-lucky-loser.store', $draw) }}">
    @csrf
    <input type="hidden" name="revision" value="{{ $revision }}">
    <label class="form-label" for="ll-target">Player withdrawing from the playoffs</label>
    <select class="form-select mb-3" name="target" id="ll-target" required>
      <option value="">Choose the playoff place</option>
      @foreach($fixtures as $fixture)
        @foreach([1, 2] as $slot)
          @php $registration = $fixture->{'registration'.$slot}; $value = $fixture->id.':'.$slot.':'.$registration?->id; @endphp
          @if($registration)
            <option value="{{ $value }}" @selected(old('target') === $value)>Match {{ $fixture->match_nr }} · {{ $fixture->stage }} · {{ $registration->display_name }}</option>
          @endif
        @endforeach
      @endforeach
    </select>
    <label class="form-label" for="ll-entry">Incoming lucky loser</label>
    <select class="form-select mb-3" name="source_entry_id" id="ll-entry" required>
      <option value="">Choose an active event entrant</option>
      @foreach($candidates as $entry)
        <option value="{{ $entry->id }}" @selected((int) old('source_entry_id') === (int) $entry->id)>{{ $entry->registration->display_name }} · {{ $entry->categoryEvent->category?->name }}</option>
      @endforeach
    </select>
    <p class="small text-muted">Any division in this event may supply the replacement. A player with another active playoff place cannot be selected.</p>
    <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirm_withdrawal" value="1" required>Confirm withdrawal from the selected playoff place and replace that player with the selected lucky loser.</label>
    <button class="btn btn-primary">Confirm playoff withdrawal and lucky loser</button>
  </form>
  <div class="mt-3">{{ $candidates->links() }}</div>
</div></div>
@endsection
