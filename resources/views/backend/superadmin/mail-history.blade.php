@extends('layouts/layoutMaster')

@section('title', 'Mail history')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <h1 class="h3 mb-0">Mail history</h1>
  <a class="btn btn-outline-secondary" href="{{ route('backend.superadmin.index') }}">Super Admin dashboard</a>
</div>
<div class="card"><div class="card-body">
  @include('backend.superadmin.partials.mail-history')
</div></div>
@endsection
