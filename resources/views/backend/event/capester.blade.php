@extends('layouts.backend')
@section('title', 'Send to Capester')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Capester support'])
<div class="card"><div class="card-body">
  <h2 class="h4">Send to Capester</h2>
  <p>Describe the problem for this event. Only the text you enter below and the event reference will be transferred to your local Capester inbox. Review it before sending; leave out passwords and personal player details.</p>
  <p>Delivery means received for review. It does not fix the problem or change the application.</p>
  @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
  @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{ route('backend.capester.store', $event) }}">
    @csrf
    <input type="hidden" name="submission_uuid" value="{{ old('submission_uuid', (string) Illuminate\Support\Str::uuid()) }}">
    <input type="hidden" name="kind" value="support">
    <label for="capester-description">Problem description</label>
    <textarea id="capester-description" name="capester_description" maxlength="2000" rows="6" class="form-control" required>{{ old('capester_description') }}</textarea>
    <label class="d-block mt-3"><input type="checkbox" name="reviewed" value="1" required> I reviewed this text and agree to transfer it to my local Capester inbox.</label>
    <button class="btn btn-primary mt-3" type="submit">Send to Capester</button>
  </form>
  @if(app()->environment(['local', 'testing']))
  <details class="mt-3"><summary>Local connection test</summary>
    <form method="post" action="{{ route('backend.capester.store', $event) }}">@csrf
      <input type="hidden" name="submission_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
      <input type="hidden" name="kind" value="hello"><input type="hidden" name="capester_description" value="Synthetic fixed-prompt connection test.">
      <input type="hidden" name="reviewed" value="1">
      <button class="btn btn-secondary" type="submit">Queue connection test</button>
    </form>
  </details>
  @endif
</div></div>
<div class="card mt-3"><div class="card-body"><h3 class="h5">Your requests for this event</h3>
@forelse($items as $item)
  <article class="border-bottom py-3" style="overflow-wrap:anywhere"><strong>{{ $item->kind === 'hello' ? 'Connection test' : 'Support request' }}</strong>
    <p>{{ $item->capester_description }}</p><p>Status: {{ str_replace('_', ' ', $item->status) }} · {{ $item->created_at->format('Y-m-d H:i') }}</p>
    @if($item->status === 'received')<p>Delivered to the local inbox for review; no fix has been applied.</p>@endif
  </article>
@empty<p>No requests yet.</p>@endforelse
{{ $items->links() }}
</div></div>
@endsection
