<!-- ===================================================== -->
<!-- 🔹 EDIT USER MODAL (AJAX, no validation) -->
<!-- ===================================================== -->
<div class="modal fade" id="editUser" tabindex="-1" aria-labelledby="editUserLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h3 class="mb-2">
            <i class="ti ti-user-edit me-1"></i> Edit User Information
          </h3>
          <p class="text-muted">Updating user details will receive a privacy audit.</p>
        </div>

        <form id="editUserForm" method="POST" action="javascript:void(0);" class="row g-3">
          @csrf

          <div class="col-12 col-md-6">
            <label class="form-label" for="userName">First Name</label>
            <input type="text" id="userName" name="userName" class="form-control"
                   placeholder="John" value="{{ Auth::user()->userName ?? '' }}">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="userSurname">Last Name</label>
            <input type="text" id="userSurname" name="userSurname" class="form-control"
                   placeholder="Doe" value="{{ Auth::user()->userSurname ?? '' }}">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="example@domain.com" value="{{ Auth::user()->email }}">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="cell_nr">Phone Number</label>
            <input type="text" id="cell_nr" name="cell_nr" class="form-control"
                   placeholder="202 555 0111" value="{{ Auth::user()->cell_nr ?? '' }}">
          </div>

          <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">
              <i class="ti ti-check me-1"></i> Save Changes
            </button>
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
              <i class="ti ti-x me-1"></i> Cancel
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
