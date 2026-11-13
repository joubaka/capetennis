@php($pageConfigs = ['myLayout' => 'vertical'])
@extends('layouts.backend')
@section('title', $title)
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

<link rel="stylesheet" href="{{ asset('assets/css/draw-workspace.css') }}">
<link rel="stylesheet" href="{{ asset('css/flexible-monrad.css') }}?v={{ filemtime(public_path('css/flexible-monrad.css')) }}">
<link rel="stylesheet" href="{{ asset('css/flexible-workspace.css') }}?v={{ filemtime(public_path('css/flexible-workspace.css')) }}">
@include('backend.draw.partials.workspace-header', ['workspaceSurface' => 'flexible'])
<div id="flexible-draw-workspace" data-has-generated-draw="{{ $config['state']['generated'] ? '1' : '0' }}">
  <nav class="rr-workspace-nav mb-3" aria-label="Draw workspace" hidden>
    <button type="button" data-flexible-tab="groups">Players &amp; Positions</button>
    <button type="button" data-flexible-tab="matrix">Draw &amp; Results</button>
    <button type="button" data-flexible-tab="schedule">Schedule</button>
    <button type="button" data-flexible-tab="settings">Settings</button>
  </nav>
  <section data-flexible-panel="editor" class="fm-surface">
    <h2 class="fm-workspace-print-title">{{ $title }} · {{ $draw->event->name }}</h2>
    <section id="fm-generated-roster" class="p-3" hidden></section>
    @include('backend.draw.partials.flexible-monrad-editor')
  </section>
  <section data-flexible-panel="schedule" class="card" hidden>
    <div class="card-body">
      <h2 class="fm-workspace-print-title">{{ $title }} · {{ $draw->event->name }}</h2>
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><h2 class="h5">Schedule &amp; Venues</h2><p class="text-muted mb-0">Timetable publication is separate from draw publication.</p></div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]] + (request('schedule_date') ? ['date' => request('schedule_date')] : [])) }}">Schedule this draw</a>
          <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => request('schedule_date', 'all')]) }}">Review / edit saved times</a>
          <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id]) }}">Schedule multiple draws</a>
        </div>
      </div>
      <div id="fm-timetable"></div>
    </div>
  </section>
  <section data-flexible-panel="settings" class="card" hidden>
    <div class="card-body">
      <h2 class="h5">Settings</h2>
      @can('update', $draw)
        <form method="POST" action="{{ route('draws.update', $draw) }}" class="mb-3">
          @csrf
          <label for="workspace-draw-name" class="form-label">Draw name</label>
          <div class="d-flex flex-wrap gap-2"><input id="workspace-draw-name" class="form-control" style="max-width:420px" name="name" value="{{ $draw->drawName }}" required maxlength="255"><button class="btn btn-primary" type="submit">Save name</button></div>
        </form>
      @endcan
      <p>{{ $title }}</p>
      <p>Best of {{ $config['state']['best_of'] }} set(s). Starting positions and bracket size are managed in Players &amp; Positions.</p>
      <a class="btn btn-outline-primary" href="{{ route('draw.setup.show', $draw) }}">Review draw format</a>
      <p class="text-muted mt-3">Review the format to see the exact reset impact. Changing format requires an unlocked, unpublished draw and unpublished times; recorded results are protected. A confirmed reset can remove existing unplayed matches and times.</p>
      <a class="btn btn-primary mb-3" href="{{ route('backend.draw.roundrobin.show', $draw) }}#groups">Continue to players</a>
      @if(($config['workflow'] ?? null) === 'feed_in_monrad' && $config['state']['generated'] && $config['state']['challenge_stage_options']['base'])
        @can('enableRollingChallenges', $draw)
          <fieldset class="border rounded p-3 mb-3" id="fm-rolling-settings" aria-describedby="fm-rolling-help">
            <legend class="h6 float-none w-auto">Challenge matches</legend>
            <p id="fm-rolling-help">Choose the matches for this draw. Every selected match is skipped if the players have already met in this draw.</p>
            @foreach(['base' => 'Base-position pairs', 'follow_on' => 'Follow-on matches for the top five'] as $group => $heading)
              @if($config['state']['challenge_stage_options'][$group])
                <h3 class="h6 mt-3">{{ $heading }}</h3>
                @foreach($config['state']['challenge_stage_options'][$group] as $stage => $label)
                  <label class="d-flex gap-2 align-items-center py-2">
                    <input type="checkbox" class="form-check-input mt-0" data-rolling-stage="{{ $stage }}" @checked(in_array($stage, $config['state']['rolling_challenge_stages'], true))>
                    <span>{{ $label }}</span>
                  </label>
                @endforeach
              @endif
            @endforeach
            <p class="text-muted small">Current 3 vs 4 uses the positions after whichever earlier matches you select. “2 vs new 3” requires Current 3 vs 4 and only takes place if that match changes position 3. No other match is enabled automatically.</p>
            <p class="text-muted small">New matches need scheduling separately. A change to private saved times requires your confirmation. Started matches, results and published times are protected.</p>
            <button type="button" class="btn btn-outline-primary" id="fm-rolling-challenges">Save challenge choices</button>
            <p role="status" id="fm-rolling-feedback" class="small mt-2 mb-0"></p>
          </fieldset>
        @endcan
      @endif
      @can('editNotes', $draw)
        <form data-workspace-notes action="{{ route('backend.draw.update-notes', $draw) }}" method="POST">
          @csrf
          @foreach(array_replace(['general' => ''], $draw->settings?->notes ?? []) as $section => $note)
            <label class="form-label" for="workspace-note-{{ $loop->index }}">{{ ucfirst(str_replace('_', ' ', $section)) }} rules &amp; notes</label>
            <textarea class="form-control mb-3" rows="4" maxlength="5000" id="workspace-note-{{ $loop->index }}" name="notes[{{ $section }}]">{{ $note }}</textarea>
          @endforeach
          <button type="submit" class="btn btn-primary">Save rules &amp; notes</button>
        </form>
      @else
        @foreach(($draw->settings?->notes ?? []) as $section => $note)
          @if($note)<h3 class="h6">{{ ucfirst(str_replace('_', ' ', $section)) }}</h3><p style="white-space:pre-wrap">{{ $note }}</p>@endif
        @endforeach
      @endcan
    </div>
  </section>
  <section data-flexible-panel="print" class="card" hidden>
    <div class="card-body">
      <h2 class="h5">Print draw</h2>
      <p>Print the same bracket and results shown in this workspace, with fixture references and final positions.</p>
      <button type="button" class="btn btn-primary" id="fm-workspace-print">Print draw &amp; results</button>
      <button type="button" class="btn btn-outline-secondary" id="fm-draw-only-print">Print draw only</button>
      <button type="button" class="btn btn-outline-secondary" id="fm-timetable-print">Print schedule</button>
      <p class="text-muted small mt-3 mb-0">The print dialog also lets you save a PDF. Share the published public link for live updates.</p>
    </div>
  </section>
</div>
</div>
@endsection
