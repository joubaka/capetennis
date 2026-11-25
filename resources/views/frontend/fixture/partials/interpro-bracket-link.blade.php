@if(\App\Services\Draw\InterproRoundRobinFormat::selected($draw))
  <a href="{{ route('public.roundrobin.show', $draw) }}#playoffs" class="btn btn-primary" data-playoff-bracket-link>
    <i class="ti ti-tournament me-1" aria-hidden="true"></i>View playoff bracket
  </a>
@endif
