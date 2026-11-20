@extends('layouts/contentNavbarLayout')
@section('title','Camp coach nominations')
@section('content')
<div data-camp-workflow class="container-xxl container-p-y" style="max-width:760px;overflow-wrap:anywhere"><h1 class="h3">{{ $link->event->name }}</h1><h2 class="h5">Nominations from {{ $link->coach_name }}</h2><p>Nominate players aged {{ $config->coach_min_age }}–{{ $config->coach_max_age }} during {{ $link->event->start_date->year-1 }}. The organiser reviews every submission before any invitation goes out.</p>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<form class="card" method="POST" action="{{ route('camp.coach.submit',$token) }}">@csrf<input type="hidden" name="request_token" value="{{ $requestToken }}"><div class="card-body"><div class="row g-3">
@foreach(['name'=>'Player first name','surname'=>'Player surname','date_of_birth'=>'Date of birth','guardian_name'=>'Parent/guardian name','guardian_email'=>'Parent/guardian email'] as $field=>$label)<div class="col-12 col-md-6"><label class="form-label" for="coach-{{ $field }}">{{ $label }}</label><input class="form-control" id="coach-{{ $field }}" name="{{ $field }}" required maxlength="255" type="{{ $field==='date_of_birth'?'date':($field==='guardian_email'?'email':'text') }}" value="{{ old($field) }}"></div>@endforeach
<div class="col-12"><label class="form-label" for="coach-gender">Gender</label><select class="form-select" name="gender" id="coach-gender" required><option value="1">Boy</option><option value="2" @selected(old('gender')==2)>Girl</option></select></div><div class="col-12"><label class="form-label" for="coach-reason">Why are you recommending this player?</label><textarea class="form-control" id="coach-reason" name="reason" maxlength="3000" rows="3">{{ old('reason') }}</textarea></div><div class="col-12"><button class="btn btn-primary" style="min-height:44px">Submit nomination for approval</button></div></div></div></form><p class="small mt-3">This link does not show other players or families. Submitting does not create an entry or guarantee a place. Link closes {{ $link->expires_at->format('j M Y H:i') }}.</p></div>
@endsection

@section('page-script')
@include('backend.practice-camps.feedback')
@endsection
