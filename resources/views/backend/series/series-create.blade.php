@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')

@endsection

@section('page-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')


@endsection

@section('content')
<div class="card">
    <div class="card-header"></div>
    <div class="card-body">
        <form action="{{route('series.store')}}" method="post">
            @csrf
            <div class="col-6">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Series Name</label>
                    <div class="col-md-8">
                        <input class="form-control" name="name" type="text" value="" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Best nr of Scores </label>
                    <div class="col-md-8">
                        <select name="numScores" id="defaultSelect" class="form-select">
                            <option>Please select</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Ranking Type </label>
                    <div class="col-md-8">
                        <select name="rankType" id="defaultSelect" class="form-select">
                            <option>Please select</option>
                            @foreach($rankingTypes as $rankType)
                            <option value="{{$rankType->id}}">{{$rankType->type}}</option>
                            @endforeach

                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                  <label for="series-year" class="col-md-4 col-form-label">Year</label>
                  <div class="col-md-8">
                    <select name="year" id="series-year" class="form-select" required>
                      <option value="">Select Year</option>
                      @for ($y = now()->year; $y <= 2030; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                      @endfor
                    </select>
                  </div>
                </div>

            </div>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>

    </div>
</div>



@endsection
