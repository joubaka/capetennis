@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Super Admin Dashboard')

@section('content')

{{-- Header Banner --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card" style="background: linear-gradient(135deg, #696cff 0%, #567bfb 100%);">
      <div class="card-body d-flex align-items-center justify-content-between py-4">
        <div>
          <h4 class="text-white mb-1">
            <i class="ti ti-shield-chevron me-2"></i>Super Admin Dashboard
          </h4>
          <p class="text-white mb-0 opacity-75">Manage Cape Tennis system settings, agreements, and users</p>
        </div>
        <div>
          <span class="badge bg-white text-primary fs-6 px-3 py-2">
            <i class="ti ti-user me-1"></i>Super User
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Stats Cards --}}
<div class="row mb-4">

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #ebe9ff;">
            <i class="ti ti-users text-primary" style="font-size:1.5rem;"></i>
          </span>
        </div>
        <h3 class="mb-1">{{ number_format($totalUsers) }}</h3>
        <small class="text-muted">Total Users</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #e8f8f0;">
            <i class="ti ti-user-check" style="font-size:1.5rem; color:#28c76f;"></i>
          </span>
        </div>
        <h3 class="mb-1">{{ number_format($totalPlayers) }}</h3>
        <small class="text-muted">Total Players</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #e0f4fd;">
            <i class="ti ti-calendar-event" style="font-size:1.5rem; color:#00cfe8;"></i>
          </span>
        </div>
        <h3 class="mb-1">{{ number_format($totalEvents) }}</h3>
        <small class="text-muted">Total Events</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #fff4e0;">
            <i class="ti ti-calendar-time" style="font-size:1.5rem; color:#ff9f43;"></i>
          </span>
        </div>
        <h3 class="mb-1">{{ number_format($activeEvents) }}</h3>
        <small class="text-muted">Active Events</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #ffe0e0;">
            <i class="ti ti-ticket" style="font-size:1.5rem; color:#ea5455;"></i>
          </span>
        </div>
        <h3 class="mb-1">{{ number_format($totalRegistrations) }}</h3>
        <small class="text-muted">Registrations</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color: #f0f0f0;">
            <i class="ti ti-file-check" style="font-size:1.5rem; color:#a0aab4;"></i>
          </span>
        </div>
        <h3 class="mb-1">—</h3>
        <small class="text-muted">CoC Accepted</small>
      </div>
    </div>
  </div>

</div>

{{-- Quick Actions --}}
<div class="row">
  <div class="col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center">
        <i class="ti ti-bolt me-2 text-warning"></i>
        <h5 class="mb-0">Quick Actions</h5>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ url('backend/user') }}" class="btn btn-outline-primary btn-sm">
            <i class="ti ti-users me-1"></i>Manage Users
          </a>
          <a href="{{ url('backend/player') }}" class="btn btn-outline-success btn-sm">
            <i class="ti ti-user-check me-1"></i>Manage Players
          </a>
          <a href="{{ url('backend/series') }}" class="btn btn-outline-info btn-sm">
            <i class="ti ti-timeline me-1"></i>Series
          </a>
          <a href="{{ url('backend/league') }}" class="btn btn-outline-warning btn-sm">
            <i class="ti ti-trophy me-1"></i>League
          </a>
          <a href="{{ url('backend/settings') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-settings me-1"></i>Site Settings
          </a>
          <a href="{{ url('backend/eventPhoto') }}" class="btn btn-outline-dark btn-sm">
            <i class="ti ti-photo me-1"></i>Photos
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center">
        <i class="ti ti-file-text me-2 text-primary"></i>
        <h5 class="mb-0">Code of Conduct Agreements</h5>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-4">
        <i class="ti ti-file-off text-muted mb-2" style="font-size:2.5rem;"></i>
        <p class="text-muted mb-3">Agreement management coming soon.</p>
        <a href="{{ url('backend/agreements') }}" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i>New Agreement
        </a>
      </div>
    </div>
  </div>
</div>

@endsection
