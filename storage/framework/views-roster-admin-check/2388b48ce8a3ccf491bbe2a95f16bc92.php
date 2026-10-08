
<?php $__env->startSection('title', 'Event communications'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceActive' => 'communications', 'eventWorkspaceRegionalOnly' => $teamAudience && !app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event, auth()->user())], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-mail-log.index',$event)); ?>">Event email log</a><a class="btn btn-outline-secondary" href="#email-history">Email history</a></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['success'=>'success','warning'=>'warning','error'=>'danger','info'=>'info']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flash=>$style): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session($flash)): ?><div class="alert alert-<?php echo e($style); ?>" role="status"><?php echo e(session($flash)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="mb-4">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamAudience): ?>
<form method="get" class="mb-3"><input type="hidden" name="compose" value="1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = request()->except(['team_search','compose']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_scalar($value)): ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><label>Find a team <input name="team_search" value="<?php echo e(request('team_search')); ?>" class="form-control" maxlength="100"></label><button class="btn btn-outline-primary">Search teams</button></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<h4><?php echo e($event->name); ?> — Send an email</h4>
<p>Choose who you want to email, write your message, then check it before sending.</p>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<form method="post" action="<?php echo e(route('backend.event-communications.preview', $event)); ?>" data-mail-compose class="card card-body mb-4"><?php echo csrf_field(); ?>
<?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php
$composeOptions = session('compose_options', []);
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['category_event_id','registration_id','direct_email']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(old($field,$composeOptions[$field] ?? null)): ?><input type="hidden" name="<?php echo e($field); ?>" value="<?php echo e(old($field,$composeOptions[$field] ?? '')); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="communication-scope">Who is this for?</label><select class="form-select" id="communication-scope" name="scope"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($teamAudience ? ['all'=>'Everyone you can contact for this event','nominations'=>'All nominated players','region'=>'A region','team'=>'A team','individual'=>'A player'] : (['all'=>'All event players','registrations'=>'Registered players','nominations'=>'Nominated players','individual'=>'A player'] + ($event->isMasters() ? ['invitations'=>'Invited players'] : []))); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if(old('scope', $composeOptions['scope'] ?? ($search !== '' ? 'individual' : 'all'))===$key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php if($canRankingMail): ?><option value="rankings" <?php if(old('scope')==='rankings'): echo 'selected'; endif; ?>>Regional rankings</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php if(in_array(old('scope',$composeOptions['scope'] ?? null),['direct','legacy_registered'],true)): ?><option value="<?php echo e(old('scope',$composeOptions['scope'] ?? '')); ?>" selected>Original selected recipients (review required)</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-md-4"><label class="form-label" for="communication-filter">Player status</label><select class="form-select" id="communication-filter" name="filter"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($teamAudience ? ['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','declined'=>'Declined','withdrawn'=>'Withdrawn','reserves'=>'Reserves'] : (['all'=>'Any status','not_registered'=>'Not registered / unpaid','payment_pending'=>'Checkout pending','paid'=>'Paid','withdrawn'=>'Withdrawn'] + ($event->isMasters() ? ['declined'=>'Declined','reserves'=>'Reserves'] : []))); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if(old('filter', $composeOptions['filter'] ?? 'all')===$key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-md-4"><label class="form-label" for="communication-recipients">Send to</label><select class="form-select" id="communication-recipients" name="recipients"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamAudience): ?><option value="both" <?php if(old('recipients',$composeOptions['recipients'] ?? 'both')==='both'): echo 'selected'; endif; ?>>Players, parents and regional managers</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><option value="players" <?php if(old('recipients', $composeOptions['recipients'] ?? ($teamAudience ? 'both' : 'players'))==='players'): echo 'selected'; endif; ?>>Players and parents</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamAudience): ?><option value="managers" <?php if(old('recipients')==='managers'): echo 'selected'; endif; ?>>Regional managers only</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamAudience): ?><div class="form-text">Regional managers receive your message for each selected team in their region.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamAudience): ?>
<div class="col-md-4" data-scope="region"><label class="form-label" for="communication-region">Region</label><select class="form-select" id="communication-region" name="region_id"><option value="">Choose a region</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($region->region_id); ?>" <?php if((string)old('region_id', $composeOptions['region_id'] ?? '')===(string)$region->region_id): echo 'selected'; endif; ?>><?php echo e($region->region?->region_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-md-4" data-scope="team"><label class="form-label" for="communication-team">Team</label><select class="form-select" id="communication-team" name="team_id"><option value="">Choose a team</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($team->id); ?>" <?php if((string)old('team_id', $composeOptions['team_id'] ?? '')===(string)$team->id): echo 'selected'; endif; ?>><?php echo e($team->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="col-md-6" data-scope="individual">
<label class="form-label" for="communication-individual">Individual</label>
<input class="form-control" id="communication-individual" name="individual_search_name" value="<?php echo e(old('individual_search_name', $search)); ?>" maxlength="100" autocomplete="off" placeholder="Type a name and choose a match" aria-describedby="individual-search-status" aria-controls="individual-search-results" aria-expanded="false">
<input type="hidden" id="communication-individual-key" name="individual_key" value="<?php echo e(old('individual_key', $composeOptions['individual_key'] ?? '')); ?>">
<div id="individual-search-status" class="form-text" role="status" aria-live="polite">Type a name to find an individual.</div>
<div id="individual-search-results" class="list-group mt-2" aria-label="Matching individuals" hidden></div>
<noscript><p class="form-text">Enable JavaScript to search for an individual.</p></noscript>
</div>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRankingMail): ?>
<div data-scope="rankings" class="mt-3 border rounded p-3">
<h5>Regional rankings  -  players and parents</h5>
<p>Only current published rankings from linked regions are used. Each selected region must have a linked source and one published run. Exclusions do not renumber the original positions; tied ranks include every player at that position.</p>
<div class="row g-3">
<div class="col-md-6"><label for="ranking-regions" class="form-label">Regions (leave empty for all event regions)</label><select multiple size="5" class="form-select" id="ranking-regions" name="ranking_region_ids[]"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($region->region_id); ?>" <?php if(in_array($region->region_id,old('ranking_region_ids',[]))): echo 'selected'; endif; ?>><?php echo e($region->region?->region_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-md-6"><label for="ranking-lists" class="form-label">Ranking lists (leave empty for all selected regions -  lists)</label><select multiple size="5" class="form-select" id="ranking-lists" name="ranking_list_ids[]"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingLists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($list->id); ?>" <?php if(in_array($list->id,old('ranking_list_ids',[]))): echo 'selected'; endif; ?>><?php echo e($regions->filter(fn($region)=>(int)$region->rankingSource?->series_id===(int)$list->series_id)->map(fn($region)=>$region->region?->region_name)->implode(', ')); ?>  -  <?php echo e($list->category?->name); ?> (#<?php echo e($list->id); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-12"><label for="rank-numbers" class="form-label">Original rank numbers</label><input id="rank-numbers" class="form-control" name="rank_numbers" maxlength="1000" value="<?php echo e(old('rank_numbers')); ?>" placeholder="All ranks, or 9, 11, 14-18"><div class="form-text">The same numbers apply within every selected list. Leave empty for everyone.</div></div>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['team_listed'=>'Players currently on any team list in this event (including unpaid players)','declined'=>'Players who declined','reserves'=>'Reserves','withdrawn'=>'Withdrawn players']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flag=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label class="d-block mt-2"><input type="checkbox" name="exclude_<?php echo e($flag); ?>" value="1" <?php if(old('exclude_'.$flag)): echo 'checked'; endif; ?>> Exclude <?php echo e($label); ?></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingLists->isEmpty()): ?><p class="alert alert-warning mt-3">No linked ranking lists are available. Link the region ranking sources before using this audience.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<label class="form-label mt-3" for="communication-subject">Subject</label><input class="form-control" id="communication-subject" name="subject" required maxlength="200" value="<?php echo e(old('subject', session('compose_subject', $event->name.' — Update'))); ?>">
<label class="form-label mt-3" for="communication-body">Message</label><textarea class="form-control" id="communication-body" name="body" rows="7" required maxlength="30000"><?php echo e(old('body',session('compose_body'))); ?></textarea>
<p class="form-text">Write an update, share arrangements or send a payment or clothing reminder. Only your message is included. Review one example and approve here before sending.</p>
<button class="btn btn-primary align-self-start">Preview email</button>
</form>
</div>
<div class="d-flex flex-wrap gap-2 mb-3" aria-label="Email report scope">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['all'=>'All event emails','invitations'=>'Invitations only']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scopeKey=>$scopeLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','report_scope','batch']),['report_scope'=>$scopeKey]))); ?>#email-history" <?php if($reportContext['report_scope']===$scopeKey): ?> aria-current="page" <?php endif; ?>><?php echo e($scopeLabel); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','report_scope']),['batch'=>$batch->id,'report_scope'=>'batch']))); ?>#email-history" <?php if($reportContext['report_scope']==='batch'): ?> aria-current="page" <?php endif; ?>>This batch only</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch): ?>
<?php ($coveredPlayers = collect($batch->recipients)->flatMap(fn ($recipient) => $recipient['player_keys'] ?? [])->unique()->count()); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($coveredPlayers): ?><p>Selected batch: <?php echo e($coveredPlayers); ?> distinct players covered; <?php echo e($batch->serverAcceptedPlayerCount()); ?> players with a mail-server-accepted contact; <?php echo e(count($batch->recipients)); ?> emails; <?php echo e(count($batch->issues ?? [])); ?> missing contacts.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch && $batch->approved_at && $summary && !$summary['all_server_accepted']): ?><p class="text-muted">Mail-server acceptance is not yet confirmed for every approved email.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.partials.mail-report',['report'=>$historyReport,'reportEvent'=>$event,'reportTitle'=>$reportContext['report_scope']==='all' ? 'All event email history' : ($reportContext['report_scope']==='invitations' ? 'Invitation email history' : 'Selected batch email history')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<details class="card card-body mb-4" <?php if($drafts->isNotEmpty()): ?> open <?php endif; ?>><summary class="h5 mb-0">Messages awaiting review</summary><div class="mt-3">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $drafts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="mb-3"><strong><?php echo e($draft->subject); ?></strong><p>A player action prepared this email. It has not been sent.</p><form method="post" action="<?php echo e(route('backend.event-communications.drafts.preview',[$event,$draft])); ?>" data-mail-launch><?php echo csrf_field(); ?><button class="btn btn-outline-primary">Review and approve draft</button></form></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No system messages awaiting review.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($drafts instanceof \Illuminate\Contracts\Pagination\Paginator): ?><?php echo e($drafts->withQueryString()->links('pagination::bootstrap-5')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></details>
<h3 class="h5">Reviewed campaigns</h3>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $batches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a class="d-block mb-2 text-break" href="<?php echo e(route('backend.event-communications.index',array_merge(['event'=>$event],request()->except(['history_page','batch','report_scope']),['batch'=>$item->id,'report_scope'=>'batch']))); ?>#email-history"><?php echo e($item->subject); ?> - <?php echo e(count($item->recipients)); ?> emails - <?php echo e($item->approved_at ? 'Approved' : 'Awaiting approval'); ?> - <?php echo e($item->created_at); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No email previews yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo e($batches->withQueryString()->links('pagination::bootstrap-5')); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch && $batch->issues): ?><details class="mt-3"><summary>Selected batch: missing contacts / excluded recipients</summary><ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batch->issues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($issue); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<script>
const scopeSelect = document.getElementById('communication-scope');
const statusFilter = document.getElementById('communication-filter');
const statusOptions = [...statusFilter.options].map(option => option.cloneNode(true));
let previousStatus = statusFilter.value;
let rosterFilterActive = false;
function updateScope(){
    const roster = <?php echo json_encode($event->isTeam(), 15, 512) ?> && ['all', 'region', 'team'].includes(scopeSelect.value);
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
    const searchUrl = <?php echo json_encode(route('backend.event-communications.index', $event), 512) ?>;
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\communications\index.blade.php ENDPATH**/ ?>