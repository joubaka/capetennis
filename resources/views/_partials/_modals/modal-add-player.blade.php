<!-- Modal -->
<div class="modal fade" id="addPlayerModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <form class="formPlayer" method="post">
                    @csrf
                    <div class="card">


                        <h5 class="card-header">Create Player</h5>


                        <div class="card-body">
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Player Name</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="player_name" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Player Surname</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="player_surname" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-date-input" class="col-md-2 col-form-label">Date of Birth</label>
                                <div class="col-md-10">
                                    <input class="form-control" type="date" name="dob" value="2021-06-18" id="html5-date-input">
                                </div>
                            </div>



                            <div class="mb-3 row">
                                <label for="html5-email-input" class="col-md-2 col-form-label">Email</label>
                                <div class="col-md-10">
                                    <input class="form-control" name="email" type="email" value="john@example.com" id="html5-email-input">
                                </div>
                            </div>




                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Cell nr.</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="cell_nr" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Gender</label>
                                <select name="gender" class="select2gender select2 form-select form-select-lg select2-hidden-accessible" data-allow-clear="true" tabindex="-1" aria-hidden="true">

                                    <option value="1">Male</option>
                                    <option value="2">Female</option>
                                </select>
                            </div>


                        </div>

                    </div>
                    <div type="button" class="btn btn-primary btn-sm mt-4" id="createPlayerButton" data-dismiss="modal">Create Player</div>
                </form>
                <!-- /Full Editor -->



                <input type="hidden" id="event_id" value="{{$event->id}}">

                <!-- /Full Editor -->
            </div>
        </div>
    </div>
</div>
