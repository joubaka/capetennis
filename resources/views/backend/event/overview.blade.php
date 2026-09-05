@extends('layouts.backend')

@section('title', $event->name)

@section('page-style')
<style>
  .event-overview-page {
    --event-navy: #102a43;
    --event-navy-deep: #0b1f33;
    --event-blue: #1f5f82;
    --event-teal: #17a398;
    --event-orange: #f28c3c;
    --event-canvas: #edf2f6;
    --event-line: #d7e0e8;
  }
  .event-operations {
    overflow: hidden;
    border: 0 !important;
    box-shadow: 0 14px 36px rgba(16, 42, 67, .12) !important;
  }
  .event-operations .card-body { padding: 0; }
  .event-operations__heading {
    padding: 22px 26px;
    color: #fff;
    background: linear-gradient(110deg, var(--event-navy-deep), var(--event-navy));
  }
  .event-operations__heading h2 { color: #fff !important; font-size: 19px; }
  .event-operations__heading p { margin: 4px 0 0; color: #afc2d2; font-size: 13px; }
  .event-operations__heading .badge {
    padding: 8px 12px;
    color: #d9fffa !important;
    background: rgba(23, 163, 152, .2) !important;
    border: 1px solid rgba(101, 212, 202, .32);
  }
  .event-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    padding: 22px 26px;
    background: #f5f8fa;
    border-bottom: 1px solid var(--event-line);
  }
  .event-kpi {
    position: relative;
    min-width: 0;
    padding: 16px 15px 14px;
    overflow: hidden;
    border: 1px solid var(--event-line);
    border-radius: 12px;
    background: #fff;
  }
  .event-kpi::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: var(--event-blue);
  }
  .event-kpi[data-metric="paid"]::before { background: var(--event-teal); }
  .event-kpi[data-metric="unpaid"]::before,
  .event-kpi[data-metric="withdrawals"]::before { background: var(--event-orange); }
  .event-kpi[data-metric="pending_refunds"]::before { background: #c64b55; }
  .event-kpi__label { color: #61758a; font-size: 12px; font-weight: 650; text-transform: uppercase; letter-spacing: .45px; }
  .event-kpi__value { margin-top: 5px; color: var(--event-navy); font-size: 28px; font-weight: 750; line-height: 1; }
  .event-warnings { padding: 8px 26px 14px; }
  .event-warnings__label { margin: 12px 0 5px; color: #61758a; font-size: 11px; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; }
  .ct-backend .event-warning {
    gap: 16px;
    min-height: 54px;
    padding: 10px 0;
    color: var(--event-navy);
    border-color: var(--event-line);
  }
  .event-warning__copy { display: flex; align-items: center; min-width: 0; }
  .event-warning__copy > .ti { flex: 0 0 auto; margin-right: 10px; color: var(--event-orange); font-size: 20px; }
  .event-warning.is-critical .event-warning__copy > .ti { color: #c64b55; }
  .event-warning__severity { min-width: 68px; margin-right: 10px; text-align: center; }
  .event-warning__count { min-width: 34px; padding: 6px 9px; color: #fff; background: var(--event-navy) !important; }
  .event-warning:hover .event-warning__count { background: var(--event-teal) !important; }
  .event-overview-page > .card:not(.event-operations) { box-shadow: 0 10px 28px rgba(16, 42, 67, .09); }
  .finance-card { transition: all 0.2s ease; }
  .finance-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
  .convenor-header { background: #fff9c4; border-left: 4px solid #f0c040; }
  .system-row td { background: #f8f9fa; font-style: italic; }
  .approved-badge { font-size: 0.7rem; }
  .budget-over { color: #dc3545; font-weight: 600; }
  .budget-under { color: #28a745; }
  .recon-table th { background: #343a40; color: #fff; }
  @media (max-width: 1199.98px) {
    .event-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 767.98px) {
    .event-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 16px; }
    .event-operations__heading { padding: 19px 18px; }
    .event-warnings { padding-inline: 18px; }
    .event-warning__copy { align-items: flex-start; }
    .event-warning__copy > .ti { display: none; }
    .event-warning__severity { min-width: 0; margin-right: 8px; font-size: 10px; }
  }
  @media (max-width: 399.98px) {
    .event-kpi-grid { gap: 9px; }
    .event-kpi { padding: 14px 12px; }
    .event-kpi__value { font-size: 25px; }
  }
  @media print {
    .no-print, .btn, .modal, .card-header .btn { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    .event-workspace-chrome .ct-page-header,
    .event-workspace-chrome .ct-context-nav,
    .event-operations__heading { color: #172e45 !important; background: #fff !important; box-shadow: none !important; }
    .event-workspace-chrome .ct-page-header h1,
    .event-operations__heading h2 { color: #172e45 !important; }
    .event-workspace-chrome .ct-page-meta,
    .event-operations__heading p { color: #66788a !important; }
    body { font-size: 12px; }
  }
</style>
@endsection

@section('content')
<div class="container-xl event-overview-page">

  @include('backend.event.partials.header', ['event' => $event])

  @if(!empty($operations))
    <section class="card event-operations mb-4" aria-labelledby="event-operations-title">
      <div class="card-body">
        <div class="event-operations__heading d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h2 class="mb-0" id="event-operations-title">Event operations</h2>
            <p>Registration, payment and draw readiness at a glance</p>
          </div>
          <span class="badge bg-label-primary text-capitalize">{{ $operations['lifecycle']['label'] }}</span>
        </div>
        <div class="event-kpi-grid">
          @foreach($operations['counts'] as $label => $count)
            <div class="event-kpi" data-metric="{{ $label }}">
              <div class="event-kpi__label">{{ str_replace('_', ' ', $label) }}</div>
              <div class="event-kpi__value">{{ $count }}</div>
            </div>
          @endforeach
        </div>
        @if($operations['warnings']->isNotEmpty())
          <div class="event-warnings">
            <div class="event-warnings__label">Needs attention</div>
            @foreach($operations['warnings'] as $warning)
              <a class="event-warning {{ $warning['severity'] === 'critical' ? 'is-critical' : '' }} list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ $warning['action'] }}">
                <span class="event-warning__copy">
                  <i class="ti {{ $warning['severity'] === 'critical' ? 'ti-alert-circle' : 'ti-alert-triangle' }}" aria-hidden="true"></i>
                  <span class="badge event-warning__severity bg-label-{{ $warning['severity'] === 'critical' ? 'danger' : 'warning' }}">{{ $warning['severity'] }}</span>
                  <span>{{ $warning['reason'] }}</span>
                </span>
                <span class="badge event-warning__count">{{ $warning['count'] }}</span>
              </a>
            @endforeach
          </div>
        @else
          <div class="p-4"><div class="alert alert-success mb-0">No outstanding operational warnings.</div></div>
        @endif
      </div>
    </section>
  @endif

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
      <i class="ti ti-circle-check me-1"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($event->isIndividual())
    @include('backend.event.individual.index')
  @elseif($event->isTeam())
    @include('backend.event.team.index')
    @include('backend.event.partials.finances')
  @elseif($event->isCamp())
    @include('backend.event.camp.index')
  @else
    <div class="alert alert-warning">Unknown event type</div>
  @endif

</div>

@endsection

@section('page-script')
<script>
  document.querySelectorAll('input[name="quantity"], input[name="unit_price"]').forEach(function(el) {
    el.addEventListener('input', function() {
      const form = el.closest('form');
      const qty  = parseFloat(form.querySelector('input[name="quantity"]')?.value) || 0;
      const up   = parseFloat(form.querySelector('input[name="unit_price"]')?.value) || 0;
      const amtInput = form.querySelector('input[name="amount"]');
      if (amtInput && qty > 0 && up > 0) {
        amtInput.value = (qty * up).toFixed(2);
      }
    });
  });
</script>
@endsection
