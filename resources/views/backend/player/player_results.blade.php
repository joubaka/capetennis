@extends('layouts/layoutMaster')

@section('title', 'Player Profile')

@section('vendor-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')


@endsection

@section('content')
<div class="card">
    <div class="card-header"><a href="{{ URL::previous() }}" class="btn btn-primary">Back</a></div>

</div>
<div class="card">
    <div class="card-header"><h3>{{$player->getFullNameAttribute()}}</h3></div>
    <div class="card-body">
      @foreach($results['fixture'] as $key => $result)
    
<p>{{$result->created_at->format('j F , Y') }} <span class="badge bg-label-success"> {{$result->fixture->draw->events->name}}</span> {{$result->team1->getFullNameAttribute()}} vs {{$result->team2->getFullNameAttribute()}} @foreach($result->fixture->teamResults as $r) {{$r->team1_score. '-'.$r->team2_score.';'}} @endforeach </p>
   
    @endforeach   
    </div>
   
</div>



@endsection