<!-- Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="teamForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Team Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    @isset($event)<input type="hidden" name="event_id" value="{{ $event->id }}">
                    @if(!app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event))
                      <label class="form-label" for="new-team-category">Category</label><select id="new-team-category" name="category_event_id" class="form-select" required>@foreach($event->eventCategories as $teamCategory)<option value="{{ $teamCategory->id }}">{{ $teamCategory->category?->name }}</option>@endforeach</select>
                    @endif
                    @endisset
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Name</label>
                        <input name="team_name" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Number of Players in Team</label>
                        <input name="num_players" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Year</label>
                        <input name="year" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <input type="hidden" name="published" value="0">
                    <input id="region_id" type="hidden" name="region_id" value="">



                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="updateTeamButton" data-bs-dismiss="modal">Add Team</button>
                </div>
            </div>

        </form>

    </div>
</div>
