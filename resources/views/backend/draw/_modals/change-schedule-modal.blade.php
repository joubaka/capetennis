<!-- Modal -->
<div class="modal fade" id="change-schedule-modal" tabindex="-1" aria-labelledby="change-schedule-modal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> <span id="player1name-modal"></span> vs <span id="player2name-modal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="editScheduleForm" action="{{route('schedule.update.time')}}" method="get">
                <div class="modal-body">
                    <div class="mb-3">


                    </div>


                    <div class="mb-3">
                        <label for="flatpickr-datetime" class="form-label">Time and Date</label>
                        <input type="text" name="time" id="time" class="form-control flatpickr-datetime" />
                    </div>
                    <div class="mb-3">
                        <select id="smallSelect" name="venue_id" class="form-select form-select-sm">

                            @foreach($draw->venues as $venue)
                            <option value="{{$venue->id}}">{{$venue->name}}</option>
                            @endforeach
                        </select>

                    </div>

                    <input type="hidden" name="draw_id" value="{{$draw->id}}">
                    <input type="hidden" name="team_order_play_id" id="editFixtureId">
                  <input type="hidden" name="oopId" id="oopId">
                  <input type="hidden" name="typeFixture" id="typeFixture">
                    @csrf
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Apply settings</button>
                </div>

            </form>
        </div>
    </div>
</div>
