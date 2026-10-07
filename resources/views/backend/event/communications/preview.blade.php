@extends('layouts.backend')
@section('title', 'Review event emails')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'communications', 'eventWorkspaceRegionalOnly' => !app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event, auth()->user())])
<h4>{{ $event->name }} — Review emails</h4>
<p>{{ count($batch->recipients) }} emails. Nothing is sent until you approve below.</p>
@php($coveredPlayers = collect($batch->recipients)->flatMap(fn ($recipient) => $recipient['player_keys'] ?? [])->unique()->count())
@if($coveredPlayers)<p>{{ $coveredPlayers }} distinct players covered; {{ count($batch->issues ?? []) }} missing contacts. Shared addresses receive one email.</p>@endif
@if(($batch->options['scope'] ?? null)==='rankings')

<div class="card card-body mb-3"><h5>Review ranked players</h5>
<p>Matched ranking rows: {{ $rankingReview['counts']['matched'] }}  -  Included: {{ $rankingReview['counts']['included'] }}  -  Missing contacts: {{ $rankingReview['counts']['missing_contacts'] }}</p>
<p>Excluded (counts can overlap): team listed {{ $rankingReview['counts']['team_listed'] }}, declined {{ $rankingReview['counts']['declined'] }}, reserves {{ $rankingReview['counts']['reserves'] }}, withdrawn {{ $rankingReview['counts']['withdrawn'] }}, manually {{ $rankingReview['counts']['manual'] }}.</p>
<form method="post" action="{{ route('backend.event-communications.preview',$event) }}">@csrf
@foreach($batch->options as $key=>$value)@if($key!=='excluded_player_ids')@if(is_array($value))@foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endif @endforeach
<input type="hidden" name="subject" value="{{ $batch->subject }}"><input type="hidden" name="body" value="{{ $batch->body }}">
<div class="table-responsive"><table class="table"><thead><tr><th>Exclude manually</th><th>Player</th><th>Region / list / rank</th><th>Contacts / exclusions</th></tr></thead><tbody>
@foreach($rankingReview['rows']->groupBy('player_id') as $playerRows)@php($player = $playerRows->first())<tr><td><input aria-label="Exclude {{ $player['name'] }}" type="checkbox" name="excluded_player_ids[]" value="{{ $player['player_id'] }}" @checked(in_array($player['player_id'],$batch->options['excluded_player_ids'] ?? []))></td><td>{{ $player['name'] }}</td><td>@foreach($playerRows as $row)<div>{{ $row['region'] }} / {{ $row['category'] }} / {{ $row['rank'] }}</div>@endforeach</td><td class="text-break">{{ implode(', ',$player['emails']) ?: 'No valid contact' }}<br>{{ implode(', ',$player['excluded']) }}</td></tr>@endforeach
</tbody></table></div><button class="btn btn-outline-primary">Update selection and review fresh emails</button>
<p class="form-text">Changing these checkboxes requires a fresh preview using the button above before approving.</p></form></div>
@endif
@if($batch->issues)<div class="alert alert-warning"><strong>Missing contacts</strong><ul class="mb-0">@foreach($batch->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>@endif
@foreach($batch->recipients as $recipient)<div class="card card-body mb-3"><p class="text-break"><strong>To:</strong> {{ $recipient['email'] }} — {{ $recipient['kind']==='manager' ? 'Regional manager' : 'Player/parent' }}</p><h5>{{ $recipient['subject'] }}</h5><iframe title="Email preview for {{ $recipient['email'] }}" sandbox="" srcdoc="{{ $recipient['html'] }}" style="width:100%;height:420px;border:1px solid #ddd" loading="lazy"></iframe></div>@endforeach
<form method="post" action="{{ route('backend.event-communications.send',$event) }}">@csrf<input type="hidden" name="token" value="{{ $batch->token }}">
@if($batch->issues)<label class="d-block mb-3"><input class="form-check-input me-2" type="checkbox" name="acknowledge_missing" value="1" required>I reviewed the missing contacts. These recipients will not receive an email.</label>@endif
<label class="d-block mb-3"><input class="form-check-input me-2" type="checkbox" name="confirm_send" value="1" required>I approve these exact recipients and messages.</label><button id="approve-ranking-mail" class="btn btn-primary">Approve and queue {{ count($batch->recipients) }} emails</button> <a class="btn btn-outline-secondary" href="{{ route('backend.event-communications.index',$event) }}">Back to composer</a>
</form>
@if(($batch->options['scope'] ?? null)==='rankings')
<script>document.querySelectorAll('input[name="excluded_player_ids[]"]').forEach(input=>input.addEventListener('change',()=>{const button=document.getElementById('approve-ranking-mail'); button.disabled=true; button.textContent='Update selection above before approving';}));</script>
@endif
@endsection
