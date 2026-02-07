@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')

@endsection

@section('page-script')

@endsection

@section('content')

<div class="container">

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header" style="background: gray; color:#f1f7fa; font-weight:bold;">
                   Import file
                </div>
                <div class="card-body">
                   <form action="{{ route('backend.team.import.no.profile') }}"
      method="post"
      enctype="multipart/form-data">
  @csrf

  <div class="row mb-3">
    <label class="col-sm-3 col-form-label">File</label>
    <div class="col-sm-9">
      <input type="file" class="form-control" name="file" required>
      @error('file')
        <div class="text-danger small">{{ $message }}</div>
      @enderror
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-sm-9 offset-sm-3">
      <button type="submit" class="btn btn-success">Submit</button>
    </div>
  </div>
</form>

                    <div class="card">
<div class="card-body">
    team_id,rank,name,surname,paystatus,
</div>



                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
