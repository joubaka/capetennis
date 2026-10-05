@extends('layouts.backend')
@section('title', 'Event communications')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'communications', 'eventWorkspaceRegionalOnly' => $teamAudience && !app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event, auth()->user())])
<a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-mail-log.index',$event) }}">Event email log</a>
@if($teamAudience)
<form method="get" class="mb-3"><label>Find a team <input name="team_search" value="{{ request('team_search') }}" class="form-control" maxlength="100"></label><button class="btn btn-outline-primary">Search teams</button></form>
@endif
<h4>{{ $event->name }} — Email communications</h4>
<p>Choose an audience at any event stage. Review every recipient and message before approving the send.</p>
@foreach(['success'=>'success','warning'=>'warning','error'=>'danger','info'=>'info'] as $flash=>$style)
@if(session($flash))<div class="alert alert-{{ $style }}" role="status">{{ session($flash) }}</div>@endif
@endforeach
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<form method="get" class="d-flex flex-wrap gap-2 mb-3"><label class="visually-hidden" for="contact-search">Find an individual</label><input class="form-control w-auto" id="contact-search" name="search" value="{{ $search }}" placeholder="Find an individual by name"><button class="btn btn-outline-primary">Find individual</button></form>
<form method="post" action="{{ route('backend.event-communications.preview', $event) }}" class="card card-body mb-4">@csrf
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="communication-scope">Audience</label><select class="form-select" id="communication-scope" name="scope">@foreach(($teamAudience ? ['all'=>'Everyone in your authorised event scope','nominations'=>'All nominated players','region'=>'One region','team'=>'One team','individual'=>'One individual'] : (['all'=>'All event players','registrations'=>'Registered players','nominations'=>'Nominated players','individual'=>'One individual'] + ($event->isMasters() ? ['invitations'=>'Invited players'] : []))) as $key=>$label)<option value="{{ $key }}" @selected(old('scope')===$key)>{{ $label }}</option>@endforeach@if($canRankingMail)<option value="rankings" @selected(old('scope')==='rankings')>Regional rankings</option>@endif</select></div>
<div class="col-md-4"><label class="form-label" for="communication-filter">Player status</label><select class="form-select" id="communication-filter" name="filter">@foreach(($teamAudience ? ['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','declined'=>'Declined','withdrawn'=>'Withdrawn','reserves'=>'Reserves'] : (['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','withdrawn'=>'Withdrawn'] + ($event->isMasters() ? ['declined'=>'Declined','reserves'=>'Reserves'] : []))) as $key=>$label)<option value="{{ $key }}" @selected(old('filter')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="communication-recipients">Send to</label><select class="form-select" id="communication-recipients" name="recipients">@if($teamAudience)<option value="both" @selected(old('recipients','both')==='both')>Players/parents and manager summaries</option>@endif<option value="players" @selected(old('recipients', $teamAudience ? 'both' : 'players')==='players')>Players/parents</option>@if($teamAudience)<option value="managers" @selected(old('recipients')==='managers')>Manager summaries only</option>@endif</select></div>
@if($teamAudience)
<div class="col-md-4" data-scope="region"><label class="form-label" for="communication-region">Region</label><select class="form-select" id="communication-region" name="region_id"><option value="">Choose a region</option>@foreach($regions as $region)<option value="{{ $region->region_id }}" @selected((string)old('region_id')===(string)$region->region_id)>{{ $region->region?->region_name }}</option>@endforeach</select></div>
<div class="col-md-4" data-scope="team"><label class="form-label" for="communication-team">Team</label><select class="form-select" id="communication-team" name="team_id"><option value="">Choose a team</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected((string)old('team_id')===(string)$team->id)>{{ $team->name }}</option>@endforeach</select></div>
@endif
<div class="col-md-4" data-scope="individual"><label class="form-label" for="communication-individual">Individual</label><select class="form-select" id="communication-individual" name="individual_key"><option value="">Choose an individual</option>@foreach($individuals as $individual)<option value="{{ $individual['key'] }}" @selected(old('individual_key')===$individual['key'])>{{ $individual['name'] }}</option>@endforeach</select><div class="form-text">Showing up to 100 matches. Use Find individual above to narrow the list.</div></div>
</div>
@if($canRankingMail)
<div data-scope="rankings" class="mt-3 border rounded p-3">
<h5>Regional rankings  -  players and parents</h5>
<p>Only current published rankings from linked regions are used. Each selected region must have a linked source and one published run. Exclusions do not renumber the original positions; tied ranks include every player at that position.</p>
<div class="row g-3">
<div class="col-md-6"><label for="ranking-regions" class="form-label">Regions (leave empty for all event regions)</label><select multiple size="5" class="form-select" id="ranking-regions" name="ranking_region_ids[]">@foreach($regions as $region)<option value="{{ $region->region_id }}" @selected(in_array($region->region_id,old('ranking_region_ids',[])))>{{ $region->region?->region_name }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="ranking-lists" class="form-label">Ranking lists (leave empty for all selected regions -  lists)</label><select multiple size="5" class="form-select" id="ranking-lists" name="ranking_list_ids[]">@foreach($rankingLists as $list)<option value="{{ $list->id }}" @selected(in_array($list->id,old('ranking_list_ids',[])))>{{ $regions->filter(fn($region)=>(int)$region->rankingSource?->series_id===(int)$list->series_id)->map(fn($region)=>$region->region?->region_name)->implode(', ') }}  -  {{ $list->category?->name }} (#{{ $list->id }})</option>@endforeach</select></div>
<div class="col-12"><label for="rank-numbers" class="form-label">Original rank numbers</label><input id="rank-numbers" class="form-control" name="rank_numbers" maxlength="1000" value="{{ old('rank_numbers') }}" placeholder="All ranks, or 9, 11, 14-18"><div class="form-text">The same numbers apply within every selected list. Leave empty for everyone.</div></div>
</div>
@foreach(['team_listed'=>'Players currently on any team list in this event (including unpaid players)','declined'=>'Players who declined','reserves'=>'Reserves','withdrawn'=>'Withdrawn players'] as $flag=>$label)<label class="d-block mt-2"><input type="checkbox" name="exclude_{{ $flag }}" value="1" @checked(old('exclude_'.$flag))> Exclude {{ $label }}</label>@endforeach
@if($rankingLists->isEmpty())<p class="alert alert-warning mt-3">No linked ranking lists are available. Link the region ranking sources before using this audience.</p>@endif
</div>
@endif
<label class="form-label mt-3" for="communication-subject">Subject</label><input class="form-control" id="communication-subject" name="subject" required maxlength="200" value="{{ old('subject', $event->name.' — Update') }}">
<label class="form-label mt-3" for="communication-body">Message</label><textarea class="form-control" id="communication-body" name="body" rows="7" required maxlength="30000">{{ old('body') }}</textarea>
<p class="form-text">Use this for announcements, arrangements, payment or clothing reminders. Player details appear in the exact preview. @if($teamAudience) Manager summaries cover teams in their assigned region. @endif</p>
<button class="btn btn-primary align-self-start">Preview recipients and exact emails</button>
</form>
<h5>Send reports</h5>
@if($teamAudience)
<div class="card card-body mb-3"><h5>Invitation send report</h5>
<p>Total: {{ $invitationSummary['total'] }} · Queued: {{ $invitationSummary['queued'] }} · Sending: {{ $invitationSummary['sending'] }} · Server accepted: {{ $invitationSummary['server_accepted'] }} · Failed: {{ $invitationSummary['failed'] }} · Skipped: {{ $invitationSummary['skipped'] }} · Unverified/test: {{ $invitationSummary['unverified'] + $invitationSummary['sandbox_accepted'] }}</p>
<p class="text-muted">Invitations are shown only within your authorised event and regional scope. Server acceptance does not confirm inbox delivery.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Recipient</th><th>Status and evidence</th><th>Action</th></tr></thead><tbody>
@forelse($invitationLogs as $invitationLog)<tr><td class="text-break">{{ $invitationLog->recipient_email }}</td><td>@include('backend.partials.email-delivery-status',['log'=>$invitationLog])</td><td>
@if($invitationLog->status==='failed' && !$invitationLog->sent_at && !$invitationLog->accepted_at)
@if(!empty($invitationLog->payload['rendered_html']) && !empty($invitationLog->payload['rendered_subject']))<form method="post" action="{{ route('backend.event-communications.retry-preview',[$event,$invitationLog]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Review invitation for retry</button></form>@else<small>Prepare a fresh invitation preview; this legacy email has no exact saved content.</small>@endif
@endif
</td></tr>@empty<tr><td colspan="3">No invitation email records in your scope.</td></tr>@endforelse
</tbody></table></div>{{ $invitationLogs->links('pagination::bootstrap-5') }}</div>
@endif
@foreach($drafts as $draft)<div class="card card-body mb-3"><strong>{{ $draft->subject }}</strong><p class="mb-2">A player action prepared this email. It has not been sent.</p><form method="post" action="{{ route('backend.event-communications.drafts.preview',[$event,$draft]) }}">@csrf<button class="btn btn-outline-primary">Review and approve draft</button></form></div>@endforeach
@forelse($batches as $item)<a class="d-block mb-2" href="{{ route('backend.event-communications.index',['event'=>$event,'batch'=>$item->id]) }}">{{ $item->subject }} — {{ count($item->recipients) }} emails — {{ $item->approved_at ? 'Approved' : 'Awaiting approval' }} — {{ $item->created_at }}</a>@empty<p>No email previews yet.</p>@endforelse
{{ $batches->withQueryString()->links('pagination::bootstrap-5') }}
@if($batch)
<div class="card card-body mt-3"><h5>{{ $batch->subject }}</h5><p>{{ count($batch->recipients) }} approved-preview emails · {{ count($batch->issues) }} missing-contact warnings</p>
@if(!$batch->approved_at)<div class="alert alert-info">This preview has not been approved; no emails have been queued.</div>@else
<div class="d-flex flex-wrap gap-3 mb-3"><span>Queued: <strong>{{ $summary['queued'] }}</strong></span><span>Sending: <strong>{{ $summary['sending'] }}</strong></span><span>Server accepted: <strong>{{ $summary['server_accepted'] }}</strong></span><span>Unverified/test: <strong>{{ $summary['unverified'] + $summary['sandbox_accepted'] }}</strong></span><span>Failed: <strong>{{ $summary['failed'] }}</strong></span><span>Skipped: <strong>{{ $summary['skipped'] }}</strong></span></div>
@if($summary['all_server_accepted'] && $summary['total']===count($batch->recipients))<div class="alert alert-success">Every approved email was accepted by the outgoing mail server.</div>@else<div class="alert alert-warning">Mail-server acceptance is not yet confirmed for every approved email. Review the statuses below.</div>@endif
<p class="text-muted">Server acceptance confirms handoff for onward delivery. Inbox delivery, bounces and reading are not tracked by this application.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Recipient</th><th>Status and evidence</th><th>Action</th></tr></thead><tbody>@foreach($logs as $log)<tr><td class="text-break">{{ $log->recipient_email }}<br><small>{{ $log->payload['recipient_kind'] ?? '' }}</small></td><td>@include('backend.partials.email-delivery-status',['log'=>$log])</td><td>@if($log->status==='failed' && !$log->sent_at)<form method="post" action="{{ route('backend.event-communications.retry-preview',[$event,$log]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Review failed email for retry</button></form>@endif</td></tr>@endforeach</tbody></table></div>{{ $logs->links('pagination::bootstrap-5') }}
@endif
@if($batch->issues)<details><summary>Missing contacts / excluded recipients</summary><ul>@foreach($batch->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>@endif
</div>
@endif
<script>
const scopeSelect = document.getElementById('communication-scope');
function updateScope(){const ranking=scopeSelect.value==='rankings'; document.getElementById('communication-filter').closest('.col-md-4').hidden=ranking; document.getElementById('communication-recipients').closest('.col-md-4').hidden=ranking;document.querySelectorAll('[data-scope]').forEach(el=>{el.hidden=el.dataset.scope!==scopeSelect.value; el.querySelectorAll('select,input').forEach(select=>{select.disabled=el.hidden;});});}
scopeSelect.addEventListener('change',updateScope); updateScope();
</script>
@endsection
