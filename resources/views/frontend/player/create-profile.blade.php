@extends('layouts/layoutMaster')

@section('title', 'Add Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
          <ul class="mb-0">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="ti ti-user-plus me-2"></i>Add Player Profile</h5>
          <small class="text-muted">The profile will automatically be linked to your account.</small>
        </div>

        <div class="card-body">
          <form method="POST" action="{{ route('player.profile.store') }}">
            @csrf

            <div class="row g-3">

              <div class="col-md-6">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Surname <span class="text-danger">*</span></label>
                <input type="text" name="surname" class="form-control @error('surname') is-invalid @enderror"
                       value="{{ old('surname') }}" required>
                @error('surname')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="text" id="dateOfBirth" name="dateOfBirth"
                       class="form-control @error('dateOfBirth') is-invalid @enderror"
                       value="{{ old('dateOfBirth') }}" placeholder="YYYY-MM-DD" required>
                @error('dateOfBirth')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                  <option value="">Select Gender</option>
                  <option value="1" {{ old('gender') == '1' ? 'selected' : '' }}>Male</option>
                  <option value="2" {{ old('gender') == '2' ? 'selected' : '' }}>Female</option>
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Cell Number</label>
                <input type="text" name="cellNr" class="form-control @error('cellNr') is-invalid @enderror"
                       value="{{ old('cellNr') }}">
                @error('cellNr')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

            </div>

            <div class="mt-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Save Profile
              </button>
              <a href="{{ route('backend.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
  flatpickr('#dateOfBirth', {
    dateFormat: 'Y-m-d',
    maxDate: 'today',
    allowInput: true,
  });
</script>
@endsection
