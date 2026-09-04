@extends('layouts.backend')

@section('title', 'Agreements - Code of Conduct')

@section('content')
<div class="container-xl">

  <x-backend.page-header title="Code of Conduct agreements" eyebrow="Administration" subtitle="Manage agreement versions and player acceptance." icon="ti-file-text">
  <x-slot:actions><a href="{{ route('backend.agreements.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> New Agreement
      </a></x-slot:actions>
</x-backend.page-header>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Title</th>
            <th>Version</th>
            <th>Status</th>
            <th>Acceptances</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($agreements as $agreement)
            <tr>
              <td>{{ $agreement->title }}</td>
              <td><strong>{{ $agreement->version }}</strong></td>
              <td>
                @if($agreement->is_active)
                  <span class="badge bg-success">Active</span>
                @else
                  <span class="badge bg-secondary">Inactive</span>
                @endif
              </td>
              <td>{{ $agreement->playerAgreements()->count() }}</td>
              <td>{{ $agreement->created_at->format('d M Y') }}</td>
              <td>
                <div class="d-flex gap-1">
                  <a href="{{ route('backend.agreements.show', $agreement) }}" class="btn btn-sm btn-outline-primary" title="View">
                    <i class="ti ti-eye"></i>
                  </a>

                  @if(!$agreement->is_active)
                    <a href="{{ route('backend.agreements.edit', $agreement) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                      <i class="ti ti-pencil"></i>
                    </a>
                  @endif

                  <form action="{{ route('backend.agreements.duplicate', $agreement) }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-info" title="Duplicate">
                      <i class="ti ti-copy"></i>
                    </button>
                  </form>

                  @if(!$agreement->is_active)
                    <form action="{{ route('backend.agreements.setActive', $agreement) }}" method="POST" style="display:inline;"
                          onsubmit="return confirm('Set this agreement as active? All players will need to re-accept.');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-success" title="Set Active">
                        <i class="ti ti-check"></i> Activate
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">No agreements created yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
