@php($pageConfigs = ['myLayout' => 'vertical'])
@extends('layouts.backend')
@section('title', $title)
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/draw-workspace.css') }}">
<link rel="stylesheet" href="{{ asset('css/flexible-monrad.css') }}?v={{ filemtime(public_path('css/flexible-monrad.css')) }}">
<link rel="stylesheet" href="{{ asset('css/flexible-workspace.css') }}?v={{ filemtime(public_path('css/flexible-workspace.css')) }}">
@include('backend.draw.partials.workspace-header')
<div id="flexible-draw-workspace">
  <nav class="rr-workspace-nav mb-3" aria-label="Draw workspace">
    <button type="button" data-flexible-tab="groups">Players &amp; Positions</button>
    <button type="button" data-flexible-tab="matrix">Draw &amp; Results</button>
    <button type="button" data-flexible-tab="schedule">Schedule</button>
    <button type="button" data-flexible-tab="settings">Setup &amp; Rules</button>
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
          <a class="btn btn-primary" href="{{ route('backend.individual-schedule.page', $draw) }}">Manage this draw</a>
          <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1]) }}">Manage full schedule</a>
          <a class="btn btn-outline-secondary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id], 'manual' => 1]) }}">Schedule only this draw</a>
        </div>
      </div>
      @can('publish', $draw)
        <button type="button" class="btn btn-outline-secondary mb-3" data-workspace-publish-schedule="{{ route('draw.toggle.publish.schedule', $draw) }}">{{ $draw->oop_published ? 'Unpublish schedule' : 'Publish schedule' }}</button>
      @endcan
      <div id="fm-timetable"></div>
    </div>
  </section>
  <section data-flexible-panel="settings" class="card" hidden>
    <div class="card-body">
      <h2 class="h5">Setup &amp; Rules</h2>
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
      <p class="text-muted mt-3">Changing format requires an empty, unlocked, unpublished draw. Existing fixtures and results are protected.</p>
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
@endsection
