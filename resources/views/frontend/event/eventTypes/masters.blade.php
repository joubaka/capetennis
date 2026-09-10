<style>
  .masters-public-event .masters-register-note{color:#7251d3;background:#f0ebff;border-radius:.25rem;padding:.35rem .65rem;display:inline-block;font-size:.78rem;font-weight:600}.masters-public-event .masters-category-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.masters-public-event .masters-category-card{border:1px solid #ebeaf0;border-radius:.45rem;overflow:hidden}.masters-public-event .masters-category-card h5{font-size:1rem}.masters-public-event .masters-player{border-top:1px solid #ebeaf0;padding:.55rem .7rem}.masters-public-event .masters-player:hover{background:#faf9fc}.masters-public-event .file-item:hover{background:#f8f9fa;border-radius:6px}@media(max-width:991.98px){.masters-public-event .masters-flow{display:flex;flex-direction:column}.masters-public-event .masters-flow>.col-xl-8,.masters-public-event .masters-flow>.col-xl-4{display:contents}.masters-public-event .masters-information{order:1}.masters-public-event .masters-about{order:2}.masters-public-event .masters-announcements{order:3}.masters-public-event .masters-documents{order:4}.masters-public-event .masters-registration{order:5}.masters-public-event .masters-invitations{order:6}}@media(max-width:768px){.masters-public-event .masters-category-grid{grid-template-columns:1fr}}
</style>
<div class="masters-public-event col-xl-12"><div class="row mb-4 masters-flow"><div class="col-xl-8 col-lg-7">
  <div class="masters-information">@include('frontend.event.partials.event-information')</div>
  <div class="masters-announcements">@include('frontend.event.partials.event-announcements')</div>
  <div class="card event-section-card event-card-padding p-4 mb-4 masters-invitations"><span class="masters-register-note">{{ $mastersRegistrationOpen ? 'Registration is open' : 'Player list published — registration is currently closed' }}</span><h4 class="mt-3 mb-1">Masters invitations</h4><p class="text-muted mb-3">Find your name in the correct category. @if($mastersRegistrationOpen)Select <strong>Register</strong> and complete payment to play. If you cannot participate, select <strong>Decline invitation</strong> to let us know you will not be playing.@else Registration will open when the organiser enables it.@endif</p><div class="masters-category-grid">
    @forelse($invitations->groupBy('category_event_id') as $categoryInvitations)
      @php($category=$categoryInvitations->first()->categoryEvent?->category?->name??'Masters category')<div class="masters-category-card"><div class="p-3 d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="ti ti-users me-1"></i>{{ $category }}</h5><span class="badge bg-label-primary">{{ $categoryInvitations->count() }}</span></div>
      @foreach($categoryInvitations as $position=>$invitation)@php($confirmed=$invitation->status===\App\Models\MastersInvitation::PAID_CONFIRMED) @php($pending=$invitation->status===\App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT)<div class="masters-player list-group-item d-flex justify-content-between align-items-center"><a href="{{ route('masters.invitations.show',$invitation) }}" class="text-body text-decoration-none flex-grow-1"><strong><span class="badge bg-label-secondary rounded-pill me-2">{{ $position+1 }}</span>{{ $invitation->player?->full_name??'Invited player' }}</strong><small class="d-block text-muted ms-4">Ranking position {{ $invitation->ranking_position }}</small></a>@if($confirmed)<span class="badge bg-label-success">Registered</span>@elseif($pending && $mastersRegistrationOpen)<a href="{{ route('masters.invitations.show',$invitation) }}" class="btn btn-sm btn-warning">Complete payment</a>@elseif(!$mastersRegistrationOpen)<span class="badge bg-label-secondary">Registration closed</span>@else<div class="d-flex gap-1"><form method="POST" action="{{ route('masters.invitations.accept',$invitation) }}">@csrf<button type="submit" class="btn btn-sm btn-warning">Register</button></form><form method="POST" action="{{ route('masters.invitations.decline',$invitation) }}" class="js-decline-invitation-form">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary">Decline invitation</button></form></div>@endif</div>@endforeach</div>
    @empty<div class="alert alert-info mb-0">Invited players will appear here once the Masters invitation batch has been sent.</div>@endforelse
  </div></div>
</div><div class="col-xl-4 col-lg-5">
  <div class="masters-about">@include('frontend.event.partials.event-about')</div>
  <div class="card event-section-card mb-4 shadow-sm masters-documents"><div class="card-header"><h6 class="text-uppercase mb-0"><i class="ti ti-folder text-primary me-2"></i>Documents</h6></div><div class="card-body pb-2">@forelse($event->files as $file)<div class="file-item border-bottom py-2"><a href="{{ route('events.documents.show', [$event, $file]) }}" target="_blank" class="fw-semibold text-dark text-decoration-none">{{ $file->name }}</a></div>@empty<div class="text-muted">No documents uploaded yet.</div>@endforelse</div></div>
  <div class="card event-section-card mb-4 masters-registration"><div class="card-body"><small class="text-uppercase">Registration</small><p class="small text-muted mt-3 mb-0">Invitations are limited to the players displayed in the Masters categories. Select your name to continue.</p></div></div>
</div></div></div>

@push('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush

@push('vendor-script')
  <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endpush

@push('page-script')
<script>
  document.querySelectorAll('.js-decline-invitation-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      Swal.fire({
        title: 'Decline invitation?',
        text: 'Please confirm that you cannot participate in this Masters event.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, decline invitation',
        cancelButtonText: 'Keep invitation',
        reverseButtons: true,
        customClass: { confirmButton: 'btn btn-danger ms-2', cancelButton: 'btn btn-outline-secondary' },
        buttonsStyling: false
      }).then(function (result) {
        if (result.isConfirmed) form.submit();
      });
    });
  });
</script>
@endpush
