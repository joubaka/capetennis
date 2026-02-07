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
