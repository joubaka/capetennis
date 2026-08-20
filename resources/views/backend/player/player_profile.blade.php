@extends('layouts/layoutMaster')

@section('title', 'Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}} " />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />

@endsection

@section('page-style')
@include('multiend.player_profile_styles')
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/player-profile.js')}}"></script>
<script src="{{asset('assets/js/charts.js')}}"></script>

@endsection

@section('content')

<div class="row g-4 player-profile-page">
  <!-- User Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <!-- User Card -->
    <div class="card mb-4 player-summary-card">
      <div class="card-body">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column">
            <div class="player-avatar-placeholder mb-3" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($player->full_name, 0, 1)) }}</div>
            <div class="user-info text-center">
              <h4 class="mb-1">{{$player->full_name}}</h4>
              <span class="text-muted small">Player profile</span>

            </div>
          </div>
        </div>
        <div class="d-flex justify-content-around flex-wrap mt-3 pt-3 pb-4 border-bottom">
          <div class="player-stat d-flex align-items-center me-2 mt-3 gap-2">
            <span class="badge bg-label-primary  rounded"><i class="ti ti-checkbox ti-sm"></i></span>
            <div>
              <p class="mb-0 fw-semibold">{{$player->registrations->count()}}</p>
              <small>Registered events</small>
            </div>
          </div>
          <div class="player-stat d-flex align-items-center mt-3 mb-3 gap-2">
            <span class="badge bg-label-primary p-2 rounded"><i class="ti ti-briefcase ti-sm"></i></span>
            <div>
              <p class="mb-0 fw-semibold">{{$player->users()->count()}}</p>
              <small>Linked users</small>

            </div>

          </div>
          <div class="card shadow-none bg-transparent border w-100 mt-2">
            <div class="card-header">
              <h6 class="mb-0">Linked users</h6>
            </div>
            <div class="card-body">
              <ol class="list-group list-group-numbered linked-user-list mb-0">
                @foreach($player->users as $user)
                <li class="list-group-item"><span class="fw-medium">{{$user->name}}</span><br><small class="text-muted">{{$user->email}}</small></li>
                @endforeach
              </ol>
            </div>
          </div>


        </div>
        <p class="mt-4 mb-2 small text-uppercase text-muted fw-semibold">Player details</p>
        <div class="info-container">
          <ul class="list-unstyled">
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Name:</span>
              <span>{{$player->full_name}}</span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Email:</span>
              <span>{{$player->email}}</span>
            </li>



            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Contact:</span>
              <span>{{$player->cellNr}}</span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Gender:</span>
              <span>{{$player->gender == 1 ? 'Male':'Female'}}</span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Date of Birth:</span>
              <span>{{$player->dateOfBirth}}</span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Coach:</span>
              <span>{{$player->coach}}</span>
            </li>
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Memberships:</span>
              <span class="badge bg-label-{{ $player->subscriptions->count() > 0  ? 'success':'info'}}">{{ $player->subscriptions->count() > 0  ? $player->subscriptions[0]->type.' Membership':'Free Membership'}} </span>
            </li>
            @if($player->subscriptions->count() > 0)
            <li class="profile-detail-row">
              <span class="fw-semibold me-1">Expires:</span>
              <span class="badge bg-label-{{ $player->subscriptions->count() > 0  ? 'info':''}}">{{ $player->subscriptions->count() > 0  ? $player->subscriptions[0]->info->finish_date:'Free Membership'}} </span>
            </li>
            @endif
          </ul>
          <div class="d-grid mt-3">
            <a href="{{route('player.edit',$player->id)}}" class="btn btn-outline-primary waves-effect"><i class="ti ti-pencil me-1"></i>Edit player details</a>

          </div>
        </div>
      </div>
    </div>
    <!-- /User Card -->

  </div>
  <!--/ User Sidebar -->


  <!-- User Content -->
  <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
    @include('multiend.player_profile')
  </div>
  <!--/ User Content -->

</div>


@endsection
