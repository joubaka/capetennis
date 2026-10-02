@extends('layouts/layoutMaster')

@section('title', 'Mail history')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="h3 mb-1">Mail history</h1><p class="text-muted mb-0">Check recipients, sending progress and acceptance evidence.</p></div>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.superadmin.index') }}">Dashboard</a>
</div>
  @include('backend.superadmin.partials.mail-history')
@endsection
