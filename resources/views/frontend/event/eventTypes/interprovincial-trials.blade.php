<div class="col-xl-12">
  <div class="row g-4">
    <div class="col-lg-8">
      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')
      <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Trial nominations</h5></div>
        <div class="card-body">
          @if($publishedCategories->isEmpty())
            <p class="text-muted mb-0">No trial nominations have been published.</p>
          @else
            <div class="row g-3">
              @foreach($publishedCategories as $categoryEvent)
                <div class="col-md-6">
                  <section class="border rounded p-3 h-100">
                    <h6>{{ $categoryEvent->category?->name ?? 'Category' }}</h6>
                    <ul class="list-group list-group-flush">
                      @forelse($categoryEvent->nominations as $nomination)
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                          <span>{{ $nomination->player?->name }} {{ $nomination->player?->surname }}</span>
                          @auth
                            @php
                              $trialInvitation = $nomination->actionableInvitation;
                              $ownsPlayer = in_array((int) $nomination->player_id, auth()->user()->ownedPlayerIds(), true);
                              $registrationOpen = $event->published && $event->hasOpenRegistrationLifecycle() && (int) $event->signUp === 1
                                && (!$event->registrationClosesAt() || now()->lte($event->registrationClosesAt()->endOfDay()));
                            @endphp
                            @if($ownsPlayer && $trialInvitation && in_array($trialInvitation->status, ['queued', 'sent'], true))
                              @if($registrationOpen)
                                <form method="POST" action="{{ route('interprovincial-trials.invitations.register', $trialInvitation) }}">@csrf<button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                              @else
                                <span class="badge bg-label-secondary">Registration closed</span>
                              @endif
                            @endif
                          @endauth
                        </li>
                      @empty
                        <li class="list-group-item px-0 text-muted">No nominated players.</li>
                      @endforelse
                    </ul>
                  </section>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      @include('frontend.event.partials.event-about')
    </div>
  </div>
</div>
