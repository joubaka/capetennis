@php
  $fieldLabels = [
    'name' => 'First name', 'surname' => 'Surname', 'dateOfBirth' => 'Date of birth',
    'gender' => 'Gender', 'email' => 'Email', 'cellNr' => 'Cellphone',
    'coach' => 'Coach', 'profile_updated_at' => 'Profile updated',
  ];
  $registrationHistoryCount = collect($analysis['impact']['registration_history'])
    ->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['keep'] + $counts['remove']));
@endphp

<div class="alert alert-warning">
  <strong>Keep #{{ $analysis['keep']->id }} and permanently remove #{{ $analysis['remove']->id }}.</strong>
  The retained profile is the only one with linked history. This action cannot be undone from this screen.
</div>

@if($analysis['blockers'])
  <div class="alert alert-danger mb-0">
    <strong>Quick merge blocked.</strong>
    <ul class="mb-0 mt-2">@foreach($analysis['blockers'] as $blocker)<li>{{ $blocker['message'] }}</li>@endforeach</ul>
  </div>
@else
  <div class="row g-3 mb-3">
    <div class="col-sm-6"><div class="border rounded p-3"><span class="text-muted small d-block">Canonical profile</span><strong>#{{ $analysis['keep']->id }} {{ $analysis['keep']->full_name }}</strong><div class="small text-success">{{ $analysis['impact']['keep']['usage_total'] }} linked records retained</div></div></div>
    <div class="col-sm-6"><div class="border rounded p-3"><span class="text-muted small d-block">Empty duplicate</span><strong>#{{ $analysis['remove']->id }} {{ $analysis['remove']->full_name }}</strong><div class="small text-muted">No linked history</div></div></div>
  </div>

  @if($registrationHistoryCount > 0)
    <div class="alert alert-info py-2"><strong>Tournament safety:</strong> {{ $registrationHistoryCount }} registration-based references keep their registration IDs, results and ranking attribution.</div>
  @endif

  <form method="POST" action="{{ route('superadmin.player-duplicates.merge') }}">
    @csrf
    <input type="hidden" name="keep_player_id" value="{{ $analysis['keep']->id }}">
    <input type="hidden" name="remove_player_id" value="{{ $analysis['remove']->id }}">
    <input type="hidden" name="impact_digest" value="{{ $analysis['digest'] }}">

    @if(collect($analysis['fields'])->contains(fn($field) => $field['different']))
      <h6 class="mb-2">Choose final profile values</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
          <thead><tr><th>Field</th><th>Keep current value</th><th>Use empty profile value</th></tr></thead>
          <tbody>
          @foreach($analysis['fields'] as $field => $comparison)
            @if($comparison['different'])
              <tr>
                <th>{{ $fieldLabels[$field] ?? ucfirst($field) }}</th>
                <td><label class="d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="radio" name="field_sources[{{ $field }}]" value="keep" {{ $comparison['recommended'] === 'keep' ? 'checked' : '' }}><span>{{ filled($comparison['keep']) ? $comparison['keep'] : 'Blank' }}</span></label></td>
                <td><label class="d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="radio" name="field_sources[{{ $field }}]" value="remove" {{ $comparison['recommended'] === 'remove' ? 'checked' : '' }}><span>{{ filled($comparison['remove']) ? $comparison['remove'] : 'Blank' }}</span></label></td>
              </tr>
            @endif
          @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label">Audit reason</label>
      <textarea name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required>Confirmed one-sided-history duplicate after matching identity details.</textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Type exactly: <code>{{ $analysis['confirmation_phrase'] }}</code></label>
      <input name="confirmation" class="form-control" autocomplete="off" required>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="small text-muted">Restricted to Super Admins. Changed linked data will reject the merge.</span>
      <button class="btn btn-danger"><i class="ti ti-git-merge me-1"></i>Confirm permanent merge</button>
    </div>
  </form>
@endif
