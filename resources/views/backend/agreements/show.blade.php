@extends('layouts/layoutMaster')

@section('title', 'View Agreement')

@section('content')
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">{{ $agreement->title }}</h4>
        <span class="text-muted">Version: <strong>{{ $agreement->version }}</strong></span>
        @if($agreement->is_active)
          <span class="badge bg-success ms-2">Active</span>
        @else
          <span class="badge bg-secondary ms-2">Inactive</span>
        @endif
      </div>
      <a href="{{ route('backend.agreements.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left"></i> Back
      </a>
    </div>
  </div>

  {{-- Agreement Content --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Agreement Content</h5>
    </div>
    <div class="card-body" style="max-height: 500px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 1.5rem;">
      {!! $agreement->content !!}
    </div>
  </div>

  {{-- Acceptances --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Acceptances ({{ $acceptances->count() }})</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Player</th>
            <th>Accepted By</th>
            <th>Guardian</th>
            <th>IP Address</th>
            <th>Accepted At</th>
          </tr>
        </thead>
        <tbody>
          @forelse($acceptances as $acceptance)
            <tr>
              <td>{{ $acceptance->player->name ?? 'N/A' }} {{ $acceptance->player->surname ?? '' }}</td>
              <td>
                @if($acceptance->accepted_by_type === 'guardian')
                  <span class="badge bg-info">Guardian</span>
                @else
                  <span class="badge bg-primary">Player</span>
                @endif
              </td>
              <td>
                @if($acceptance->guardian_name)
                  {{ $acceptance->guardian_name }}<br>
                  <small class="text-muted">{{ $acceptance->guardian_email }}</small><br>
                  <small class="text-muted">{{ $acceptance->guardian_relationship }}</small>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td><small>{{ $acceptance->ip_address }}</small></td>
              <td>{{ $acceptance->accepted_at->format('d M Y H:i') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">No acceptances yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
