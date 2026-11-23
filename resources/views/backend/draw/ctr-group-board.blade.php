@php
  $ready = true;
  try { $planner->validateProposal($plan->proposal); } catch (\Illuminate\Validation\ValidationException $e) { $ready = false; $blocked = $e->validator->errors()->first(); }
  $stale = $plan->expires_at->isPast() || !hash_equals($plan->fingerprint, $source['fingerprint']);
  $savedExcluded = $plan->proposal['excluded_category_ids'] ?? [];
  $displayExcluded = array_map('intval', old('excluded_categories', $savedExcluded));
@endphp
<section id="ctr-review" aria-labelledby="ctr-board-heading">
  <h2 class="h5" id="ctr-board-heading">Review your groups</h2>
  <p class="small text-muted">Drag players between groups, or choose a group and press Move. Players without a usable CTR start in the last groups. Move them if you know their level. Aim for three matches per player, up to four; any two-match groups need your explicit review.</p>
  <form id="ctr-placement-form" method="POST" action="{{ route('backend.ctr-planner.review', $event) }}" data-has-errors="{{ $errors->any() ? '1' : '0' }}">@csrf
    <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
    @foreach(collect($plan->proposal['rows'])->groupBy('category_id') as $categoryId => $categoryRows)
      @php
        $six = $categoryRows->count() === 6;
        $straightBlock = collect($plan->proposal['straight_blocks'] ?? [])->firstWhere('category_id', (int) $categoryId);
        $straightGroups = $straightBlock['groups'] ?? [];
        $displayRows = $categoryRows->map(function ($row) { $row['group'] = (int) old('placements.'.$row['entry_id'], $row['group']); return $row; });
        $displayGroups = $planner->groups(['rows' => $displayRows->all()]);
        $groupNumbers = array_column($displayGroups, 'number');
        $excluded = in_array((int) $categoryId, $displayExcluded, true);
        $differentBands = $categoryRows->pluck('component')->filter()->unique()->count() > 1;
      @endphp
      <section class="ctr-category-board" data-ctr-category data-category-id="{{ $categoryId }}" data-six="{{ $six ? '1' : '0' }}" data-straight-groups="{{ implode(',', $straightGroups) }}" data-player-total="{{ $categoryRows->count() }}">
        <div class="ctr-board-heading">
          <div><h3 class="h5 mb-1">{{ $categoryRows->first()['category'] }}</h3><p class="small text-muted mb-0">{{ ['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'Green/Yellow CTR'][$categoryRows->first()['pool']] ?? 'Ball level needs review' }} · {{ $categoryRows->count() }} players</p></div>
          <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="excluded_categories[]" value="{{ $categoryId }}" data-exclude-category @checked($excluded)><span class="form-check-label">Exclude from this draw plan</span></label>
        </div>
        @if($categoryRows->count() < 3)<p class="alert alert-warning py-2">Not enough players. At least three are needed. You can exclude this category from the plan; its entries and payments are kept.</p>@endif
        @if($differentBands)<p class="small text-muted">Separate comparison bands are shown. Their CTR values cannot be compared with each other.</p>@endif
        @if($straightGroups)<p class="alert alert-info py-2" data-six-description data-ctr-competition data-kind="straight_finals">Groups {{ implode(' and ', $straightGroups) }} form one six-player competition, three players each. After the round robins: A1 vs B1 for the winner, A2 vs B2 for places 3–4, A3 vs B3 for places 5–6. Nine matches, one winner prize and one sportsmanship prize.</p>@endif
        <p class="small text-muted" data-excluded-note @if(!$excluded) hidden @endif>Excluded from creation. All players remain registered.</p>
        <div class="ctr-group-board" data-group-board>
          @foreach($displayGroups as $display)
            <article class="ctr-group-card" data-ctr-group data-category-id="{{ $categoryId }}" data-group-id="{{ $display['number'] }}" data-straight="{{ in_array($display['number'], $straightGroups, true) ? '1' : '0' }}">
              <div class="ctr-group-header"><h4>Group {{ $display['number'] }}</h4><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-group @if(count($display['rows'])) hidden @endif>Remove empty group</button></div>
              @if(in_array($display['number'], $straightGroups, true))<p class="small text-primary mb-1">Shared six-player competition · Group {{ array_search($display['number'], $straightGroups, true) === 0 ? 'A' : 'B' }}</p>@endif
              <p class="ctr-group-meta"><span data-group-count>{{ count($display['rows']) }}</span> players · <span data-group-matches>{{ count($display['rows']) * (count($display['rows']) - 1) / 2 }}</span> round-robin matches · <span data-group-games>{{ count($display['rows']) - 1 + (in_array($display['number'], $straightGroups, true) ? 1 : 0) }}</span> matches per player
                @if(in_array($display['number'], $straightGroups, true))
                  , including the final
                @endif
              </p>
              <ul class="ctr-player-list" data-player-list aria-label="Group {{ $display['number'] }} players">
                @foreach($display['rows'] as $row)
                  @include('backend.draw.ctr-player-card', ['displayGroup' => $display['number']])
                @endforeach
              </ul>
              <p class="ctr-group-warning" data-group-warning @if(count($display['rows']) >= 4 && count($display['rows']) <= 5 || count($display['rows']) === 3 && in_array($display['number'], $straightGroups, true)) hidden @endif>
                @if(count($display['rows']) === 3 && !in_array($display['number'], $straightGroups, true))
                  2 matches per player — below the target of three. Review this exception.
                @else
                  Each included group needs 3–5 players.
                @endif
              </p>
            </article>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-add-group @if($six) hidden @endif>Add an empty group</button>
      </section>
    @endforeach
    <div class="ctr-manual-review" data-manual-review @if(!collect($plan->proposal['rows'])->pluck('component')->filter()->unique()->count()) hidden @endif>
      <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="manual_reviewed" value="1" data-manual-ack @checked(old('manual_reviewed', $plan->proposal['manual_reviewed']))><span class="form-check-label">I reviewed any group that mixes different CTR comparison bands.</span></label>
      <p class="small text-muted mt-1 mb-0">Only required when you deliberately mix players whose ratings cannot be compared.</p>
    </div>
    <div class="ctr-manual-review" data-short-review>
      <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="short_matches_reviewed" value="1" data-shortfall-ack @checked(old('short_matches_reviewed', $plan->proposal['short_matches_reviewed'] ?? false))><span class="form-check-label">I accept two matches per player in the highlighted independent three-player groups, below the target of three.</span></label>
      <p class="small text-muted mt-1 mb-0">You can instead rebalance the groups or exclude the category from this plan. No rematches are added automatically.</p>
    </div>
    <div id="ctr-board-status" role="status" aria-live="polite"></div>
    <div class="ctr-review-bar"><div><p id="ctr-prize-total">{{ count($competitions) }} winner prizes + {{ count($competitions) }} sportsmanship prizes = {{ count($competitions) * 2 }} prizes</p><p class="small text-muted mb-0" id="ctr-programme-total">{{ count($competitions) }} competitions · {{ array_sum(array_column($competitions, 'matches')) }} matches</p></div><button id="ctr-review-changes" class="btn btn-outline-primary" type="submit">Review placements</button></div>
    <noscript><p class="alert alert-info">Choose each player's destination group, then press Review placements to see the updated groups.</p></noscript>
  </form>
  @if(!$ready)<p class="alert alert-warning" id="ctr-blocked">{{ $blocked }}</p>@endif
  @if($stale)<p class="alert alert-warning">This preview expired or uses an earlier grouping rule. Press Plan all draws for a fresh preview.</p>@endif
  <form method="POST" action="{{ route('backend.ctr-planner.apply', $event) }}" id="ctr-confirm-form" class="mb-4">@csrf
    <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
    <button id="ctr-confirm" class="btn btn-primary" type="submit" @disabled(!$ready || $stale || $errors->any())>Confirm and create all draws</button>
    <p class="small text-muted mt-2">Review changed placements first. Confirmation creates the saved plan privately, without scheduling or publication. Plans expire after 30 minutes.</p>
  </form>
</section>
<template id="ctr-empty-group-template"><article class="ctr-group-card" data-ctr-group><div class="ctr-group-header"><h4></h4><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-group>Remove empty group</button></div><p class="ctr-group-meta"><span data-group-count>0</span> players · <span data-group-matches>0</span> round-robin matches · <span data-group-games>0</span> matches per player</p><ul class="ctr-player-list" data-player-list></ul><p class="ctr-group-warning" data-group-warning hidden>Each included group needs 3–5 players.</p></article></template>
