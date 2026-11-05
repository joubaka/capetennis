<section class="region-organizer-card" aria-label="Regional organizer" data-region-organizers>
  @foreach($organizerRegions ?? [] as $organizer)
    @php($scope = $organizer['scope'])
    @php($manager = $organizer['manager'])
    @php($defaults = $organizer['defaults'])
    <div data-region-organizer="{{ $scope->region_id }}" @if(!$loop->first) hidden @endif>
      <details data-organizer-editor @if($errors->has('manager_user_id') || $errors->has('manager_email')) open @endif>
        <summary class="btn btn-outline-secondary" aria-label="Regional organizer settings">
          <i class="ti ti-user-cog" aria-hidden="true"></i> Organizer
        </summary>
        <div class="small mt-2">Regional organizer <strong>{{ $manager?->name ?: trim(($manager?->userName ?? '').' '.($manager?->userSurname ?? '')) ?: $manager?->email ?: 'Not assigned' }}</strong></div>
        <div class="fw-semibold">{{ $manager ? 'Change organizer' : 'Assign organizer' }}</div>
        @if($manager?->email)<div class="small text-muted region-organizer-identity">{{ $manager->email }}</div>@endif
        <span class="badge bg-label-{{ $scope->managerAssignment ? 'primary' : 'secondary' }}">{{ $scope->managerAssignment ? 'Custom assignment' : ($manager ? 'Series organizer default' : 'Not assigned') }}</span>
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
