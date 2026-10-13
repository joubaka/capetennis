@extends('layouts.backend')
@section('title', 'Share private selection preview')
@section('content')
@php $maskedEmail = fn ($email) => substr((string) $email, 0, 1).'***@'.(explode('@', (string) $email)[1] ?? ''); @endphp
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h3>Share Preview Selections</h3><p>{{ $event->name }}</p></div><a class="btn btn-outline-primary" href="{{ route('backend.selection-preview.show', ['event' => $event, 'age' => $age]) }}">Back to preview</a></div>
  <p>Choose Cape Tennis accounts to give read-only access to this event's private selection preview. Each recipient must sign in with their selected account. They cannot edit selections, change setup or share access. The link shows the current preview as drafts change.</p>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @if($result = session('share_result'))<div class="alert alert-info" role="status">Read-only access shared with {{ $result['recipients'] }} accounts. Email outcomes: {{ $result['queued'] }} queued, {{ $result['sending'] }} processing, {{ $result['prepared'] }} prepared for queue submission, {{ $result['accepted'] }} accepted by the mail transport, {{ $result['failed'] }} failed, {{ $result['skipped'] }} skipped, {{ $result['unknown'] }} uncertain. These outcomes do not confirm inbox delivery.</div>@endif
  <form method="GET" class="d-flex flex-wrap gap-2 mb-3"><input class="form-control" style="max-width: 350px" type="search" name="q" value="{{ $q }}" placeholder="Find an account by name" aria-label="Find account by name" minlength="2" maxlength="80">@if($age)<input type="hidden" name="age" value="{{ $age }}">@endif<button class="btn btn-outline-primary">Search</button></form>
  <form method="POST" action="{{ route('backend.selection-preview-share.store', $event) }}" class="card card-body mb-4">
    @csrf <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">@if($age)<input type="hidden" name="age" value="{{ $age }}">@endif
    <h5>Select recipients</h5><p>Select accounts on this page, then send. You can search again to share with more people. A new send action can send another link to an existing recipient.</p>
    @forelse($users as $user)<label class="d-flex align-items-center gap-2 py-2"><input type="checkbox" class="form-check-input" name="recipient_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, old('recipient_ids', [])))><span>{{ $user->name }} <small class="text-muted">Account #{{ $user->id }} · {{ $maskedEmail($user->email) }}</small></span></label>@empty<p>No matching accounts.</p>@endforelse
    <div class="mt-3"><button type="submit" class="btn btn-primary">Send preview link</button></div>
  </form>{{ $users->links() }}
  <h5>Accounts with shared read-only access</h5>
  @forelse($grants as $grant)<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom py-2"><span>{{ $grant->user->name }} <small class="text-muted">{{ $maskedEmail($grant->user->email) }}</small></span><form method="POST" action="{{ route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $grant->user_id]) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Remove access</button></form></div>@empty<p>No accounts have shared preview access yet.</p>@endforelse
  {{ $grants->links() }}
</div>
@endsection
