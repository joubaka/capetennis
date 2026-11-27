@php
  $ready = true;
  try { $planner->validateProposal($plan->proposal); } catch (\Illuminate\Validation\ValidationException $e) { $ready = false; $blocked = $e->validator->errors()->first(); }
  $stale = $plan->expires_at->isPast() || !hash_equals($plan->fingerprint, $source['fingerprint']);
  $savedExcluded = $plan->proposal['excluded_category_ids'] ?? [];
  $displayExcluded = array_map('intval', old('excluded_categories', $savedExcluded));
@endphp
<section id="ctr-review" aria-labelledby="ctr-board-heading">
  <div data-step-panel="2"><h2 class="h5" id="ctr-board-heading">2. Choose groups for each category</h2>
  <p class="small text-muted">Choose each category's split, then review or move its players. Players without a usable CTR start in the last groups. Larger groups play every opponent once and need more court time; any two-match groups need your explicit review.</p></div>
  <form id="ctr-placement-form" method="POST" action="{{ route('backend.ctr-planner.review', $event) }}" data-event-id="{{ $event->id }}" data-has-errors="{{ $errors->any() ? '1' : '0' }}" data-stale="{{ $stale ? '1' : '0' }}">@csrf
    <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
    <div data-step-panel="2">
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
        $currentLayout = implode('+', array_map(fn ($group) => count($group['rows']), $displayGroups));
        $layoutOptions = $planner->layouts($categoryRows->count());
      @endphp
      <section class="ctr-category-board" data-ctr-category data-category-id="{{ $categoryId }}" data-six="{{ $six ? '1' : '0' }}" data-straight-groups="{{ implode(',', $straightGroups) }}" data-player-total="{{ $categoryRows->count() }}">
        <div class="ctr-board-heading">
          <div><h3 class="h5 mb-1">{{ $categoryRows->first()['category'] }}</h3><p class="small text-muted mb-0">{{ ['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'Green/Yellow CTR'][$categoryRows->first()['pool']] ?? 'Ball level needs review' }} · {{ $categoryRows->count() }} players</p></div>
          <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="excluded_categories[]" value="{{ $categoryId }}" data-exclude-category @checked($excluded)><span class="form-check-label">Exclude from this draw plan</span></label>
        </div>
        <div class="border rounded p-3 my-3" data-category-layout>
          <label class="form-label" for="ctr-layout-{{ $categoryId }}">Choose group sizes for these {{ $categoryRows->count() }} players</label>
          <select id="ctr-layout-{{ $categoryId }}" class="form-select" name="category_layouts[{{ $categoryId }}]" data-layout-choice @disabled($excluded)>
            <option value="{{ $currentLayout }}">Current groups: {{ $currentLayout }}{{ $straightGroups ? ' (linked finals)' : '' }}</option>
            @foreach($layoutOptions as $sizes)
              @php
                $layout = implode('+', $sizes);
                $matchCount = array_sum(array_map(fn ($size) => $size * ($size - 1) / 2, $sizes));
              @endphp
              <option value="{{ $layout }}">{{ $layout }} · {{ count($sizes) }} {{ count($sizes) === 1 ? 'group' : 'groups' }} · {{ $matchCount }} matches · {{ implode('/', array_map(fn ($size) => $size - 1, $sizes)) }} per player</option>
            @endforeach
          </select>
          <label class="form-label small mt-2" for="ctr-custom-layout-{{ $categoryId }}">Or enter exact sizes, for example 4+4+8</label>
          <input id="ctr-custom-layout-{{ $categoryId }}" class="form-control" name="custom_layouts[{{ $categoryId }}]" maxlength="3200" data-custom-layout @disabled($excluded)>
          <p class="small text-muted mt-2 mb-2">Each group needs 3–16 players. Options list up to 100 splits; custom sizes cover other layouts. Applying a split resets only this category's placements in rating-band order and uses independent round robins without linked finals. Larger groups need more court time.</p>
          <button class="btn btn-outline-primary" type="submit" name="regroup_category" value="{{ $categoryId }}" data-apply-layout @disabled($excluded)>Apply these group sizes</button>
          <p class="small text-muted mt-1 mb-0">Finish saving player moves before applying a different split. This saves the private plan only.</p>
        </div>
        @if($categoryRows->count() < 3)<p class="alert alert-warning py-2" data-category-minimum @if($excluded) hidden @endif>Not enough players. At least three are needed. You can exclude this category from the plan; its entries and payments are kept.</p>@endif
        @if($differentBands)<p class="small text-muted">Separate comparison bands are shown. Their CTR values cannot be compared with each other.</p>@endif
        @if($straightGroups)<p class="alert alert-info py-2" data-six-description data-ctr-competition data-kind="straight_finals">Groups {{ implode(' and ', $straightGroups) }} form one six-player competition, three players each. After the round robins: A1 vs B1 for the winner, A2 vs B2 for places 3–4, A3 vs B3 for places 5–6. Nine matches, one winner prize and one sportsmanship prize.</p>@endif
        <p class="small text-muted" data-excluded-note @if(!$excluded) hidden @endif>Excluded from creation. All players remain registered. <span data-exclusion-next-step hidden>Changes save automatically. <a href="#ctr-board-status" data-review-exclusion>Check save status</a>.</span></p>
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
              <p class="small text-warning" data-large-group-warning @if(count($display['rows']) <= 5) hidden @endif>Large round robin: check the matches per player and available court time before confirming.</p>
              <ul class="ctr-player-list" data-player-list aria-label="Group {{ $display['number'] }} players">
                @foreach($display['rows'] as $row)
                  @include('backend.draw.ctr-player-card', ['displayGroup' => $display['number']])
                @endforeach
              </ul>
              <p class="ctr-group-warning" data-group-warning @if(count($display['rows']) >= 4 && count($display['rows']) <= 16 || count($display['rows']) === 3 && in_array($display['number'], $straightGroups, true)) hidden @endif>
                @if(count($display['rows']) === 3 && !in_array($display['number'], $straightGroups, true))
                  2 matches per player — below the target of three. Review this exception.
                @else
                  Each included group needs 3–16 players.
                @endif
              </p>
            </article>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-add-group @if($straightGroups) hidden @endif>Add an empty group</button>
      </section>
    @endforeach
    <div class="ctr-manual-review" data-manual-review @if(!collect($plan->proposal['rows'])->pluck('component')->filter()->unique()->count()) hidden @endif>
      <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="manual_reviewed" value="1" data-manual-ack @checked(old('manual_reviewed', $plan->proposal['manual_reviewed']))><span class="form-check-label">I reviewed placements involving different CTR comparison bands.</span></label>
      <p class="small text-muted mt-1 mb-0">Required when a group or linked competition spans ratings that cannot be compared.</p>
    </div>
    <div class="ctr-manual-review" data-short-review>
      <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="short_matches_reviewed" value="1" data-shortfall-ack @checked(old('short_matches_reviewed', $plan->proposal['short_matches_reviewed'] ?? false))><span class="form-check-label">I accept two matches per player in the highlighted independent three-player groups, below the target of three.</span></label>
      <p class="small text-muted mt-1 mb-0">Required for any independent group of three in your chosen split. You can instead rebalance the groups or exclude the category from this plan. No rematches are added automatically.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary" data-step-target="1">Back to players</button>
    <button type="button" class="btn btn-primary" data-step-next="3">Continue to scoring</button>
    </div>
    @php
      $scoring = old('scoring', $plan->proposal['scoring'] ?? []);
      $scoreFormats = \App\Domain\Draws\Services\TennisScoreFormat::catalog();
    @endphp
    <section class="card card-body my-3" data-step-panel="3">
      <h2 class="h5">3. Choose scoring and match timing</h2>
      <p>Use timed matches or choose the set format. These settings apply to every competition in this plan.</p>
      <label class="form-label" for="ctr-score-format">Match scoring</label>
      <select class="form-select mb-3" id="ctr-score-format" name="scoring[score_format]" data-scoring-input>
        <option value="">Choose timed matches or a set format</option>
        @foreach($scoreFormats as $key => $format)<option value="{{ $key }}" @selected(($scoring['score_format'] ?? '') === $key)>{{ $format['label'] }}</option>@endforeach
      </select>
      <label class="form-label" for="ctr-playoff-score-format">Linked finals scoring</label>
      <select class="form-select mb-3" id="ctr-playoff-score-format" name="scoring[playoff_score_format]" data-scoring-input><option value="">Same as round robin</option>@foreach($scoreFormats as $key => $format)<option value="{{ $key }}" @selected(($scoring['playoff_score_format'] ?? '') === $key)>{{ $format['label'] }}</option>@endforeach</select>
      <p class="small text-muted">Used only by any remaining linked six-player competition.</p>
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-6"><label class="form-label" for="ctr-match-duration">Match duration / estimated court time (minutes)</label><input class="form-control" id="ctr-match-duration" type="number" name="scoring[match_duration_minutes]" min="1" max="240" value="{{ $scoring['match_duration_minutes'] ?? '' }}" data-scoring-input><p class="small text-muted">For timed matches this is the playing duration. For sets it is a scheduling estimate.</p></div>
        <div class="col-12 col-md-6"><label class="form-label" for="ctr-changeover">Changeover between matches (minutes)</label><input class="form-control" id="ctr-changeover" type="number" name="scoring[changeover_minutes]" min="0" max="60" value="{{ $scoring['changeover_minutes'] ?? '' }}" data-scoring-input></div>
      </div>
      <div><button type="button" class="btn btn-outline-secondary" data-step-target="2">Back to groups</button> <button type="button" class="btn btn-primary" data-step-next="4">Continue to review</button></div>
    </section>
    <div id="ctr-board-status" role="status" aria-live="polite"></div>
    <div id="ctr-save-error" class="alert alert-danger" role="alert" hidden><p data-save-error-message class="mb-2"></p><a id="ctr-reload-plan" href="{{ route('backend.ctr-planner.show', [$event, 'plan' => $plan->token]) }}">Reload saved plan</a><span class="small d-block mt-1">Reloading discards changes that have not been saved.</span></div>
    <div class="ctr-review-bar"><div><p id="ctr-prize-total">{{ count($competitions) }} winner prizes + {{ count($competitions) }} sportsmanship prizes = {{ count($competitions) * 2 }} prizes</p><p class="small text-muted mb-0" id="ctr-programme-total">{{ count($competitions) }} competitions · {{ array_sum(array_column($competitions, 'matches')) }} matches</p></div><button id="ctr-review-changes" class="btn btn-outline-primary" type="submit">Save changes &amp; review</button></div>
    <noscript><p class="alert alert-info">Choose each player's destination group, then press Save changes &amp; review to see the updated groups.</p></noscript>
  </form>
  <p class="alert alert-warning" id="ctr-blocked" @if($ready) hidden @endif>{{ $blocked ?? '' }}</p>
  @if($stale)<p class="alert alert-warning">This preview expired or uses an earlier grouping rule. Press Plan all draws for a fresh preview.</p>@endif
  <div data-step-panel="4">
  <h2 class="h5 mt-3">4. Review and create</h2>
  <p>Check the included players, group sizes, match totals, prizes and scoring. Creation saves private draws; scheduling comes next.</p>
  <p id="ctr-scoring-summary" class="fw-semibold">{{ isset($scoring['score_format']) ? ($scoreFormats[$scoring['score_format']]['label'] ?? 'Choose scoring') : 'Choose scoring' }} @if(isset($scoring['match_duration_minutes'])) · {{ $scoring['match_duration_minutes'] }} minutes + {{ $scoring['changeover_minutes'] ?? '' }} minutes changeover @endif</p>
  <ul data-review-group-summary>@foreach($competitions as $competition)<li>{{ $competition['name'] }} · {{ implode('+', array_map(fn ($group) => count($group['rows']), $competition['groups'])) }} players · {{ $competition['matches'] }} matches</li>@endforeach</ul>
  <button type="button" class="btn btn-outline-secondary mb-3" data-step-target="3">Back to scoring</button>
  <form method="POST" action="{{ route('backend.ctr-planner.apply', $event) }}" id="ctr-confirm-form" class="mb-4">@csrf
    <input type="hidden" name="token" value="{{ $plan->token }}"><input type="hidden" name="revision" value="{{ $planner->revision($plan) }}">
    <button id="ctr-confirm" class="btn btn-primary" type="submit" @disabled(!$ready || $stale || $errors->any())>Confirm and create all draws</button>
    <p class="small text-muted mt-2">Changes save automatically on this page. Wait for Saved before confirming. Confirmation creates the saved plan privately, without scheduling or publication. Plans expire after 30 minutes.</p>
  </form>
  </div>
</section>
<template id="ctr-empty-group-template"><article class="ctr-group-card" data-ctr-group><div class="ctr-group-header"><h4></h4><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-group>Remove empty group</button></div><p class="ctr-group-meta"><span data-group-count>0</span> players · <span data-group-matches>0</span> round-robin matches · <span data-group-games>0</span> matches per player</p><p class="small text-warning" data-large-group-warning hidden>Large round robin: check the matches per player and available court time before confirming.</p><ul class="ctr-player-list" data-player-list></ul><p class="ctr-group-warning" data-group-warning hidden>Each included group needs 3–16 players.</p></article></template>
