@extends('layouts.backend')
@section('title', 'Private registration')
@section('vendor-style')<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">@endsection
@section('vendor-script')<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>@endsection
@section('content')
<div class="card mx-auto" style="max-width: 760px"><div class="card-body">
<h1 class="h4">Enable private registration</h1>
<p>{{ $event->name }} · {{ $categoryEvent->category->name }}</p>
<p>Choose one player. Their linked account will see <strong>Register and pay</strong> on the event page while public entries stay closed. The primary email receives the registration link; additional contacts receive notification-only copies. Access expires in 48 hours or when the event starts, whichever is sooner.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(isset($issued))
<div class="alert alert-success">{{ $issued['reused'] ? 'Registration was already enabled. No duplicate invitation was queued.' : 'Private registration enabled.' }} Invitation status: <strong>{{ $issued['mail_status'] }}</strong>@if($issued['recipient']) to {{ $issued['recipient'] }}@endif.</div>
@if(in_array($issued['mail_status'], ['failed', 'missing', 'skipped', 'acceptance_unknown']))<div class="alert alert-warning">Review this invitation in the event's email log before trying to send again.</div>@endif
<ul class="small">@foreach($issued['messages'] as $message)<li>{{ $message->recipient_email }} — {{ ($message->payload['recipient_kind'] ?? null) === 'primary-player' ? 'Registration invitation' : 'Notification only' }} ({{ $message->status }})</li>@endforeach</ul>
<label class="form-label" for="privateLink">Registration link</label><input id="privateLink" class="form-control mb-2" readonly value="{{ $issued['url'] }}">
<button type="button" class="btn btn-primary mb-2" id="copyPrivateLink">Copy link</button>
<p class="small">Expires {{ $issued['expires']->format('j M Y H:i') }}. Only an account already linked to this player can use the link. The first payer can resume their checkout. If that checkout is cancelled or expires, enable registration again.</p>
@else
<form method="POST" action="{{ route('admin.category.private-link.store', [$event, $categoryEvent]) }}">@csrf
<label for="privatePlayer" class="form-label">Player</label><select name="player_id" id="privatePlayer" class="form-select" required></select>
<input type="hidden" name="reviewed_email" id="reviewedEmail" value="">
<input type="hidden" name="reviewed_contacts" id="reviewedContacts" value="">
<p class="mt-3 mb-0" id="privateRecipient" aria-live="polite">Choose a player to review the invitation recipient.</p>
<button class="btn btn-primary mt-3" id="enablePrivateRegistration" disabled>Enable and email registration</button>
</form>
@endif
<a class="btn btn-outline-secondary mt-2" href="{{ route('admin.events.entries.new', $event) }}">Back to entries</a>
</div></div>
@endsection
@section('page-script')
@php($recipientReviewUrl = route('admin.category.private-link.recipient', [$event, $categoryEvent, 'PLAYER_ID']))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('privatePlayer')) $('#privatePlayer').select2({width: '100%', placeholder: 'Search for a player', minimumInputLength: 2, ajax: {url: @json(route('register.search.players')), dataType: 'json', delay: 250, data: params => ({q: params.term}), processResults: data => data}});
    $('#privatePlayer').on('change', async function () {
        const playerId = this.value;
        const review = document.getElementById('privateRecipient');
        const email = document.getElementById('reviewedEmail');
        const contacts = document.getElementById('reviewedContacts');
        const submit = document.getElementById('enablePrivateRegistration');
        email.value = ''; contacts.value = ''; submit.disabled = true;
        review.textContent = playerId ? 'Checking linked account and email…' : 'Choose a player to review the invitation recipient.';
        if (!playerId) return;
        try {
            const url = @json($recipientReviewUrl).replace('PLAYER_ID', encodeURIComponent(playerId));
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (this.value !== playerId) return;
            if (!response.ok) throw new Error(data.message || 'Could not review the invitation recipient.');
            if (data.message) { review.textContent = data.message; return; }
            email.value = data.email;
            contacts.value = data.reviewed_contacts;
            review.textContent = 'Registration link: ' + data.email + '. Notification only: ' + (data.copies.length ? data.copies.join(', ') : 'none') + '. Only this player’s linked account can register.';
            submit.disabled = false;
        } catch (error) { if (this.value === playerId) review.textContent = error.message || 'Could not review the invitation recipient. Try selecting the player again.'; }
    });
    document.getElementById('copyPrivateLink')?.addEventListener('click', async function () {
        const input = document.getElementById('privateLink');
        try { await navigator.clipboard.writeText(input.value); this.textContent = 'Copied'; }
        catch (_) { input.select(); this.textContent = 'Select and copy the link above'; }
    });
});
</script>
@endsection
