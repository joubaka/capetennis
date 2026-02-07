@extends('layouts/layoutMaster')

@section('title', 'All Categories Schedule – ' . $event->name)

{{-- =========================
     VENDOR STYLES
   ========================= --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"
        referrerpolicy="no-referrer" />
@endsection

{{-- =========================
     VENDOR SCRIPTS
   ========================= --}}
@section('vendor-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
          referrerpolicy="no-referrer"></script>
@endsection

@section('content')
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">All Categories – {{ $event->name }}</h4>
    <a href="{{ route('event.tab.draws', $event->id) }}" class="btn btn-secondary btn-sm">Back</a>
  </div>

  {{-- 🔹 Global Auto-Schedule Controls --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="autoForm" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="text" id="start" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>
        <div class="col-md-3">
          <label class="form-label">End</label>
          <input type="text" id="end" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Duration (min)</label>
          <input type="number" id="duration" class="form-control" value="90" min="20" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Gap (min)</label>
          <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Rounds (optional)</label>
          <input type="text" id="round" class="form-control" placeholder="e.g. 1,2,3">
        </div>
        <div class="col-12">
          <label class="form-label">Venues (limit to these)</label>
          <select id="venues" class="form-select" multiple></select>
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
          <button type="button" id="btnAutoAll" class="btn btn-primary">
            <i class="ti ti-calendar"></i> Auto-Schedule All
          </button>
          <button type="button" id="btnReload" class="btn btn-outline-secondary">Reload</button>
          <button type="button" id="btn-clear-schedule" class="btn btn-outline-danger btn-sm">
            <i class="ti ti-trash"></i> Clear All
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- 🔹 Rank → Venue Map --}}
  <div class="card mt-3">
    <div class="card-header">
      <h5 class="mb-0">Rank → Venue Map</h5>
      <small class="text-muted">Assign home rank numbers to venues</small>
    </div>
    <div class="card-body">
      <form id="rankVenueForm" class="row g-2 align-items-end">
        <div class="col-md-12">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">Home Rank #</th>
                <th>Venue</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="rankVenueRows"></tbody>
          </table>
        </div>
        <div class="col-12">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRankVenue">
            <i class="ti ti-plus"></i> Add Mapping
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- 🔹 Draw Sections --}}
  <div id="drawSections" class="mt-4"></div>
</div>

{{-- =========================
     MAIN SCRIPT
   ========================= --}}
<script>
$(function () {
  'use strict';
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const eventId = {{ $event->id }};
  const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };
  const DEBUG = true;
  let VENUES = [];
  let rankVenueMap = {};

  function log(...a){ if(DEBUG) console.log('[ALL-SCHEDULE]',...a); }
  log('🟢 Init page for event ID:', eventId);

  // ========== HELPERS ==========
  function safeFlatpickr(el){
    if(!el._flatpickr){
      flatpickr(el, fpOpts);
      log('🕓 Flatpickr init', el);
    }
  }
  function safeSelect2($el,opts={}){
    if($el.hasClass('select2-hidden-accessible')){
      $el.select2('destroy');
      log('🗑️ Select2 destroyed on', $el.attr('id')||$el.data('id'));
    }
    $el.select2({width:'100%',...opts});
    log('🎨 Select2 init on', $el.attr('id')||$el.data('id'));
  }
  function initRowEditors($row){
    log('🎯 Init editors for row', $row);
    $row.find('.dtp').each(function(){safeFlatpickr(this);});
    $row.find('.venue-select').each(function(){safeSelect2($(this));});
  }
  function venueOptionsHtml(selectedId){
    return VENUES.map(v=>
      `<option value="${v.id}" ${+selectedId===+v.id?'selected':''}>${v.name} (x${v.num_courts})</option>`
    ).join('');
  }

  // ========== RENDER FIXTURE ROW ==========
  function rowToRender(r){
    return {
      round:r.round??'',
      match:r.match??'',
      teams:`${r.p1} <span class="text-muted">vs</span> ${r.p2}`,
      datetime_html:`<input type="text" class="form-control form-control-sm dtp" data-id="${r.id}" value="${r.scheduled_at??''}" placeholder="YYYY-MM-DD HH:mm">`,
      venue_html:`<select class="form-select form-select-sm venue-select" data-id="${r.id}">${venueOptionsHtml(r.venue_id)}</select>`,
      court_html:`<input class="form-control form-control-sm court-input" data-id="${r.id}" value="${r.court_label??''}" maxlength="50">`,
      duration_html:`<input type="number" min="20" max="480" step="5" class="form-control form-control-sm dur-input text-center" data-id="${r.id}" value="${r.duration_min??''}">`,
      status_html:r.scheduled_at?'<span class="badge bg-success">Scheduled</span>':'<span class="badge bg-secondary">Unscheduled</span>',
      actions_html:`<button class="btn btn-sm btn-primary btn-save" data-id="${r.id}">Save</button>`
    };
  }

  // ========== LOAD DATA ==========
  function loadData(){
    log('📥 Loading all draw data for event', eventId);
    $.get(`{{ route('backend.team-schedule.all.data', $event->id) }}`)
      .done(res=>{
        log('✅ Data loaded:', res);
        renderDrawSections(res.draws, res.venues);
      })
      .fail(err=>{
        console.error('[ALL-SCHEDULE] ❌ Failed to load data', err);
        toastr.error('Failed to load data');
      });
  }

  // ========== RENDER ALL DRAW CARDS ==========
  function renderDrawSections(draws, venues){
    const container=$('#drawSections').empty();
    VENUES=venues;
    log('🎨 Rendering draw sections:', draws.length, 'draws found');

    draws.forEach(draw=>{
      const html=`
        <div class="card mb-4 draw-card" data-draw="${draw.id}">
          <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h5 class="mb-0">${draw.name}</h5>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-primary btn-auto-draw" data-id="${draw.id}">
                <i class="ti ti-calendar"></i> Auto
              </button>
              <button class="btn btn-sm btn-outline-danger btn-clear-draw" data-id="${draw.id}">
                <i class="ti ti-trash"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary btn-reload-draw" data-id="${draw.id}">
                <i class="ti ti-refresh"></i>
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="table-draw-${draw.id}" class="table table-sm table-striped table-bordered align-middle w-100">
              <thead class="table-light">
                <tr class="text-center">
                  <th>Round</th><th>Match</th><th>Home vs Away</th>
                  <th>Date/Time</th><th>Venue</th><th>Court</th>
                  <th>Dur</th><th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>`;
      container.append(html);
      log(`🧩 Rendering DataTable for draw ${draw.name} (ID ${draw.id})`);
      initDrawTable(draw);
    });
  }

  // ========== INIT DATATABLE FOR DRAW ==========
  function initDrawTable(draw){
    const rows=draw.fixtures.map(r=>rowToRender({...r,category:draw.name}));
    log(`📊 Initializing DataTable for ${draw.name}`, rows.length, 'rows');
    const table=$(`#table-draw-${draw.id}`).DataTable({
      paging:false, ordering:false, searching:false,
      data:rows,
      columns:[
        {data:'round',className:'text-center'},
        {data:'match',className:'text-center'},
        {data:'teams'},
        {data:'datetime_html'},
        {data:'venue_html'},
        {data:'court_html'},
        {data:'duration_html',className:'text-center'},
        {data:'status_html',className:'text-center'},
        {data:'actions_html',className:'text-center'}
      ],
      drawCallback:function(){
        log(`🔧 Editors init for draw ${draw.id}`);
        $(`#table-draw-${draw.id} tbody tr`).each(function(){initRowEditors($(this));});
      }
    });
  }

  // ========== SAVE FIXTURE ==========
  $('#drawSections').on('click','.btn-save',function(){
    const id=$(this).data('id');
    const dt=$(`.dtp[data-id="${id}"]`).val();
    const venue=$(`.venue-select[data-id="${id}"]`).val();
    const court=$(`.court-input[data-id="${id}"]`).val();
    const dur=$(`.dur-input[data-id="${id}"]`).val();
    log('💾 Saving fixture',{id,dt,venue,court,dur});

    $.post(`{{ route('backend.team-schedule.save', 0) }}`.replace('/0','/'+id),{
      _token:csrf, fixture_id:id,
      scheduled_at:dt||null, venue_id:venue||null,
      court_label:court||null, duration_min:dur||null
    })
    .done(res=>{log('✅ Fixture saved',res);toastr.success('Saved');loadData();})
    .fail(err=>{console.error('[ALL-SCHEDULE] ❌ Save failed',err);toastr.error('Save failed');});
  });

  // ========== AUTO-SCHEDULE ONE DRAW ==========
  $('#drawSections').on('click','.btn-auto-draw',function(){
    const drawId=$(this).data('id');
    const payload=buildPayload();
    log('🚀 Auto-schedule draw',drawId,payload);
    $.post(`/backend/team-schedule/auto/${drawId}`,payload)
      .done(res=>{
        log(`✅ Auto-scheduled draw ${drawId}`,res);
        toastr.success(`Auto-scheduled ${res.assigned?.length ?? 0} matches`);
        loadData();
      })
      .fail(err=>{console.error('[ALL-SCHEDULE] ❌ Auto-schedule failed',err);toastr.error('Auto-schedule failed');});
  });

  // ========== CLEAR DRAW ==========
  $('#drawSections').on('click','.btn-clear-draw',function(){
    const drawId=$(this).data('id');
    if(!confirm('Clear all schedules for this draw?'))return;
    log('🧹 Clearing draw',drawId);
    $.post(`/backend/team-schedule/clear/${drawId}`,{_token:csrf})
      .done(res=>{log(`✅ Cleared draw ${drawId}`,res);toastr.success(res.message);loadData();})
      .fail(err=>{console.error('[ALL-SCHEDULE] ❌ Clear failed',err);toastr.error('Clear failed');});
  });

  $('#drawSections').on('click','.btn-reload-draw',function(){
    log('🔄 Reload requested for all draws');
    loadData();
  });

  // ========== RANK → VENUE MAP ==========
  function renderRankVenueRows(){
    const $tb=$('#rankVenueRows').empty();
    log('🎯 Rendering rank→venue map',rankVenueMap);
    Object.entries(rankVenueMap).forEach(([rank,venueId])=>{
      $tb.append(`
        <tr data-rank="${rank}">
          <td><input type="number" class="form-control form-control-sm rank-input" value="${rank}"></td>
          <td><select class="form-select form-select-sm venue-select-row">
            ${VENUES.map(v=>`<option value="${v.id}" ${v.id==venueId?'selected':''}>${v.name}</option>`).join('')}
          </select></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger btnRemoveRankVenue"><i class="ti ti-trash"></i></button></td>
        </tr>`);
    });
    $('.venue-select-row').each(function(){safeSelect2($(this));});
  }

  $('#btnAddRankVenue').on('click',()=>{
    const nextRank=Object.keys(rankVenueMap).length+1;
    rankVenueMap[nextRank]=VENUES[0]?.id||null;
    log('➕ Added rank mapping',rankVenueMap);
    renderRankVenueRows();
  });
  $('#rankVenueRows').on('change','.rank-input,.venue-select-row',function(){
    const $r=$(this).closest('tr');
    const rank=$r.find('.rank-input').val();
    const venue=$r.find('.venue-select-row').val();
    if(rank&&venue){
      delete rankVenueMap[$r.data('rank')];
      rankVenueMap[rank]=venue;
      $r.attr('data-rank',rank);
      log('🔄 Updated rank mapping',rankVenueMap);
    }
  });
  $('#rankVenueRows').on('click','.btnRemoveRankVenue',function(){
    const rank=$(this).closest('tr').data('rank');
    delete rankVenueMap[rank];
    log('🗑️ Removed rank mapping',rank);
    renderRankVenueRows();
  });

  // ========== PAYLOAD BUILDER ==========
  function buildPayload(){
    const roundsRaw=$('#round').val();
    const rounds=roundsRaw?roundsRaw.split(',').map(r=>r.trim()).filter(r=>r.length>0):[];
    const payload={
      _token:csrf,
      start:$('#start').val(),
      end:$('#end').val(),
      duration:$('#duration').val(),
      gap:$('#gap').val(),
      round:rounds,
      venues:$('#venues').val()??[],
      rank_venue_map:rankVenueMap
    };
    log('📦 Built payload',payload);
    return payload;
  }

  // ========== AUTO-SCHEDULE ALL ==========
  $('#btnAutoAll').on('click',function(){
    const payload=buildPayload();
    log('🚀 Auto-schedule ALL draws',payload);
    $.post(`{{ route('backend.team-schedule.all.auto', $event->id) }}`,payload)
      .done(res=>{
        log('✅ Auto-schedule all success',res);
        toastr.success(`Auto-scheduled ${res.count??0} matches across all categories`);
        loadData();
      })
      .fail(err=>{console.error('[ALL-SCHEDULE] ❌ Auto-schedule all failed',err);toastr.error('Auto-schedule all failed');});
  });

  // ========== CLEAR ALL ==========
  $('#btn-clear-schedule').on('click',function(){
    if(!confirm('Clear ALL scheduled fixtures for this event?'))return;
    log('🧹 Clearing all schedules for event',eventId);
    $.post(`{{ route('backend.draw.schedule.clear', 0) }}`.replace('/0','/'+eventId),{_token:csrf})
      .done(r=>{log('✅ Cleared all',r);toastr.success(r.message||'Cleared');loadData();})
      .fail(err=>{console.error('[ALL-SCHEDULE] ❌ Failed to clear all',err);toastr.error('Failed to clear');});
  });

  // ========== INIT ==========
  log('⚙️ Initializing flatpickr and loading data');
  flatpickr('#start',fpOpts);
  flatpickr('#end',fpOpts);
  $('#btnReload').on('click',function(){log('🔄 Manual reload clicked');loadData();});
  loadData();
});
</script>
@endsection
