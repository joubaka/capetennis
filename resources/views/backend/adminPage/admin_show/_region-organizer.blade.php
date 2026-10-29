<style>
  .region-organizer-card { min-width:0; background:#f7faff; }
  .region-organizer-card [hidden] { display:none !important; }
  .region-organizer-card .select2-container { max-width:100%; }
  .region-organizer-identity { overflow-wrap:anywhere; }
  .region-organizer-card summary { cursor:pointer; min-height:44px; display:flex; align-items:center; gap:.5rem; font-weight:600; color:#173f78; }
</style>
<section class="region-organizer-card border rounded p-3 mt-3" aria-label="Regional organizer" data-region-organizers>
  @foreach($organizerRegions ?? [] as $organizer)
    @php($scope = $organizer['scope'])
    @php($manager = $organizer['manager'])
    @php($defaults = $organizer['defaults'])
    <div data-region-organizer="{{ $scope->region_id }}" @if(!$loop->first) hidden @endif>
      <div class="d-flex flex-wrap justify-content-between gap-2">
        <div class="region-organizer-identity"><strong>Regional organizer</strong><div>{{ $manager?->name ?: trim(($manager?->userName ?? '').' '.($manager?->userSurname ?? '')) ?: $manager?->email ?: 'Not assigned' }}</div>@if($manager?->email)<div class="small text-muted">{{ $manager->email }}</div>@endif</div>
        <span class="badge bg-label-{{ $scope->managerAssignment ? 'primary' : 'secondary' }} align-self-start">{{ $scope->managerAssignment ? 'Custom assignment' : ($manager ? 'Series organizer default' : 'Not assigned') }}</span>
      </div>
      <details class="mt-2" data-organizer-editor @if($errors->has('manager_user_id') || $errors->has('manager_email')) open @endif>
        <summary><i class="ti ti-user-cog" aria-hidden="true"></i>{{ $manager ? 'Change organizer' : 'Assign organizer' }}</summary>
        <p class="small text-muted mt-2">Assign an existing Cape Tennis user to {{ $scope->region?->region_name }} for this event. A player profile is not required.</p>
        <form method="POST" action="{{ route('backend.team-selection.manager.assign', [$event, $scope]) }}" class="row g-2 align-items-end">
          @csrf @method('PUT')
          <div class="col-12 col-lg-6"><label class="form-label" for="workspace-region-manager-{{ $scope->id }}">Select a system user</label><select id="workspace-region-manager-{{ $scope->id }}" name="manager_user_id" class="form-select" data-organizer-user data-search-url="{{ route('backend.team-selection.users.search', $event) }}"><option value=""></option>@if($scope->managerAssignment && $manager)<option value="{{ $manager->id }}" selected>{{ $manager->name ?: $manager->email }} · {{ $manager->email }}</option>@endif</select></div>
          <div class="col-12 col-sm-6 col-lg-3 d-grid"><button class="btn btn-primary">Assign to this region</button></div>
          <div class="col-12 col-sm-6 col-lg-3 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" @disabled($defaults->count() !== 1)>Use default</button></div>
        </form>
        <p class="small text-muted mt-2 mb-0">This grants access to this region. Existing event-wide or other-region access stays unchanged.</p>
        @if(!$scope->managerAssignment && $defaults->count() > 1)<div class="alert alert-warning mt-2 mb-0">Multiple default organizers were found. Assign the intended account explicitly.</div>@endif
        @if(auth()->user()->hasRole('super-user'))<a class="btn btn-sm btn-outline-secondary mt-3" href="{{ route('backend.team-selection.manager.preview', [$event, $scope]) }}">Preview regional organizer view</a>@endif
      </details>
    </div>
  @endforeach
</section>
