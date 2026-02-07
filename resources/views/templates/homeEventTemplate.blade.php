<div class="d-none" id="eventInfo">
  <div class="card mb-4">
    <div class="card-header eventHeader" style="background-color:#004177">
      <h3>
        <a class="eventName p-2 rounded"></a>
      </h3>
    </div>

    <div class="card-body mt-2">
      <div class="row m-2">

        {{-- Logo --}}
        <div class="col-xl-6 order-0 order-xl-0">
          <div class="logo"></div>
        </div>

        {{-- Dates --}}
        <div class="col-xl-6 order-1 order-xl-0">

          <div class="mb-2">
            <h6 class="mb-1">
              <span class="me-2">Event start:</span>
              <span class="start_date badge bg-label-success"></span>
            </h6>
          </div>

          <div class="mb-2 pt-1">
            <h6 class="mb-1">
              <span class="me-2">Event end:</span>
              <span class="end_date badge bg-label-success"></span>
            </h6>
          </div>

          <div class="mb-3 pt-1">
            <h6 class="mb-1">
              <span class="me-2">Deadline:</span>
              <span class="badge bg-label-warning deadline"></span>
            </h6>
          </div>

        </div>

        {{-- Actions --}}
        <div class="col-12 order-2 order-xl-0 buttons">
          <button class="btn btn-label-success cancel-subscription waves-effect">
            Sign-Up
          </button>
        </div>

      </div>
    </div>
  </div>
</div>
