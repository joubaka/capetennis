@extends('layouts.backend')
@section('title', 'Player Plus management')
@section('content')
<div class="container-xxl container-p-y" style="max-width:1150px;overflow-wrap:anywhere" data-plus-management>
  @include('backend.player-plus.manage-style')
  <a href="{{ route('backend.superadmin.index') }}">← Super Admin</a>
  <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 my-3"><div><h1 class="h3">Player Plus management</h1><p class="text-muted mb-0">Manage player access, memberships and payment problems.</p></div><a class="btn btn-outline-primary" href="{{ route('superadmin.player-plus.index') }}">Payment reviews</a></header>
  <div class="card card-body mb-4"><strong class="h4 mb-1">{{ number_format($activeCount) }} active players</strong><span class="text-muted">Verified paid access and current admin grants, excluding suspended access.</span></div>
  <p><a href="{{ route('settings.index') }}#settings-plus">Manage Plus plans, prices and new purchases</a></p>
  <form method="get" class="card card-body mb-4">
    <div class="row g-3"><div class="col-md-6"><label for="plus-search" class="form-label">Player name or profile number</label><input id="plus-search" class="form-control" name="q" value="{{ $search }}" maxlength="100" placeholder="Find a player to manage or activate"></div>
      <div class="col-md-4"><label for="plus-status" class="form-label">Show</label><select id="plus-status" class="form-select" name="status">@foreach(['members'=>'All registrations and grants','active'=>'Active access','suspended'=>'Deactivated access','scheduled'=>'Scheduled admin access','expired'=>'Expired terms','pending'=>'Pending checkouts','problems'=>'Payment review history · includes resolved','all'=>'All players · add access'] as $key=>$label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Search</button></div></div>
  </form>
  <p class="small text-muted">{{ $players->total() }} players found. Select “All players · add access” to activate a player without an existing registration.</p>
  <div class="list-group mb-3">
    @forelse($players as $player)
    <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2 py-3" href="{{ route('superadmin.player-plus.manage.show', $player) }}">
      <div><strong>{{ $player->full_name }}</strong><span class="d-block small text-muted">Profile #{{ $player->id }} · {{ $player->plus_orders_count }} orders</span></div>
      <div class="text-md-end"><span class="badge {{ in_array($player->id, $activeIds) ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $player->plusOverride?->mode === 'suspended' ? 'Deactivated' : (in_array($player->id, $activeIds) ? 'Active' : 'No current access') }}</span><span class="d-block small mt-1">@if($player->plusOverride?->mode === 'grant')Admin grant: {{ $player->plusOverride->starts_at?->format('d M Y') }} – {{ $player->plusOverride->ends_at?->format('d M Y') }}@elseif($player->plusMembership)Paid term ends {{ $player->plusMembership->ends_at->format('d M Y') }}@endif</span></div>
    </a>
    @empty<div class="card card-body">No players match these filters. Search all players to add access.</div>@endforelse
  </div>
  {{ $players->links() }}
</div>
@endsection
