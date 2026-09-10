@extends('layouts/layoutMaster')

@section('title', 'Review Player Profile')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
      @if($errors->any())
        <div class="alert alert-danger" role="alert">
          <strong>Please correct the player details before continuing.</strong>
          <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card">
        <div class="card-header">
          <h5 class="mb-1">Review Player Profile</h5>
          <p class="text-muted mb-0">{{ $player->full_name }} · profile #{{ $player->id }}</p>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            Confirm that these details are current and correct. The player will only be linked to your account and roster position after this form is saved.
          </div>

          <form method="POST" action="{{ route('player.claim.complete') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" value="{{ $player->name }}" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Surname</label>
                <input type="text" class="form-control" value="{{ $player->surname }}" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="dateOfBirth">Date of Birth <span class="text-danger">*</span></label>
                <input id="dateOfBirth" type="date" name="dateOfBirth" class="form-control @error('dateOfBirth') is-invalid @enderror" value="{{ old('dateOfBirth', $player->dateOfBirth ? \Carbon\Carbon::parse($player->dateOfBirth)->format('Y-m-d') : '') }}" max="{{ now()->subDay()->format('Y-m-d') }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                  <option value="">Select Gender</option>
                  <option value="Male" @selected(old('gender', (int) $player->gender === 1 ? 'Male' : '') === 'Male')>Male</option>
                  <option value="Female" @selected(old('gender', (int) $player->gender === 2 ? 'Female' : '') === 'Female')>Female</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="cellNr">Cell Number <span class="text-danger">*</span></label>
                <input id="cellNr" type="tel" name="cellNr" class="form-control @error('cellNr') is-invalid @enderror" value="{{ old('cellNr', $player->cellNr) }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $player->email) }}" placeholder="player@example.com">
              </div>
            </div>

            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" value="1" id="confirmed-details" name="confirmed_details" required>
              <label class="form-check-label" for="confirmed-details">I confirm that these player details are current and correct.</label>
            </div>

            <div class="d-flex justify-content-end mt-4">
              <button type="submit" class="btn btn-success">Save Profile and Continue to Payment</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
