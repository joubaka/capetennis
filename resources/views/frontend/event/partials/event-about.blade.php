@if(
  $sDate ||
  $eDate ||
  $formatEntryLine ||
  $formatWithdrawalLine ||
  $event->entryFee !== null ||
  $event->eventCategories->isNotEmpty() ||
  $event->organizer ||
  $event->email
)
  <div class="card event-section-card mb-4">
    <div class="card-body">
      <small class="card-text text-uppercase">About</small>

      <ul class="list-unstyled mb-4 mt-3">
        @if($sDate)
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span class="fw-bold">Start Date:</span>
            <span class="badge bg-label-success">{{ $sDate }}</span>
          </li>
        @endif

        @if($eDate)
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span class="fw-bold">End Date:</span>
            <span class="badge bg-label-success">{{ $eDate }}</span>
          </li>
        @endif

        <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
          <i class="ti ti-users" aria-hidden="true"></i>
          <span class="fw-bold">Confirmed entries:</span>
          <span class="badge bg-label-info">{{ number_format($entryCount) }}</span>
        </li>

        @if($formatEntryLine)
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-check" aria-hidden="true"></i>
            <span class="fw-bold">Entry deadline:</span>
            <span class="badge bg-label-warning">{{ $formatEntryLine }}</span>
          </li>
        @endif

        @if($formatWithdrawalLine)
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-x" aria-hidden="true"></i>
            <span class="fw-bold">Withdrawal deadline:</span>
            <span class="badge bg-label-danger">{{ $formatWithdrawalLine }}</span>
          </li>
        @endif

        @if($event->entryFee !== null || $event->eventCategories->contains(fn ($categoryEvent) => $categoryEvent->entry_fee !== null))
          @include('frontend.event.partials.entry-fees')
        @endif
      </ul>

      @if($event->organizer || $event->email)
        <small class="card-text text-uppercase">Contact</small>
        <ul class="list-unstyled mb-0 mt-3">
          @if($event->organizer)
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-phone-call mt-1" aria-hidden="true"></i>
              <span><strong>Organizer:</strong> {{ $event->organizer }}</span>
            </li>
          @endif

          @if($event->email)
            <li class="d-flex align-items-start gap-2 mb-0">
              <i class="ti ti-mail mt-1" aria-hidden="true"></i>
              <span class="text-break"><strong>Email:</strong> <a href="mailto:{{ $event->email }}">{{ $event->email }}</a></span>
            </li>
          @endif
        </ul>
      @endif
    </div>
  </div>
@endif
