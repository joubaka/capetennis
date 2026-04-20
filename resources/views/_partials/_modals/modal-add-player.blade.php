<!-- Add Player Modal -->
<div class="modal fade" id="addPlayerModal" tabindex="-1" aria-labelledby="addPlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="addPlayerModalLabel">
                    <i class="ti ti-user-plus me-2"></i>Create New Player
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="formPlayer" method="post" onsubmit="return false;">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-4">Fill in the player's details below. Fields marked with <span class="text-danger">*</span> are required.</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal_player_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="player_name" id="modal_player_name" placeholder="e.g. John" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_player_surname" class="form-label">Surname <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="player_surname" id="modal_player_surname" placeholder="e.g. Smith" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" name="dob" id="modal_dob" max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="modal_gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="1">Male</option>
                                <option value="2">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_email" class="form-label">Email</label>
                            <input class="form-control" name="email" type="email" id="modal_email"
                                   value="{{ Auth::user()->email ?? '' }}" placeholder="e.g. player@email.com">
                        </div>
                        <div class="col-md-6">
                            <label for="modal_cell_nr" class="form-label">Cell Number</label>
                            <input class="form-control" type="tel" name="cell_nr" id="modal_cell_nr"
                                   value="{{ Auth::user()->cellNr ?? Auth::user()->phone ?? '' }}" placeholder="e.g. 0821234567">
                        </div>
                    </div>

                    <input type="hidden" id="event_id" value="{{ $event->id ?? '' }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="createPlayerButton">
                        <i class="ti ti-check me-1"></i>Create Player
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
