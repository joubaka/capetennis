<?php $__env->startSection('title', $recordSection === 'opponents' ? 'Opponents' : 'Results'); ?>
<?php $__env->startSection('content'); ?>
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-record ct-player-workspace">
  <style>
    .ct-record{color:#172e45;min-width:0}.ct-record *{min-width:0}.ct-record a{color:#004177}.ct-record .record-banner{border-left:5px solid #ff9f43;background:#172e45;padding:clamp(1rem,3vw,2rem);border-radius:0 18px 18px 0;color:#fff;margin:1rem 0}.ct-record .record-banner h1{color:#fff;font-size:clamp(1.6rem,4vw,2.3rem);margin:.5rem 0}.ct-record .record-banner p{color:#dce8f4;margin:0}.ct-record .record-eyebrow{color:#ffb367;font-weight:700;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase}.ct-record .record-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin:1.25rem 0}.ct-record .record-nav a{padding:.75rem 1rem;border:1px solid #cbd8e6;border-radius:9px;min-height:44px}.ct-record .record-nav a[aria-current=page]{background:#004177;color:#fff;border-color:#004177}.ct-record .record-sheet{padding:1.25rem;background:#fff;border:1px solid #dce5ef;border-radius:14px;margin-bottom:1.25rem;overflow-wrap:anywhere}.ct-record .record-sheet h2{font-size:1.15rem;color:#172e45}.ct-record .record-row{display:grid;grid-template-columns:7rem minmax(0,1fr) auto;gap:1rem;padding:1rem 0;border-top:1px solid #e1e9f1;align-items:start}.ct-record .record-row p{margin:0}.ct-record .record-row time,.ct-record .record-note{font-size:.8rem;color:#52677b}.ct-record .record-score{text-align:right;color:#004177;font-weight:600}.ct-record .record-filter{display:flex;align-items:end;flex-wrap:wrap;gap:.75rem}.ct-record .record-filter select{min-width:10rem}.ct-record .record-filter .btn,.ct-record .record-page a{min-height:44px}.ct-record .record-page{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-top:1rem}.ct-record .record-opponent{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:center;padding:1rem 0;border-top:1px solid #e1e9f1}.ct-record .record-opponent a{padding:.6rem 0;display:inline-block}.ct-record .record-season{display:flex;gap:1rem;justify-content:space-between;padding:.7rem 0;border-top:1px solid #e1e9f1}.ct-record a:focus-visible{outline:3px solid #b85b00;outline-offset:3px}@media(max-width:575px){.ct-record .record-row{grid-template-columns:minmax(0,1fr) auto;gap:.5rem}.ct-record .record-row time{grid-column:1/-1}.ct-record .record-sheet{padding:1rem}.ct-record .record-score{max-width:7rem}}
    .ct-record .record-section-heading { display:flex; flex-wrap:wrap; gap:.3rem 1rem; align-items:baseline; justify-content:space-between; }
    .ct-record .record-section-heading h2 { margin:0; }
    .ct-record .record-section-heading a, .ct-record .record-fixture-link { display:inline-flex; align-items:center; min-height:44px; font-size:.85rem; text-underline-offset:3px; }
    .ct-record .record-upcoming-row time strong { white-space:nowrap; }
    .ct-record .record-rating-comparison { margin:.4rem 0 0; }
    .ct-record .record-rating-comparison summary { min-height:44px; padding:.6rem .15rem; color:#004177; cursor:pointer; }
    .ct-record .record-rating-comparison-body { background:#f6f8fa; border:1px solid #dce5ef; border-radius:10px; padding:clamp(.75rem,2vw,1rem); }
    .ct-record .record-rating-comparison-title { margin:0 0 .65rem; }
    .ct-record .record-rating-comparison-side { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem 1rem; padding:.45rem 0; border-top:1px solid #dce5ef; }
    .ct-record .record-rating-comparison-side .record-player-name > strong { flex:0 1 8rem; }
    .ct-record .record-rating-comparison-side > .record-note { margin-inline-start:auto; }
    .ct-record :is(.record-coverage, .record-rating-comparison) summary:focus-visible { outline:3px solid #b85b00; outline-offset:2px; }
    .ct-record .record-coverage { margin:0 0 1rem; }
    .ct-record .record-coverage summary { cursor:pointer; padding:.6rem 0; width:fit-content; min-height:44px; color:#004177; }
    .ct-record #past-opponents, .ct-record #opponent-meetings { scroll-margin-top:6rem; }
    .ct-record .record-matchup-heading, .ct-record .record-player-name { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .6rem; }
    .ct-record .record-rating-mark { display:inline-flex; flex-wrap:wrap; align-items:center; gap:.2rem .4rem; }
    .ct-record .record-rating-mark > .small { max-width:5rem; }
    .ct-record .record-ball-label { color:#095c60; font-size:.65rem; }
    .ct-record #match-results { scroll-margin-top:6rem; }
    .ct-record .record-month-chart { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:.25rem; text-align:center; }
    .ct-record .record-month-column { height:7rem; display:flex; flex-direction:column; justify-content:flex-end; border-bottom:1px solid #a5b5c6; }
    .ct-record .record-month-column span { display:block; }
    .ct-record .record-month-chart small { font-size:.65rem; }
    .ct-record .record-key { display:inline-block; width:.7rem; height:.7rem; border-radius:2px; }
    .ct-record .record-live-results .record-row{padding:.65rem 0;gap:.65rem}
    .ct-record .recent-results-more summary{cursor:pointer;min-height:44px;align-content:center;font-weight:600;color:#004177;margin-top:.3rem}
    .ct-record .recent-results-more summary:focus-visible{outline:3px solid #b85b00;outline-offset:2px}
    .ct-record .results-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.6rem 1rem; margin-bottom:.75rem; }
    .ct-record .results-toolbar .record-filter { align-items:center; gap:.5rem; }
    .ct-record .results-toolbar .form-select { min-height:44px; min-width:8rem; width:auto; }
    .ct-record .results-form { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; margin:.5rem 0; }
    .ct-record .results-form .badge { min-width:1.65rem; }
    .ct-record .results-grid .record-sheet { padding:1rem; margin-bottom:.85rem; }
    @media(min-width:992px) {
      .ct-record .results-grid { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr); gap:1rem; align-items:start; }
      .ct-record #match-results .record-row { grid-template-columns:6rem minmax(0,1fr) auto; gap:.65rem; align-items:baseline; padding:.55rem 0; }
      .ct-record #match-results .record-row p { display:inline; }
      .ct-record #match-results .record-row p + .record-note { margin-inline-start:.35rem; }
      .ct-record #match-results .record-score { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:0 .45rem; }
      .ct-record #match-results .record-score br { display:none; }
      .ct-record .results-grid .record-comparison-table { min-width:0; }
      .ct-record .results-grid .record-comparison-table :is(th,td) { white-space:normal; }
    }
    @media(max-width:575px) {
      .ct-record .record-upcoming-row { grid-template-columns:minmax(0,1fr); gap:.25rem; }
      .ct-record .record-upcoming-row .record-fixture-link { justify-self:start; }
      .ct-record .record-rating-comparison-side > .record-note { margin-inline-start:0; }
    }
  </style>
  <?php echo $__env->make('frontend.my-tennis.profile-header', ['workspaceTab' => $recordSection, 'ratingPlayer' => $recordPlayer], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'record'): ?>
    <?php echo $__env->make('frontend.my-tennis.record-overview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($liveResults)): ?>
      <?php echo $__env->make('frontend.my-tennis.record-live-results', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents' && isset($upcoming)): ?>
    <?php echo $__env->make('frontend.my-tennis.record-upcoming', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents'): ?>
    <div class="record-section-heading mb-2">
      <h2 id="past-opponents" class="h5">Past opponents</h2>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($upcoming)): ?><a href="#upcoming-heading">Back to upcoming matches ↑</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['reason']): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents'): ?><div class="record-sheet" role="status"><?php echo e($record['reason']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents'): ?>
    <form method="get" class="record-filter <?php echo e($recordSection === 'opponents' ? 'mb-3' : ''); ?>" action="<?php echo e(route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => $recordSection])); ?><?php echo e($recordSection === 'opponents' ? '#past-opponents' : ''); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['opponent'] || request()->integer('opponent')): ?><input type="hidden" name="opponent" value="<?php echo e($record['opponent']['id'] ?? request()->integer('opponent')); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['first', 'second']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comparisonSide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['comparison'][$comparisonSide.'_year'] !== ''): ?><input type="hidden" name="compare_<?php echo e($comparisonSide); ?>" value="<?php echo e($record['comparison'][$comparisonSide.'_year']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['d-flex align-items-center gap-2' => $recordSection === 'record']); ?>"><label for="record-year" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['form-label', 'mb-0' => $recordSection === 'record']); ?>">Season</label><select id="record-year" name="year" class="form-select"><option value="">All seasons</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $record['years']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($year); ?>" <?php if((string)$year === $record['year']): echo 'selected'; endif; ?>><?php echo e($year); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
      <button class="btn btn-outline-primary" type="submit">Apply</button>
    </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents' && $record['stale']): ?><p class="record-note" role="status"><?php echo e($recordSection === 'record' ? 'The saved history and season stats are waiting for their next update.' : 'An update is pending. These are the last saved results.'); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents' && !$record['played']): ?><div class="record-sheet" role="status">No saved match results for this season.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'record'): ?>
      <div class="results-grid">
      <section class="record-sheet" id="match-results">
        <h2>Match results</h2>
        <p class="record-note">Saved match history <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pastRatings ?? []): ?> · Badges show the latest saved CTR.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($record['record_month'])): ?>
          <p class="record-note"><?php echo e(\Carbon\CarbonImmutable::createFromFormat('!Y-m', $record['record_month'])->format('F Y')); ?> · <a href="<?php echo e(request()->fullUrlWithQuery(['record_month' => null, 'page' => null])); ?>#match-results">Clear month filter</a></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$record['results']['total']): ?><p class="record-note" role="status">No saved match results for this month.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo $__env->make('frontend.my-tennis.record-matches', ['matchPage' => $record['results'], 'pageKey' => 'page', 'matchRatings' => $pastRatings ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </section>
      <aside aria-label="Results trends and milestones">
      <?php echo $__env->make('frontend.my-tennis.record-comparison', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['first']): ?>
      <section class="record-sheet"><h2>First recorded matches</h2><p><strong>Earliest recorded match:</strong> <?php echo e($record['first']['date']); ?> · <?php echo e($record['first']['opponent']); ?> · <?php echo e($record['first']['event']); ?></p>
      <p class="record-note"><?php echo e($record['first']['date_basis']); ?>. This covers all seasons with saved results.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['first_win']): ?><p class="mb-0"><strong>Earliest recorded win:</strong> <?php echo e($record['first_win']['date']); ?> · <?php echo e($record['first_win']['opponent']); ?> <span class="record-note">(<?php echo e($record['first_win']['date_basis']); ?>)</span></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></section>
      <section class="record-sheet"><h2>Results by season</h2><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $record['seasons']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $season): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="record-season"><a href="<?php echo e(request()->fullUrlWithQuery(['year' => $season['year'], 'record_month' => null, 'page' => null])); ?>"><?php echo e($season['year']); ?></a><div style="flex:1;max-width:30rem"><span><?php echo e($season['played']); ?> matches · <?php echo e($season['wins']); ?> wins · <?php echo e($season['played'] - $season['wins']); ?> losses</span><div aria-hidden="true" style="display:flex;height:8px;border-radius:5px;overflow:hidden;background:#ff9f43;margin-top:.5rem"><span style="background:#004177;width:<?php echo e(100 * $season['wins'] / $season['played']); ?>%"></span></div></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></section>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </aside>
      </div>
    <?php else: ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['opponent']): ?>
      <?php
        $ownSnapshot = ($workspace['canViewRating'] ?? false) ? ($workspace['snapshot'] ?? null) : null;
        $ownPoints = $ownSnapshot['points'] ?? [];
        $ownRating = !empty($ownSnapshot['stage']) && $ownPoints ? $ownPoints[array_key_last($ownPoints)] : null;
      ?>
      <section class="record-sheet" id="opponent-meetings" tabindex="-1" style="border-top:3px solid #ff9f43" aria-labelledby="matchup-heading">
        <span class="record-note">Selected matchup<?php echo e($record['year'] !== '' ? ' · '.$record['year'] : ' · all available seasons'); ?></span>
        <h2 id="matchup-heading" class="mt-2 record-matchup-heading">
          <?php echo $__env->make('frontend.my-tennis.record-player-name', ['name' => $recordPlayer->full_name, 'rating' => $ownRating, 'stage' => $ownSnapshot['stage'] ?? null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <span>vs</span>
          <?php echo $__env->make('frontend.my-tennis.record-player-name', ['name' => $record['opponent']['name'], 'rating' => $pastRatings[$record['opponent']['id']] ?? null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </h2>
        <p><strong><?php echo e($record['opponent']['wins']); ?> wins · <?php echo e($record['opponent']['played'] - $record['opponent']['wins']); ?> losses</strong> across <?php echo e($record['opponent']['played']); ?> recorded meetings.</p>
        <?php echo $__env->make('frontend.my-tennis.record-opponent-trend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <h3 class="h6 mt-3">Match history</h3>
        <?php echo $__env->make('frontend.my-tennis.record-matches', ['matchPage' => $record['meetings'], 'pageKey' => 'meeting_page', 'matchRatings' => $pastRatings ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </section>
      <?php elseif(request()->filled('opponent')): ?><p id="opponent-meetings" role="status">No saved meetings with this opponent for the selected season.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['opponents']['rows']): ?>
      <section class="record-sheet" aria-labelledby="past-opponents">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $record['opponents']['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($opponentRating = $pastRatings[$opponent['id']] ?? null); ?>
          <a class="record-opponent text-decoration-none" href="<?php echo e(request()->fullUrlWithQuery(['opponent' => $opponent['id'], 'meeting_page' => null])); ?>#opponent-meetings"
            aria-label="View matchup: <?php echo e($recordPlayer->full_name); ?> vs <?php echo e($opponent['name']); ?><?php echo e($opponentRating ? ', CTR '.number_format($opponentRating['score'], 1) : ''); ?>"
            <?php if(($record['opponent']['id'] ?? null) === $opponent['id']): ?> aria-current="true" <?php endif; ?>>
            <div>
              <?php echo $__env->make('frontend.my-tennis.record-player-name', ['name' => $opponent['name'], 'rating' => $opponentRating], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <p class="record-note mb-0"><?php echo e($opponent['played']); ?> meetings · last played <?php echo e($opponent['last']); ?></p>
            </div>
            <span><?php echo e($opponent['wins']); ?> W · <?php echo e($opponent['played'] - $opponent['wins']); ?> L <span aria-hidden="true">→</span></span>
          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo $__env->make('frontend.my-tennis.record-pagination', ['matchPage' => $record['opponents'], 'pageKey' => 'opponent_page'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </section>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordSection === 'opponents'): ?>
    <details class="record-coverage">
      <summary>About these results</summary>
      <p class="record-note mb-0">Saved results<?php echo e($record['coverage_start'] ? ' from '.$record['coverage_start'] : ''); ?> through <?php echo e($record['as_of']); ?>. Some matches may be missing. Final placings are separate from played matches. Estimated match dates use the event start date. The order of matches on the same day may be unknown.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (auth()->user()?->hasRole('super-user'))): ?> Connected TeamSheet match details have limited access.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
    </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('frontend.my-tennis.workspace-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\playing-record.blade.php ENDPATH**/ ?>