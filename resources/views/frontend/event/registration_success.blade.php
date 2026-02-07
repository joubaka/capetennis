@extends('layouts/layoutMaster')

@section('content')
<div class="container py-5 text-center">
    <h3 class="text-success mb-3">✅ Registration Complete</h3>
    <p>Your registration has been successfully completed.</p>
    <a href="{{ url('/') }}" class="btn btn-primary mt-3">Back to Events</a>
</div>
@endsection
