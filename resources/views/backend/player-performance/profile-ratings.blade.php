@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', $ratingLabel.' ratings')
@section('page-style')
@include('backend.player-performance.partials.ctr-directory-style')
@endsection
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="ctr-directory">
    <header class="ctr-hero">
        <div><h1>CTR Ratings</h1><p>{{ $ballLevel ? $ratingLabel.' · singles ratings' : 'Cape Tennis all-age singles ratings' }}</p></div>
        <x-ctr-mark size="heading" />
    </header>
    <div class="ctr-summary">
        <span><strong>{{ number_format($players->total()) }}</strong> players · {{ $year }} season</span>
        <span>{{ $snapshot['reason'] ? 'Awaiting rating calculation' : 'Last updated: '.$snapshot['built_at'] }}</span>
    </div>
    <div class="ctr-content">
    @if($refreshStatus['pending'] || $refreshStatus['failed'])
        <div class="ctr-update-status mb-3" role="status">
            <strong>{{ $refreshStatus['failed'] ? 'The latest rating update did not complete.' : 'A rating update is pending.' }}</strong>
            This page shows the latest available saved ratings.
        </div>
    @endif
    <h2 class="ctr-section-title" id="ctr-filter-heading">Filter players</h2>
    @if($allAgesBall)<p class="ctr-intro mb-2">{{ $ratingLabel }} · all ages in one list. Find players by gender, region or name.</p>@else<p class="ctr-intro mb-2">Find players by ball level, age group, gender or name.</p>@endif
    @if($ballLevel)<p class="small mb-2">{{ $ratingLabel }} uses verified singles and valid published final placings for this ball level. Final-placing-only ratings have low confidence. Ball levels have independent scales; compare only players connected to the same reference group.</p>@endif
    @if($diagnosticOrder && !$snapshot['reason'])<div class="alert alert-warning" role="status"><strong>Diagnostic score order — not a calibrated overall ability ranking.</strong> Local scores are not calibrated between comparison groups. These cross-group scores are uncalibrated. List numbers show numeric score order only; they do not establish who is stronger across groups. @if($ageOrder){{ $includeYounger ? 'Numbers apply across the selected ages combined' : 'Numbers restart within each current age group' }}; age does not change rating scores.@endif</div>@endif
    @if($comparisonGroup)<p>Showing comparison group {{ $comparisonGroup }}. <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['ball_type' => $ballType]) }}">Show all groups</a></p>@endif
    @if($withinGroup)<p class="alert alert-info" role="status"><strong>Within-group ratings only.</strong> These players have recorded connections to one another, but no established connection to the shared reference. Compare these estimates only within group {{ $comparisonGroup }}; a matching number in another group does not mean equal ability. Provisional badges and profile ratings carry the same low-confidence, within-group restriction.</p>@endif
    <form method="get" class="ctr-filters mb-3 {{ $allAgesBall ? 'ctr-filters-all-ages' : '' }}" aria-labelledby="ctr-filter-heading">
        @if($comparisonGroup)<input type="hidden" name="comparison_group" value="{{ $comparisonGroup }}">@endif
        @if($comparisonGroup)<input type="hidden" name="ball_type" value="{{ $ballType }}">@else
        <div class="ctr-field ctr-field-mobile-wide"><label for="ball-type" class="form-label">Ball level</label><select name="ball_type" id="ball-type" class="form-select" style="min-height:44px">@foreach(\App\Services\Performance\BallRatingModel::LABELS as $key => $label)<option value="{{ $key }}" @selected($ballType === $key)>{{ $label }}</option>@endforeach<option value="overall" @selected($ballType === 'overall')>Overall CTR · all ball levels</option></select></div>
        @endif
        @unless($allAgesBall)
        <div class="ctr-field ctr-field-mobile-wide"><label for="age-group" class="form-label">Age group / birth year</label><select name="age_group" id="age-group" class="form-select" style="min-height:44px"><option value="">All ages</option>@foreach(array_diff_key(\App\Services\Performance\PlayerRatingProfileDirectory::birthYearLabels($year), ['u7' => true, 'u8' => true]) as $key => $label)<option value="{{ $key }}" @selected($ageGroup === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="ctr-field ctr-field-mobile-wide"><label for="age-scope" class="form-label">Age range</label><select name="age_scope" id="age-scope" class="form-select" aria-describedby="ctr-age-hint" style="min-height:44px"><option value="group" @selected($ageScope === 'group')>Selected age group only</option><option value="younger" @selected($ageScope === 'younger')>This age and younger</option></select><p class="ctr-field-hint" id="ctr-age-hint">Choose whether to include younger players in the selected group.</p></div>
        @endunless
        <div class="ctr-field"><label for="gender" class="form-label">Gender</label><select name="gender" id="gender" class="form-select" style="min-height:44px"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $key => $label)<option value="{{ $key }}" @selected($gender === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="ctr-field"><label for="region" class="form-label">Region</label><select name="region" id="region" class="form-select" aria-describedby="ctr-region-hint" style="min-height:44px"><option value="">All regions</option>@foreach($regionOptions as $option)<option value="{{ $option }}" @selected($region === $option)>{{ $option }}</option>@endforeach</select><p class="ctr-field-hint" id="ctr-region-hint">Recorded team affiliations, including past seasons.</p></div>
        <div class="ctr-field ctr-search"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="ctr-field"><label for="players-per-page" class="form-label">{{ $groupedOrder ? 'Players per list' : 'Players per page' }}</label><select name="per_page" id="players-per-page" class="form-select" style="min-height:44px">@foreach([10, 25, 50, 100, 500] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="ctr-filter-actions">
        @unless($comparisonGroup)<details class="ctr-sort-options" @if($sort !== ($allAgesBall ? 'shared' : 'grouped')) open @endif><summary>More options</summary><label for="rating-sort" class="form-label">List order</label><select name="sort" id="rating-sort" class="form-select" style="min-height:44px">@unless($allAgesBall)<option value="grouped" @selected($sort === 'grouped')>Age groups · Boys and Girls</option>@endunless<option value="shared" @selected($sort === 'shared')>Shared-scale order</option><option value="score" @selected($sort === 'score')>Ranked list — all scores</option>@unless($allAgesBall)<option value="age" @selected($sort === 'age')>Rank by age group</option>@endunless</select></details>@endunless
        <button type="submit" class="btn btn-primary ctr-apply">Show players</button>
        </div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    @if($includeYounger)<p class="small mb-2"><strong>{{ $ageScopeLabel }}</strong> · {{ $groupedOrder ? 'Players from these ages share one list per gender.' : 'Includes all players through this age.' }}</p>@endif
    <div class="ctr-results-intro">
    <h2 class="ctr-section-title">Player results</h2>
    <p class="ctr-result-summary"><strong>{{ number_format($players->total()) }} {{ Str::plural('player', $players->total()) }}</strong> · @if($snapshot['reason'])players appear after a valid rating calculation.@elseif($groupedOrder)Highest to lowest shared-scale rating {{ $includeYounger ? 'across the selected ages, separately by gender' : 'within each age group and gender' }}. Positions restart for each list; players awaiting calibration have no position.@elseif($diagnosticOrder)diagnostic score order, highest to lowest {{ $ageOrder ? ($includeYounger ? 'across the selected ages combined' : 'within each current age group') : 'across all saved scores' }}. List numbers are not overall ability positions.@else{{ $withinGroup ? 'highest to lowest within-group singles rating' : ($combined ? 'highest to lowest shared-scale singles rating' : ($unified ? 'highest to lowest singles rating within the selected comparison group' : 'highest to lowest rating within each current age group and gender')) }}. @if($combined)Players awaiting calibration follow without an overall position.@endif @endif</p>
    <details class="ctr-rating-details"><summary>Rating details</summary>
    <div class="ctr-rating-details-body">
    @if($allAgesBall)<p>Red and Orange each include all ages in one list. Age does not divide or change these ball-level ratings.</p>@else
    <p>{{ $year }} season: groups use the year players were born, from 1 January to 31 December, regardless of whether their birthday has passed. The age filter starts at U9; U9–U13 include that calendar age and younger by default. With “Selected age group only”, U9–U13 each cover one birth year. U18 covers ages 14–18. Use the ball-level filter to find Red and Orange players. These profile groups do not establish event eligibility.</p>@endif
    @unless($ballLevel)<x-rating-explanation />@else
    <p>Each ball level has its own provisional 0–100 singles scale. Valid published final placings can support a low-confidence estimate when no played matches are recorded. Missing identities, invalid or incomplete finishing fields and unpublished final placings do not qualify. Eligible evidence is limited to two years, with recent results carrying more weight. {{ $allAgesBall ? 'Gender filters change' : 'Age and gender filters change' }} the player list, not the estimate. Matching numbers across ball levels or disconnected groups do not establish equal ability.</p>
    @endunless
    @if($sharedCoverage && !$snapshot['reason'])<p>{{ $ballLevel ? $ratingLabel.' coverage' : 'Shared-scale coverage' }}: {{ $sharedCoverage['connected'] }} of {{ $sharedCoverage['eligible'] }} players with eligible singles results within two years are connected to the reference group.</p>@endif
    @if($connectionPlan && !$snapshot['reason'] && $connectionPlan['component_count'] > 1)
        @include('backend.player-performance.connection-plan', ['plan' => $connectionPlan])
    @endif
    @if($snapshot['reason'])
    <p>Players appear after a valid rating calculation confirms their comparison group.</p>
    @elseif($diagnosticOrder)
    <p>All numeric headline scores appear highest to lowest across comparison groups{{ $ageOrder ? ($includeYounger ? ', across the selected ages combined' : ', separately within each current age group') : '' }}. Equal scores use surname, first name, then player ID. List numbers stay the same when searching or paging within the selected age and gender filters. This diagnostic order does not make local estimates comparable.</p>
    @elseif($withinGroup)
    <p>Ordering uses estimates fitted from played singles within the explicitly selected comparison group. {{ $allAgesBall ? 'Gender filters change' : 'Age and gender filters change' }} membership, not the estimated strength. Positions apply only to this group and stay the same when searching or paging. These estimates do not establish a shared scale with other groups.</p>
    @elseif($groupedOrder)
    <p>Boys and Girls appear separately {{ $includeYounger ? 'across the selected ages combined' : 'for each current age group' }}. Shared-scale positions restart in each list and stay the same when searching or paging. Players awaiting calibration follow without a position; their local estimates are not comparable with the shared reference.</p>
    @elseif($combined)
    <p>Players connected to the shared reference appear first, highest rating to lowest. Players awaiting calibration follow alphabetically; their local estimates do not determine an overall position. {{ $allAgesBall ? 'Gender filters change' : 'Age and gender filters change' }} the player list, not the rating. Equal scores use a stable name order. Positions stay the same when searching or paging.</p>
    <p>Scores are out of 100 and provisional. Compare players connected to the same singles reference group. Disconnected players receive a provisional within-group estimate with low confidence until suitable match evidence calibrates them against the reference. @if($ballLevel)Only evidence assigned to {{ \App\Services\Performance\BallRatingModel::LABELS[$ballType] }} contributes to this list. Published final placings provide weaker evidence than played singles.@else Verified completed singles count across all ball colours and court formats, without category-based rating offsets.@endif This is not an official selection ranking.</p>
    <p>Recent results carry more weight. Matches up to two years old can connect groups; comparisons relying on a link older than one year are marked Low confidence — historical connection.</p>
    @elseif($unified)
    <p>Players in the selected shared reference group appear highest rating to lowest. Positions stay the same when searching or paging. Scores are provisional ability estimates, not official selection positions.</p>
    @else
    <p>A shared calculation is pending. Existing separate context scores are not comparable across ages or disconnected groups.</p>
    @endif
    </div>
    </details>
    </div>
    @if($snapshot['reason'])<p class="alert alert-warning">Ratings are currently unavailable. Players will appear when a valid calculation is available.</p>@endif
    @if($groupedOrder)
      @forelse($sections as $ageKey => $section)
        <section class="ctr-results-section mb-4" data-ctr-age-section="{{ $ageKey }}" aria-labelledby="ctr-age-{{ $ageKey }}">
          <h2 class="h5" id="ctr-age-{{ $ageKey }}">{{ $section['label'] }}</h2>
          <div class="ctr-gender-columns {{ $gender !== '' ? 'ctr-single-column' : '' }}">
            @foreach($section['columns'] as $genderKey => $column)
              <div class="ctr-gender-column" data-ctr-gender-column="{{ $genderKey }}">
                <h3 class="h6">{{ \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$genderKey] }} <span class="text-muted">({{ $column->total() }})</span></h3>
                <ul class="list-group mb-3">
                  @forelse($column as $player)
                    @include('backend.player-performance.partials.ctr-directory-row')
                  @empty<li class="list-group-item small text-muted">No matching players.</li>@endforelse
                </ul>
                {{ $column->fragment('ctr-age-'.$ageKey)->links('pagination::simple-bootstrap-5') }}
              </div>
            @endforeach
          </div>
        </section>
      @empty<p>No matching player profiles.</p>@endforelse
    @else
    @forelse($players->getCollection()->groupBy(fn ($player) => $ageOrder ? ($includeYounger ? 'younger' : $player['age_key']) : ($combined ? 'all' : ($unified ? ($player['rating']['component'] ?? 'Unrated') : ($includeYounger ? 'younger' : $player['age_key']).'|'.$player['gender_key']))) as $members)
    <section class="ctr-results-section mb-4" aria-label="{{ $ageOrder ? ($ageScopeLabel ?? $members->first()['age_group']) : ($combined || $unified ? 'All-age singles ratings' : $members->first()['age_group'].' '.$members->first()['gender']) }}">
    <h2 class="h5">{{ $ageOrder ? ($ageScopeLabel ?? $members->first()['age_group']) : ($combined ? ($allAgesBall ? $ratingLabel.' · all ages' : 'All players') : ($withinGroup ? 'Within-group singles ratings' : ($unified ? 'Comparison group '.($members->first()['rating']['component'] ?? 'Unrated') : ($ageScopeLabel ?? $members->first()['age_group']).' · '.$members->first()['gender']))) }}</h2>
    <div class="ctr-column-head" aria-hidden="true"><span>Position</span><span>Player</span><span>CTR</span><span>Evidence</span></div>
    <ul class="list-group mb-3">
    @foreach($members as $player)
        @include('backend.player-performance.partials.ctr-directory-row')
    @endforeach
    </ul></section>
    @empty<p>No matching player profiles.</p>@endforelse
    {{ $players->links('pagination::bootstrap-5') }}
    @endif
    <details class="ctr-other-views"><summary>Other rating views</summary><a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['view' => 'recorded']) }}">Ratings within recorded comparison groups</a></details>
</div></div></div>
@endsection
