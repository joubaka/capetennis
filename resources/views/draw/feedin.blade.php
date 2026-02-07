@extends('layouts.layoutMaster')

@section('title', 'Feed-In Consolation Draw')

@section('content')
    <div class="container">
        <h2 class="mb-4">Feed-In Draw</h2>
        <div class="border p-3 bg-white">
            {!! $svg !!}
        </div>
    </div>
@endsection
