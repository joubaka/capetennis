@extends('layouts/layoutMaster')

@section('title', 'Cancel')

@section('vendor-style')

@endsection

@section('vendor-script')



@endsection

@section('page-style')

@endsection

@section('page-script')


@endsection

@section('content')
@auth
  @if(auth()->user()->id == 584)
    <div class="m-2">
      <a href="{{ route('frontend.bracket.fixtures', $draw->id) }}" class="btn btn-primary btn-sm">
        Fixtures
      </a>
    </div>
  @endif
@endauth





<div>

  @include('frontend.draw.print')
</div>


@auth
@if(Auth::user()->id = 584)
<h3>Positions</h3>
@foreach($draw->registrations as $key => $registration)
<p>Position: {{$key+1}} {{Brackets::getPosition($key+1,$draw->id)}}</p>


@endforeach
@endif

@endauth

@endsection


