@extends('layouts/layoutMaster')

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
