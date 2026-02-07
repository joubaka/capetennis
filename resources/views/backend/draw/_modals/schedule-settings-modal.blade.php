<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="settingsForm" action="{{route('schedule.create')}}" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="defaultFormControlInput" class="form-label">Match Duration in minutes</label>
                        <input type="text" class="form-control" name="duration" id="defaultFormControlInput" aria-describedby="defaultFormControlHelp" />

                    </div>
                    <div class="mb-3">
                        <label for="defaultFormControlInput" class="form-label">Number of courts</label>
                        <input type="text" class="form-control" name="numcourts" id="defaultFormControlInput" aria-describedby="defaultFormControlHelp" />

                    </div>
                    <div class="mb-3">
                        <label for="smallSelect" class="form-label">Venue</label>
                        <select id="venueSelect" name="venue" class="form-select form-select-sm">
                            <option>Select Venue</option>
                            @foreach($venues as $venue)
                            <option value="{{$venue->id}}">{{$venue->name}}</option>
                            @endforeach
                        </select>
                    </div>

                  
                    <div class="mb-3">
                        <label for="flatpickr-datetime" class="form-label">First Match</label>
                        <input type="text" name="firstMatchTime" class="form-control flatpickr-datetime" placeholder="{{Carbon\Carbon::now()}}"  />
                    </div>
                    <div class="mb-3">
                        <label for="flatpickr-datetime" class="form-label">Last Match</label>
                        <input type="text"  name="lastMatchTime"  class="form-control flatpickr-datetime" placeholder="{{Carbon\Carbon::now()}}"  />
                    </div>
                   
                    <input type="hidden" name="draw_id" value="{{$draw->id}}">
                    @csrf
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="applySchedule" class="btn btn-primary" data-bs-dismiss="modal">Apply settings</button>
                </div>

            </form>
        </div>
    </div>
</div>