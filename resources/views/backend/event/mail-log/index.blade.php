@extends('layouts/layoutMaster')
@section('title', 'Event email log')
@section('page-style')
<style>
.email-log{
    --mail-border:#d9dee6
}
.email-log .mail-summary{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:.65rem
}
.email-log .mail-stat{
    display:block;
    background:#fff;
    border:1px solid var(--mail-border);
    border-radius:.6rem;
    padding:.8rem;
    text-decoration:none;
    color:#344054;
    min-width:0
}
.email-log .mail-stat:hover,.email-log .mail-stat.is-active{
    border-color:#1e4fa3;
    box-shadow:0 0 0 1px #1e4fa3
}
.email-log .mail-stat strong{
    display:block;
    font-size:1.45rem;
    line-height:1.3;
    color:#16253f
}
.email-log .mail-stat span{
    font-size:.8rem
}
.email-log .mail-list{
    border:1px solid var(--mail-border);
    border-radius:.6rem;
    background:#fff;
    overflow:hidden
}
.email-log .mail-grid{
    display:grid;
    grid-template-columns:135px minmax(170px,1fr) minmax(230px,1.65fr) minmax(180px,1fr) 76px;
    gap:1rem;
    align-items:center
}
.email-log .mail-head{
    background:#f4f6fa;
    padding:.7rem 1rem;
    color:#475467;
    font-size:.8rem;
    font-weight:600
}
.email-log .mail-row{
    padding:.9rem 1rem;
    border-top:1px solid var(--mail-border);
    font-size:.875rem
}
.email-log .mail-row>div{
    min-width:0;
    overflow-wrap:anywhere
}
.email-log .mail-row h2{
    font-size:.9rem;
    line-height:1.4;
    margin:0 0 .2rem
}
.email-log .mail-subject a,.email-log .mail-evidence-link{
    color:#164984
}
.email-log .mail-subject a:hover,.email-log .mail-evidence-link:hover{
    color:#0e3565;
    text-decoration:underline
}
.email-log .btn-outline-secondary{
    color:#414c5d;
    border-color:#8793a3
}
.email-log .btn-outline-secondary:hover{
    color:#253145;
    background:#edf0f4
}
.email-log .mail-action .btn{
    min-height:44px;
    min-width:64px;
    white-space:nowrap;
    word-break:normal;
    overflow-wrap:normal;
    display:inline-flex;
    align-items:center;
    justify-content:center
}
.email-log .mail-secondary{
    font-size:.8rem;
    color:#586479
}
.email-log .mail-badge{
    display:inline-block;
    white-space:normal;
    line-height:1.35;
    font-size:.75rem;
    font-weight:600;
    border-radius:.4rem;
    padding:.35rem .55rem
}
.email-log .mail-neutral{
    color:#414c5d;
    background:#edf0f4
}
.email-log .mail-success{
    color:#15542c;
    background:#dff3e5
}
.email-log .mail-info{
    color:#17467e;
    background:#e2edfc
}
.email-log .mail-warning{
    color:#6b4700;
    background:#fff0c4
}
.email-log .mail-danger{
    color:#8c2227;
    background:#fde6e7
}
.email-log .mail-context{
    display:flex;
    flex-wrap:wrap;
    gap:.4rem
}
.email-log .mail-chip{
    font-size:.8rem;
    background:#eef2f7;
    border:1px solid var(--mail-border);
    border-radius:.4rem;
    padding:.3rem .5rem;
    overflow-wrap:anywhere;
    max-width:100%
}
.email-log .pagination{
    flex-wrap:wrap;
    gap:.2rem
}
.email-log .pagination .page-link{
    min-width:2.25rem;
    text-align:center
}
.email-log .mail-filter .form-label{
    font-size:.8rem;
    margin-bottom:.3rem
}
.email-log .mail-advanced summary{
    min-height:44px;
    line-height:44px;
    color:#164984;
    cursor:pointer;
    overflow-wrap:anywhere
}
.email-log .mail-advanced summary:focus-visible{
    outline:2px solid #164984;
    outline-offset:2px
}
.email-log .mail-filter{
    background:#fff;
    border:1px solid var(--mail-border);
    border-radius:.6rem;
    padding:1rem
}
@media(max-width:1199px){
    .email-log .mail-grid{
        grid-template-columns:115px minmax(140px,1fr) minmax(180px,1.5fr) minmax(150px,1fr) 76px;
        gap:.7rem
    }
    .email-log .mail-summary{
        grid-template-columns:repeat(3,minmax(0,1fr))
    }
}
@media(max-width:991px){
    .email-log .mail-head{
        display:none
    }
    .email-log .mail-list{
        background:transparent;
        border:0;
        overflow:visible
    }
    .email-log .mail-row{
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:.7rem;
        background:#fff;
        border:1px solid var(--mail-border);
        border-radius:.6rem;
        margin-bottom:.7rem;
        padding:1rem
    }
    .email-log .mail-subject{
        grid-column:1/-1;
        grid-row:1
    }
    .email-log .mail-recipient{
        grid-column:1/-1;
        grid-row:2
    }
    .email-log .mail-date{
        grid-column:1;
        grid-row:4
    }
    .email-log .mail-outcome{
        grid-column:1/-1;
        grid-row:3
    }
    .email-log .mail-action{
        grid-column:2;
        grid-row:4;
        align-self:center
    }
    .email-log .mail-row h2{
        font-size:1rem
    }
}
@media(max-width:575px){
    .email-log .mail-summary{
        grid-template-columns:repeat(2,minmax(0,1fr))
    }
    .email-log .mail-stat{
        padding:.65rem
    }
    .email-log .mail-advanced summary{
        min-height:44px;
        line-height:44px;
        color:#164984;
        cursor:pointer;
        overflow-wrap:anywhere
    }
    .email-log .mail-advanced summary:focus-visible{
        outline:2px solid #164984;
        outline-offset:2px
    }
    .email-log .mail-filter{
        padding:.8rem
    }
    .email-log .mail-results-heading{
        align-items:flex-start!important
    }
}
</style>
@endsection
@section('content')
<div class="email-log">
@include('backend.event.mail-log.feedback')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h3 mb-1">Email log</h1><p class="mb-0 text-body">{{ $event->name }}</p></div><a class="btn btn-outline-primary align-self-start" href="{{ route('backend.email-issues') }}">My email issues</a></div>
<p class="mail-secondary mb-3">Sent means a completed send. Neither a completed send nor server acceptance proves inbox delivery or reading. Records without a proven event association are excluded.</p>
@php
    $facetFilters = array_filter($filters, fn($value) => $value !== null && $value !== '');
    unset($facetFilters['status'], $facetFilters['outcome']);
    $cards = [''=>'All records','pending'=>'Waiting / sending','sent_complete'=>'Sent','failed'=>'Failed','skipped'=>'Excluded','uncertain'=>'Uncertain'];
    $cardCounts = [''=>$facets['total'],'pending'=>$facets['queued']+$facets['sending'],'sent_complete'=>$facets['sent_complete'],'failed'=>$facets['failed'],'skipped'=>$facets['skipped'],'uncertain'=>$facets['uncertain']];
    $resetFilters = !empty($filters['campaign']) ? ['campaign'=>$filters['campaign']] : [];
@endphp
<h2 class="h6 mb-2">{{ ($filters['audience'] ?? 'all')==='copies' ? 'Copy outcomes' : (($filters['audience'] ?? 'all')==='recipients' ? 'Recipient outcomes' : 'Recipient and copy outcomes') }}</h2>
<div class="mail-summary mb-2" aria-label="Filter records by outcome">
@foreach($cards as $key=>$label)<a class="mail-stat {{ ($outcome ?? '')===$key ? 'is-active' : '' }}" href="{{ route('backend.event-mail-log.index', ['event'=>$event,...$facetFilters,...($key ? ['outcome'=>$key] : [])]) }}" @if(($outcome ?? '')===$key) aria-current="true" @endif><strong>{{ number_format($cardCounts[$key]) }}</strong><span>{{ $label }}</span></a>@endforeach
</div>
<p class="mail-secondary mb-2">Sent includes completed and historical sends with limited evidence, excluding sandbox and uncertain attempts. <a class="mail-evidence-link" href="{{ route('backend.event-mail-log.index',['event'=>$event,...$facetFilters,'outcome'=>'accepted']) }}">Server accepted: {{ number_format($facets['server_accepted']) }}</a></p>
<p class="mail-secondary mb-3">Outcome counts include your permitted {{ ($filters['audience'] ?? 'all')==='copies' ? 'sender/admin copies' : (($filters['audience'] ?? 'all')==='recipients' ? 'recipients' : 'recipients and sender/admin copies') }} and all filters below, except the selected outcome.</p>
@if($facets['unverified'])<p class="mail-secondary">{{ number_format($facets['unverified']) }} completed records have unverified acceptance evidence. Historical records may have limited evidence; this does not mean they failed. <a class="mail-evidence-link" href="{{ route('backend.event-mail-log.index',['event'=>$event,...$facetFilters,'outcome'=>'unverified']) }}">View unverified records</a></p>@endif
@if($errors->any())
<div id="mail-filter-errors" class="alert alert-danger" role="alert"><strong>Check email log filters</strong><ul class="mb-0 mt-1">@foreach(array_slice($errors->all(),0,10) as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@php
    $advancedActive = filled(old('mail_type',$filters['mail_type'] ?? null)) || filled(old('from',$filters['from'] ?? null)) || filled(old('until',$filters['until'] ?? null)) || old('audience',$filters['audience'] ?? 'all') !== 'all' || $errors->has('from') || $errors->has('until');
@endphp
<form method="get" class="mail-filter mb-3" aria-label="Filter email records">
@if(!empty($filters['campaign']))<input type="hidden" name="campaign" value="{{ $filters['campaign'] }}">@endif
<div class="row g-3">
<div class="col-12 col-md-6 col-xl-5"><label class="form-label" for="mail-search">Recipient name, email or subject</label><input id="mail-search" class="form-control" name="search" maxlength="200" placeholder="Search email records" value="{{ old('search',$filters['search'] ?? '') }}"></div>
<div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="mail-outcome">Outcome / evidence</label><select id="mail-outcome" name="outcome" class="form-select"><option value="">All outcomes</option>@foreach($outcomes as $key=>$label)<option value="{{ $key }}" @selected(old('outcome',$outcome ?? '')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12 col-xl-3 d-flex gap-2 align-items-end"><button class="btn btn-primary">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('backend.event-mail-log.index',['event'=>$event,...$resetFilters]) }}">Reset filters</a></div>
</div>
<details class="mail-advanced mt-2" @if($advancedActive) open @endif>
<summary>More filters <span class="mail-secondary">(type, audience, dates)</span>@if($advancedActive)<span class="mail-secondary"> - active</span>@endif</summary>
<div class="row g-3 pt-2">
<div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="mail-type">Email type</label><select id="mail-type" name="mail_type" class="form-select"><option value="">All email types</option>@foreach($types as $key=>$label)<option value="{{ $key }}" @selected(old('mail_type',$filters['mail_type'] ?? '')===$key)>{{ $label }}</option>@endforeach</select>@if($typesLimited)<small class="mail-secondary">Showing the first 100 permitted email types and the selected type if needed.</small>@endif</div>
<div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="mail-audience">Audience</label><select id="mail-audience" name="audience" class="form-select">@foreach(['all'=>'Recipients and copies','recipients'=>'Recipients only','copies'=>'Sender/admin copies'] as $key=>$label)<option value="{{ $key }}" @selected(old('audience',$filters['audience'] ?? 'all')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="mail-from">From date</label><input type="date" id="mail-from" name="from" class="form-control" value="{{ old('from',$filters['from'] ?? '') }}" @error('from') aria-invalid="true" aria-describedby="mail-filter-errors" @enderror></div>
<div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="mail-until">Until date (inclusive)</label><input type="date" id="mail-until" name="until" class="form-control" value="{{ old('until',$filters['until'] ?? '') }}" @error('until') aria-invalid="true" aria-describedby="mail-filter-errors" @enderror></div>
</div></details>
</form>
@if(!empty($filters['campaign']))<div class="mail-context mb-2"><span class="mail-chip">Campaign: {{ $filters['campaign'] }}</span><a class="small align-self-center" href="{{ route('backend.event-mail-log.index',$event) }}">View all event emails</a></div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 mail-results-heading"><h2 class="h6 mb-0">{{ number_format($logs->total()) }} matching {{ \Illuminate\Support\Str::plural('record',$logs->total()) }}</h2><span class="mail-secondary">@if($logs->total())Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }} · @endif Newest first · SAST</span></div>
<div class="mail-context mb-3" aria-label="Active filters">
@foreach(['search'=>'Search','outcome'=>'Outcome','mail_type'=>'Type','from'=>'From','until'=>'Until','audience'=>'Audience'] as $key=>$label)
@php
    $value=$key==='outcome' ? $outcome : ($filters[$key] ?? null);
@endphp
@if($value && !($key==='audience' && $value==='all'))<span class="mail-chip">{{ $label }}: {{ $key==='outcome' ? ($outcomes[$value] ?? $value) : ($key==='mail_type' ? ($types[$value] ?? \App\Services\SuperAdminMailHistory::typeLabel($value)) : $value) }}</span>@endif
@endforeach
</div>
<div class="mail-list">
<div class="mail-grid mail-head" aria-hidden="true"><div>Created</div><div>Recipient</div><div>Message / audience</div><div>Outcome</div><div></div></div>
@forelse($logs as $log)
@php
    $badge = match(true) {
        $log->status==='failed'=>'mail-danger', $log->status==='acceptance_unknown'=>'mail-warning',
        $log->status==='sent' && $log->accepted_at && $log->evidence_status==='server_accepted'=>'mail-success',
        in_array($log->status,['queued','sending']) || ($log->status==='sent' && $log->evidence_status==='sandbox_accepted' && $log->accepted_at)=>'mail-info',
        default=>'mail-neutral',
    };
    $isCopy=in_array(data_get($log->payload,'recipient_kind'),['admin_copy','sender_copy']);
@endphp
<article class="mail-grid mail-row">
<div class="mail-date"><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d M Y') }}</time><div class="mail-secondary">{{ $log->created_at->format('H:i') }} SAST</div></div>
<div class="mail-recipient"><div class="fw-semibold">{{ $log->recipient_name ?: 'Name not recorded' }}</div><div class="mail-secondary">{{ $log->recipient_email ?: 'Email not recorded' }}</div></div>
<div class="mail-subject"><h2><a href="{{ route('backend.event-mail-log.show',[$event,$log]) }}">{{ \App\Services\EventMailLogService::subject($log) }}</a></h2><div class="mail-secondary">{{ \App\Services\SuperAdminMailHistory::typeLabel($log->mail_type) }} · {{ $isCopy ? 'Sender/admin copy' : 'Recipient' }}</div></div>
<div class="mail-outcome"><span class="mail-badge {{ $badge }}">{{ $log->delivery_status_label }}</span></div>
<div class="mail-action"><a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-mail-log.show',[$event,$log]) }}" aria-label="Open email record {{ $log->id }}">Open</a></div>
</article>
@empty<div class="p-4 text-center"><h2 class="h6">No matching email records</h2><p class="mail-secondary">Try a wider date range or reset the filters. Results remain limited to your permitted event audience.</p><a class="btn btn-outline-secondary" href="{{ route('backend.event-mail-log.index',['event'=>$event,...$resetFilters]) }}">Reset filters</a></div>@endforelse
</div>
@if($copySummary['total'])<p class="mail-secondary mt-3 mb-0">{{ number_format($summary['total']) }} recipient records and {{ number_format($copySummary['total']) }} sender/admin copy records match the search, dates, type and campaign before audience/outcome filtering.</p>@endif
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
