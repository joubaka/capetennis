@extends('layouts/layoutMaster')

@section('title', 'Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}} " />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>

@endsection

@section('page-script')
<script src="{{asset('assets/js/edit-player.js')}}"></script>

@endsection

@section('content')
<form action="{{route('player.update',$player->id)}}" method="POST">
    @csrf
    @method('PATCH')
    <div class="card">


        <h5 class="card-header">Edit Player</h5>

        <div class="col-6">
            <div class="card-body">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Name</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="player_name" value="{{$player->name}}" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Surname</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="player_surname" value="{{$player->surname}}" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-date-input" class="col-md-2 col-form-label">Date of Birth</label>
                    <div class="col-md-10">
                        <input class="form-control" type="date" name="dob" value="{{$player->dateOfBirth}}" id="html5-date-input">
                    </div>
                </div>



                <div class="mb-3 row">
                    <label for="html5-email-input" class="col-md-2 col-form-label">Email</label>
                    <div class="col-md-10">
                        <input class="form-control" name="email" type="email" value="{{$player->email}}" id="html5-email-input">
                    </div>
                </div>




                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Cell nr.</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="cell_nr" value="{{$player->cellNr}}" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3">

                    <label for="html5-text-input" class="col-md-4 col-form-label">Gender</label>

                    <select name="gender" class="select2gender select2 form-select form-select-lg select2-hidden-accessible" data-allow-clear="true" tabindex="-1" aria-hidden="true">

                        <option value="1" {{$player->gender == 1 ? 'selected':''}}>Male</option>
                        <option value="2" {{$player->gender == 2 ? 'selected':''}}>Female</option>
                    </select>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Player Coach</label>
                    <div class="col-md-8">
                        <input class="form-control" type="text" name="coach" value="{{$player->coach ? $player->coach:''}}" id="html5-text-input">
                    </div>
                </div>
            </div>


        </div>

    </div>
    <button type="submit" class="btn btn-primary btn-sm mt-4">Confirm changes</button>
</form>


@endsection