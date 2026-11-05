@if($selectedPlayer)
  @php
    $dateOfBirth = substr((string) $selectedPlayer->dateOfBirth, 0, 10);
    $validBirthDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)
        && checkdate((int) substr($dateOfBirth, 5, 2), (int) substr($dateOfBirth, 8, 2), (int) substr($dateOfBirth, 0, 4));
  @endphp
  <section class="card mb-4" aria-labelledby="my-tennis-profile-details">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 id="my-tennis-profile-details" class="h5 mb-0">Player details</h2>
        <a class="btn btn-primary" href="{{ route('player.profile.edit', ['player' => $selectedPlayer, 'from' => 'my-tennis']) }}">Edit profile</a>
      </div>
      <dl class="ct-my-tennis-profile-fields mb-0">
        <div><dt>Name</dt><dd>{{ $selectedPlayer->full_name }}</dd></div>
        <div><dt>Date of birth</dt><dd>{{ $validBirthDate ? \Illuminate\Support\Carbon::parse($dateOfBirth)->format('j F Y') : 'Not added' }}</dd></div>
        <div><dt>Gender</dt><dd>{{ [1 => 'Male', 2 => 'Female'][$selectedPlayer->gender] ?? 'Not added' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $selectedPlayer->email ?: 'Not added' }}</dd></div>
        <div><dt>Mobile number</dt><dd>{{ $selectedPlayer->cellNr ?: 'Not added' }}</dd></div>
        @if($selectedPlayer->email_2)<div><dt>Additional notification email</dt><dd>{{ $selectedPlayer->email_2 }}</dd></div>@endif
        @if($selectedPlayer->email_3)<div><dt>Additional notification email</dt><dd>{{ $selectedPlayer->email_3 }}</dd></div>@endif
      </dl>
    </div>
  </section>
  @include('frontend.my-tennis.teamsheet-connection')
@else
  <section class="card mb-4">
    <div class="card-body">
      <h2 class="h5">Your player profile</h2>
      <p class="text-muted">Choose a linked player to view their details. Cape Tennis support can help with access to an existing player.</p>
      <a class="btn btn-primary" href="{{ route('my.tennis', ['tab' => 'manage']) }}">Manage player access</a>
    </div>
  </section>
@endif
