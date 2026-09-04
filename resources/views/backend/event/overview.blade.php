@extends('layouts.backend')

@section('title', $event->name)

@section('page-style')
<style>
  .finance-card { transition: all 0.2s ease; }
  .finance-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
  .convenor-header { background: #fff9c4; border-left: 4px solid #f0c040; }
  .system-row td { background: #f8f9fa; font-style: italic; }
  .approved-badge { font-size: 0.7rem; }
  .budget-over { color: #dc3545; font-weight: 600; }
  .budget-under { color: #28a745; }
  .recon-table th { background: #343a40; color: #fff; }
  @media print {
    .no-print, .btn, .modal, .card-header .btn { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    body { font-size: 12px; }
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  @include('backend.event.partials.header', ['event' => $event])

  @if(!empty($operations))
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0">Event operations</h5>
          <span class="badge bg-label-primary text-capitalize">{{ $operations['lifecycle']['label'] }}</span>
        </div>
        <div class="row g-3 mb-3">
          @foreach($operations['counts'] as $label => $count)
            <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-2 h-100"><div class="small text-muted text-capitalize">{{ str_replace('_', ' ', $label) }}</div><div class="fs-4 fw-semibold">{{ $count }}</div></div></div>
          @endforeach
        </div>
        @if($operations['warnings']->isNotEmpty())
          <div class="list-group list-group-flush">
            @foreach($operations['warnings'] as $warning)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0" href="{{ $warning['action'] }}">
                <span><span class="badge bg-label-{{ $warning['severity'] === 'critical' ? 'danger' : 'warning' }} me-2">{{ $warning['severity'] }}</span>{{ $warning['reason'] }}</span>
                <span class="badge bg-secondary">{{ $warning['count'] }}</span>
              </a>
            @endforeach
          </div>
        @else
          <div class="alert alert-success mb-0">No outstanding operational warnings.</div>
        @endif
      </div>
    </div>
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
