@extends('layouts.backend')
@section('title', 'Event communications')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceActive' => 'communications', 'eventWorkspaceRegionalOnly' => $teamAudience && !app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event, auth()->user())])
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.index',$event) }}">Event email log</a><a class="btn btn-outline-secondary" href="#email-history">Email history</a></div>
@foreach(['success'=>'success','warning'=>'warning','error'=>'danger','info'=>'info'] as $flash=>$style)
@if(session($flash))<div class="alert alert-{{ $style }}" role="status">{{ session($flash) }}</div>@endif
@endforeach
<details class="card card-body mb-4" @if($errors->any() || session('compose_subject') || request('compose') || $search !== '') open @endif><summary class="h5 mb-0">Write an email</summary><div class="mt-3">
@if($teamAudience)
<form method="get" class="mb-3"><input type="hidden" name="compose" value="1">@foreach(request()->except(['team_search','compose']) as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label>Find a team <input name="team_search" value="{{ request('team_search') }}" class="form-control" maxlength="100"></label><button class="btn btn-outline-primary">Search teams</button></form>
@endif
<h4>{{ $event->name }} — Send an email</h4>
<p>Choose who you want to email, write your message, then check it before sending.</p>

@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('backend.event-communications.preview', $event) }}" class="card card-body mb-4">@csrf
@php
$composeOptions = session('compose_options', []);
@endphp
@foreach(['category_event_id','registration_id','direct_email'] as $field)
@if(old($field,$composeOptions[$field] ?? null))<input type="hidden" name="{{ $field }}" value="{{ old($field,$composeOptions[$field] ?? '') }}">@endif
@endforeach
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="communication-scope">Who is this for?</label><select class="form-select" id="communication-scope" name="scope">@foreach(($teamAudience ? ['all'=>'Everyone you can contact for this event','nominations'=>'All nominated players','region'=>'A region','team'=>'A team','individual'=>'A player'] : (['all'=>'All event players','registrations'=>'Registered players','nominations'=>'Nominated players','individual'=>'A player'] + ($event->isMasters() ? ['invitations'=>'Invited players'] : []))) as $key=>$label)<option value="{{ $key }}" @selected(old('scope', $composeOptions['scope'] ?? ($search !== '' ? 'individual' : 'all'))===$key)>{{ $label }}</option>@endforeach@if($canRankingMail)<option value="rankings" @selected(old('scope')==='rankings')>Regional rankings</option>@endif@if(in_array(old('scope',$composeOptions['scope'] ?? null),['direct','legacy_registered'],true))<option value="{{ old('scope',$composeOptions['scope'] ?? '') }}" selected>Original selected recipients (review required)</option>@endif</select></div>
<div class="col-md-4"><label class="form-label" for="communication-filter">Player status</label><select class="form-select" id="communication-filter" name="filter">@foreach(($teamAudience ? ['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','declined'=>'Declined','withdrawn'=>'Withdrawn','reserves'=>'Reserves'] : (['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','withdrawn'=>'Withdrawn'] + ($event->isMasters() ? ['declined'=>'Declined','reserves'=>'Reserves'] : []))) as $key=>$label)<option value="{{ $key }}" @selected(old('filter', $composeOptions['filter'] ?? 'all')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="communication-recipients">Send to</label><select class="form-select" id="communication-recipients" name="recipients">@if($teamAudience)<option value="both" @selected(old('recipients',$composeOptions['recipients'] ?? 'both')==='both')>Players, parents and regional managers</option>@endif<option value="players" @selected(old('recipients', $composeOptions['recipients'] ?? ($teamAudience ? 'both' : 'players'))==='players')>Players and parents</option>@if($teamAudience)<option value="managers" @selected(old('recipients')==='managers')>Regional managers only</option>@endif</select>@if($teamAudience)<div class="form-text">Regional managers receive your message for each selected team in their region.</div>@endif</div>
@if($teamAudience)
<div class="col-md-4" data-scope="region"><label class="form-label" for="communication-region">Region</label><select class="form-select" id="communication-region" name="region_id"><option value="">Choose a region</option>@foreach($regions as $region)<option value="{{ $region->region_id }}" @selected((string)old('region_id', $composeOptions['region_id'] ?? '')===(string)$region->region_id)>{{ $region->region?->region_name }}</option>@endforeach</select></div>
<div class="col-md-4" data-scope="team"><label class="form-label" for="communication-team">Team</label><select class="form-select" id="communication-team" name="team_id"><option value="">Choose a team</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected((string)old('team_id', $composeOptions['team_id'] ?? '')===(string)$team->id)>{{ $team->name }}</option>@endforeach</select></div>
@endif
<div class="col-md-6" data-scope="individual">
<label class="form-label" for="communication-individual">Individual</label>
<input class="form-control" id="communication-individual" name="individual_search_name" value="{{ old('individual_search_name', $search) }}" maxlength="100" autocomplete="off" placeholder="Type a name and choose a match" aria-describedby="individual-search-status" aria-controls="individual-search-results" aria-expanded="false">
<input type="hidden" id="communication-individual-key" name="individual_key" value="{{ old('individual_key', $composeOptions['individual_key'] ?? '') }}">
<div id="individual-search-status" class="form-text" role="status" aria-live="polite">Type a name to find an individual.</div>
<div id="individual-search-results" class="list-group mt-2" aria-label="Matching individuals" hidden></div>
<noscript><p class="form-text">Enable JavaScript to search for an individual.</p></noscript>
</div>
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
<label class="form-label mt-3" for="communication-subject">Subject</label><input class="form-control" id="communication-subject" name="subject" required maxlength="200" value="{{ old('subject', session('compose_subject', $event->name.' — Update')) }}">
<label class="form-label mt-3" for="communication-body">Message</label><textarea class="form-control" id="communication-body" name="body" rows="7" required maxlength="30000">{{ old('body',session('compose_body')) }}</textarea>
<p class="form-text">Write an update, share arrangements or send a payment or clothing reminder. Only your message is included. Check it on the next screen before sending.</p>
<button class="btn btn-primary align-self-start">Preview email</button>
</form>
</div></details>
<div class="d-flex flex-wrap gap-2 mb-3" aria-label="Email report scope">
@foreach(['all'=>'All event emails','invitations'=>'Invitations only'] as $scopeKey=>$scopeLabel)<a class="btn btn-outline-primary" href="{{ route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','report_scope','batch']),['report_scope'=>$scopeKey])) }}#email-history" @if($reportContext['report_scope']===$scopeKey) aria-current="page" @endif>{{ $scopeLabel }}</a>@endforeach
@if($batch)<a class="btn btn-outline-primary" href="{{ route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','report_scope']),['batch'=>$batch->id,'report_scope'=>'batch'])) }}#email-history" @if($reportContext['report_scope']==='batch') aria-current="page" @endif>This batch only</a>@endif
</div>
@if($batch)
@php($coveredPlayers = collect($batch->recipients)->flatMap(fn ($recipient) => $recipient['player_keys'] ?? [])->unique()->count())
@if($coveredPlayers)<p>Selected batch: {{ $coveredPlayers }} distinct players covered; {{ $batch->serverAcceptedPlayerCount() }} players with a mail-server-accepted contact; {{ count($batch->recipients) }} emails; {{ count($batch->issues ?? []) }} missing contacts.</p>@endif
@endif
@if($batch && $batch->approved_at && $summary && !$summary['all_server_accepted'])<p class="text-muted">Mail-server acceptance is not yet confirmed for every approved email.</p>@endif
@include('backend.partials.mail-report',['report'=>$historyReport,'reportEvent'=>$event,'reportTitle'=>$reportContext['report_scope']==='all' ? 'All event email history' : ($reportContext['report_scope']==='invitations' ? 'Invitation email history' : 'Selected batch email history')])
<details class="card card-body mb-4" @if($drafts->isNotEmpty()) open @endif><summary class="h5 mb-0">Messages awaiting review</summary><div class="mt-3">
@forelse($drafts as $draft)<div class="mb-3"><strong>{{ $draft->subject }}</strong><p>A player action prepared this email. It has not been sent.</p><form method="post" action="{{ route('backend.event-communications.drafts.preview',[$event,$draft]) }}">@csrf<button class="btn btn-outline-primary">Review and approve draft</button></form></div>@empty<p>No system messages awaiting review.</p>@endforelse
@if($drafts instanceof \Illuminate\Contracts\Pagination\Paginator){{ $drafts->withQueryString()->links('pagination::bootstrap-5') }}@endif
</div></details>
<h3 class="h5">Reviewed campaigns</h3>
@forelse($batches as $item)<a class="d-block mb-2 text-break" href="{{ route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','batch','report_scope']),['batch'=>$item->id,'report_scope'=>'batch'])) }}#email-history">{{ $item->subject }} - {{ count($item->recipients) }} emails - {{ $item->approved_at ? 'Approved' : 'Awaiting approval' }} - {{ $item->created_at }}</a>@empty<p>No email previews yet.</p>@endforelse
{{ $batches->withQueryString()->links('pagination::bootstrap-5') }}
@if($batch && $batch->issues)<details class="mt-3"><summary>Selected batch: missing contacts / excluded recipients</summary><ul>@foreach($batch->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>@endif
<script>
const scopeSelect = document.getElementById('communication-scope');
const statusFilter = document.getElementById('communication-filter');
const statusOptions = [...statusFilter.options].map(option => option.cloneNode(true));
let previousStatus = statusFilter.value;
let rosterFilterActive = false;
function updateScope(){
    const roster = @json($event->isTeam()) && ['all', 'region', 'team'].includes(scopeSelect.value);
    if (roster && !rosterFilterActive) {
        previousStatus = statusFilter.value;
        statusFilter.replaceChildren(new Option('All players on the roster', 'all', true, true));
    } else if (!roster && rosterFilterActive) {
        statusFilter.replaceChildren(...statusOptions.map(option => option.cloneNode(true)));
        statusFilter.value = previousStatus;
    }
    rosterFilterActive = roster;
    const ranking=scopeSelect.value==='rankings'; statusFilter.closest('.col-md-4').hidden=ranking; document.getElementById('communication-recipients').closest('.col-md-4').hidden=ranking;document.querySelectorAll('[data-scope]').forEach(el=>{el.hidden=el.dataset.scope!==scopeSelect.value; el.querySelectorAll('select,input').forEach(select=>{select.disabled=el.hidden;});});
}
scopeSelect.addEventListener('change',updateScope); updateScope();
(() => {
    const input = document.getElementById('communication-individual');
    const key = document.getElementById('communication-individual-key');
    const results = document.getElementById('individual-search-results');
    const status = document.getElementById('individual-search-status');
    const searchUrl = @json(route('backend.event-communications.index', $event));
    let timer, controller, requestNumber = 0;
    function closeResults() {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    }
    function dismissResults() {
        requestNumber++;
        clearTimeout(timer);
        controller?.abort();
        closeResults();
    }
    function showResults(individuals) {
        results.replaceChildren();
        individuals.forEach(individual => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = individual.name;
            button.addEventListener('click', () => {
                requestNumber++;
                clearTimeout(timer);
                controller?.abort();
                key.value = individual.key;
                input.value = individual.name;
                input.setCustomValidity('');
                status.textContent = 'Selected: ' + individual.name;
                closeResults();
                input.focus();
            });
            results.append(button);
        });
        results.hidden = individuals.length === 0;
        input.setAttribute('aria-expanded', String(individuals.length > 0));
        status.textContent = individuals.length
            ? 'Choose a match below.' + (individuals.length === 100 ? ' Showing up to 100 matches; keep typing to narrow the results.' : '')
            : 'No matching individuals in your event scope.';
    }
    async function search(term, currentRequest) {
        if (!term) {
            status.textContent = 'Type a name to find an individual.';
            return;
        }
        controller = new AbortController();
        status.textContent = 'Searching…';
        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('individual_search', term);
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            if (currentRequest === requestNumber) showResults(data.individuals);
        } catch (error) {
            if (currentRequest === requestNumber && error.name !== 'AbortError') {
                status.textContent = 'Search unavailable. Type again to retry.';
                closeResults();
            }
        }
    }
    input.addEventListener('input', () => {
        key.value = '';
        input.setCustomValidity('');
        clearTimeout(timer);
        controller?.abort();
        closeResults();
        const currentRequest = ++requestNumber;
        const term = input.value.trim();
        status.textContent = term ? 'Searching…' : 'Type a name to find an individual.';
        timer = setTimeout(() => search(term, currentRequest), 250);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' && !results.hidden) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        }
        if (event.key === 'Escape') dismissResults();
        if (event.key === 'Enter' && !key.value) event.preventDefault();
    });
    results.addEventListener('keydown', event => {
        const buttons = [...results.querySelectorAll('button')];
        const index = buttons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            buttons[index + (event.key === 'ArrowDown' ? 1 : -1)]?.focus();
        }
        if (event.key === 'Escape') {
            dismissResults();
            input.focus();
        }
    });
    input.form.addEventListener('submit', event => {
        if (scopeSelect.value === 'individual' && !key.value) {
            event.preventDefault();
            input.setCustomValidity('Choose an individual from the matching results.');
            input.reportValidity();
        }
    });
    scopeSelect.addEventListener('change', () => {
        input.setCustomValidity('');
        if (scopeSelect.value !== 'individual') {
            requestNumber++;
            clearTimeout(timer);
            controller?.abort();
            closeResults();
        } else if (input.value.trim() && !key.value) {
            search(input.value.trim(), ++requestNumber);
        }
    });
    if (key.value) {
        status.textContent = 'Selected: ' + input.value;
    } else if (input.value.trim()) {
        search(input.value.trim(), ++requestNumber);
    }
})();
</script>
@endsection
