<?php
  $progressRows = array_reverse(array_slice($profileMatchProgress['rows'] ?? [], 0, 5));
  $progressReason = $profileMatchProgress['reason'] ?? null;
  $progressUrl = route('my.tennis.rating', $selectedPlayer).'#ctr-match-progress-heading';
  $hasPair = static fn (array $row): bool => isset($row['before_score'], $row['after_score'], $row['delta'])
      && is_numeric($row['before_score']) && is_numeric($row['after_score']) && is_numeric($row['delta'])
      && is_finite((float) $row['before_score']) && is_finite((float) $row['after_score']) && is_finite((float) $row['delta'])
      && $row['before_score'] >= 0 && $row['before_score'] <= 100 && $row['after_score'] >= 0 && $row['after_score'] <= 100
      && ($row['reason'] ?? null) === null && abs(round((float) $row['after_score'] - (float) $row['before_score'], 1) - (float) $row['delta']) < .00001;
  $timeLabel = static fn (array $row): ?string => empty($row['time']) ? null : \Carbon\CarbonImmutable::parse($row['time'], 'Africa/Johannesburg')->format('H:i');
  $hasProgressValues = collect($progressRows)->contains($hasPair);
?>
<section class="profile-match-progress" aria-labelledby="profile-match-progress-heading" data-profile-match-progress>
  <style>
    .profile-match-progress{color:#172e45;border:1px solid #dbe4ef;border-top:4px solid #ff9f43;background:#fff;border-radius:16px;margin:0 0 1.25rem;overflow-wrap:anywhere;min-width:0;box-shadow:0 6px 20px #142d5009}
    .profile-match-progress *{min-width:0}.profile-match-progress .pm-heading{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem 1.5rem;padding:clamp(1rem,2vw,1.5rem);background:#142d50;border-radius:12px 12px 0 0;color:#fff}
    .profile-match-progress .pm-eyebrow{color:#ffc182;font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700;display:block;margin-bottom:.4rem}
    .profile-match-progress .pm-heading h2{color:#fff;font-size:clamp(1.2rem,2.7vw,1.55rem);font-weight:700;margin:0}
    .profile-match-progress .pm-heading a{color:#fff;display:inline-flex;align-items:center;gap:.4rem;min-height:44px;font-size:.82rem;text-underline-offset:4px;font-weight:600}
    .profile-match-progress .pm-body{padding:clamp(1rem,2vw,1.5rem)}
    .profile-match-progress .pm-basis{margin:0 0 .35rem;font-size:.82rem;color:#233d58}
    .profile-match-progress .pm-note{color:#52677b;font-size:.78rem;line-height:1.6;margin:0 0 .6rem}
    .profile-match-progress .pm-scroll{max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;border:1px solid #e6edf4;border-radius:12px;background:#fbfcfe}
    .profile-match-progress .pm-scroll svg{width:100%;min-width:640px;max-width:65rem;display:block;margin-inline:auto}
    .profile-match-progress .pm-caption{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.3rem 1rem;margin:.5rem 0 0;font-size:.75rem;color:#52677b}
    .profile-match-progress .pm-values{border-top:1px solid #e4ebf3;margin-top:.85rem}
    .profile-match-progress .pm-values summary{cursor:pointer;min-height:44px;align-content:center;font-size:.82rem;font-weight:600;color:#004177}
    .profile-match-progress .pm-value-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.4rem 1rem;padding:.8rem 0;border-top:1px solid #e4ebf3}
    .profile-match-progress .pm-value-row h3{font-size:.85rem;margin:0 0 .25rem;color:#142d50}
    .profile-match-progress .pm-value-row p{margin:0;font-size:.76rem;color:#52677b}
    .profile-match-progress .pm-numbers{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem 1rem;margin:0;font-variant-numeric:tabular-nums}
    .profile-match-progress .pm-numbers dt{font-size:.68rem;font-weight:500;color:#52677b;margin-bottom:.15rem}.profile-match-progress .pm-numbers dd{font-size:1rem;font-weight:700;color:#145c8d;margin:0}
    .profile-match-progress .pm-numbers .pm-decrease{color:#9e3b10}.profile-match-progress .pm-unavailable{align-self:center;max-width:26rem;font-size:.76rem;color:#52677b}
    .profile-match-progress .pm-scroll:focus-visible,.profile-match-progress a:focus-visible,.profile-match-progress summary:focus-visible{outline:3px solid #b85b00;outline-offset:3px}
    .profile-match-progress .pm-scroll-cue{display:none}.profile-match-progress .pm-pending{padding:1rem;border-radius:10px;background:#f1f5fa;color:#233d58;font-size:.85rem;line-height:1.6}
    @media(max-width:650px){.profile-match-progress .pm-scroll-cue{display:block;color:#52677b;font-size:.75rem;margin:.6rem 0}.profile-match-progress .pm-value-row{grid-template-columns:1fr}.profile-match-progress .pm-heading{align-items:start}.profile-match-progress .pm-numbers{gap:1.5rem}}
  </style>
  <div class="pm-heading">
    <div><span class="pm-eyebrow">Your last five matches</span><h2 id="profile-match-progress-heading">CTR before &amp; after each match</h2></div>
    <a data-my-tennis-link href="<?php echo e($progressUrl); ?>">Explore match explanations <span aria-hidden="true">→</span></a>
  </div>
  <div class="pm-body">
    <p class="pm-basis"><strong>Reconstructed using scheduled match order.</strong></p>
    <p class="pm-note">CTR estimates, not archived ratings or verified completion times. Each pair uses strictly earlier scheduled results, then adds that match; other simultaneous results are excluded.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profileMatchProgress['as_of'] ?? null): ?> Saved through <?php echo e($profileMatchProgress['as_of']); ?>.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressReason !== null): ?>
      <p class="pm-pending mb-0" role="status" data-profile-match-pending><?php echo e($progressReason); ?></p>
    <?php elseif(!$progressRows): ?>
      <p class="pm-pending mb-0" role="status" data-profile-match-pending>No eligible included matches with available details yet. Saved match reconstructions will appear after an overnight update.</p>
    <?php else: ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasProgressValues): ?>
      <p class="pm-scroll-cue">Swipe or scroll across to see every available match. Exact values are also listed below.</p>
      <figure class="mb-0">
        <div class="pm-scroll" tabindex="0" role="region" aria-label="Reconstructed match ratings; scroll horizontally on a narrow screen" data-profile-match-chart>
          <svg viewBox="0 0 660 352" role="group" aria-labelledby="profile-match-chart-title" aria-describedby="profile-match-chart-description">
            <title id="profile-match-chart-title">Latest five eligible matches, oldest to newest, on a fixed 0 to 100 CTR scale</title>
            <desc id="profile-match-chart-description">Each separate arrow compares reconstructed CTR immediately before and after that match. Gaps are deliberate: no continuous rating trend between matches is implied. Exact values, results and unavailable reasons are in the match values list below.</desc>
            <defs><marker id="profile-match-pair-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="context-stroke" /></marker></defs>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [100, 75, 50, 25, 0]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php $tickY = 190 - 1.6 * $tick; ?>
              <line x1="43" y1="<?php echo e($tickY); ?>" x2="640" y2="<?php echo e($tickY); ?>" stroke="#e0e8f1" />
              <text x="33" y="<?php echo e($tickY + 4); ?>" text-anchor="end" font-size="12" fill="#52677b"><?php echo e($tick); ?></text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $progressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php $beforeX = 70 + $index * 116; ?>
              <?php $afterX = $beforeX + 50; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPair($row)): ?>
                <?php
                  $beforeY = 190 - 1.6 * $row['before_score'];
                  $afterY = 190 - 1.6 * $row['after_score'];
                  $ink = $row['delta'] < 0 ? '#b44914' : '#145c8d';
                ?>
                <g data-profile-match-pair data-before="<?php echo e($row['before_score']); ?>" data-after="<?php echo e($row['after_score']); ?>" data-delta="<?php echo e($row['delta']); ?>" role="img" aria-label="<?php echo e($row['date']); ?> vs <?php echo e($row['opponent']); ?>: <?php echo e($row['won'] ? 'Won' : 'Lost'); ?> <?php echo e($row['score']); ?>. Reconstructed before <?php echo e(number_format($row['before_score'], 1)); ?>, after <?php echo e(number_format($row['after_score'], 1)); ?>, change <?php echo e(sprintf('%+.1f', $row['delta'])); ?> CTR.">
                  <line x1="<?php echo e($beforeX); ?>" y1="<?php echo e($beforeY); ?>" x2="<?php echo e($afterX); ?>" y2="<?php echo e($afterY); ?>" stroke="<?php echo e($ink); ?>" stroke-width="3.5" marker-end="url(#profile-match-pair-arrow)" />
                  <circle cx="<?php echo e($beforeX); ?>" cy="<?php echo e($beforeY); ?>" r="4" fill="<?php echo e($ink); ?>"/><circle cx="<?php echo e($afterX); ?>" cy="<?php echo e($afterY); ?>" r="4" fill="<?php echo e($ink); ?>"/>
                  <text x="<?php echo e($beforeX); ?>" y="<?php echo e(max(17, $beforeY - 11)); ?>" text-anchor="middle" font-size="12" font-weight="600" fill="#142d50"><?php echo e(number_format($row['before_score'], 1)); ?></text>
                  <text x="<?php echo e($afterX); ?>" y="<?php echo e(max(17, $afterY - 11)); ?>" text-anchor="middle" font-size="12" font-weight="600" fill="#142d50"><?php echo e(number_format($row['after_score'], 1)); ?></text>
                  <text x="<?php echo e($beforeX); ?>" y="215" text-anchor="middle" font-size="11" fill="#52677b">Before</text><text x="<?php echo e($afterX); ?>" y="215" text-anchor="middle" font-size="11" fill="#52677b">After</text>
                  <rect x="<?php echo e($beforeX - 17); ?>" y="231" width="84" height="26" rx="12" fill="<?php echo e($row['delta'] < 0 ? '#fff0e5' : '#eaf2fb'); ?>"/>
                  <text x="<?php echo e($beforeX + 25); ?>" y="249" text-anchor="middle" font-size="14" font-weight="700" fill="<?php echo e($ink); ?>"><?php echo e($row['delta'] > 0 ? '↑' : ($row['delta'] < 0 ? '↓' : '→')); ?> <?php echo e(sprintf('%+.1f', $row['delta'])); ?></text>
                </g>
              <?php else: ?>
                <text x="<?php echo e($beforeX + 25); ?>" y="115" text-anchor="middle" font-size="12" fill="#52677b"><title><?php echo e($row['reason'] ?? 'Comparable before/after pair unavailable.'); ?></title>Unavailable</text>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <text x="<?php echo e($beforeX + 25); ?>" y="281" text-anchor="middle" font-size="12" fill="#142d50"><title><?php echo e($row['opponent']); ?></title><?php echo e(\Illuminate\Support\Str::limit($row['opponent'], 17)); ?></text>
              <text x="<?php echo e($beforeX + 25); ?>" y="303" text-anchor="middle" font-size="12" fill="#52677b"><?php echo e(\Carbon\CarbonImmutable::parse($row['date'])->format('j M')); ?><?php echo e($timeLabel($row) ? ' · '.$timeLabel($row) : ''); ?></text>
              <text x="<?php echo e($beforeX + 25); ?>" y="326" text-anchor="middle" font-size="12" fill="#142d50"><?php echo e($row['won'] ? 'Won' : 'Lost'); ?> <?php echo e(\Illuminate\Support\Str::limit($row['score'], 16)); ?></text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </svg>
        </div>
        <figcaption class="pm-caption"><span>Oldest → newest · fixed 0–100 scale</span><span>Each arrow is one match’s before/after pair.</span></figcaption>
      </figure>
      <?php else: ?>
        <p class="pm-pending" role="status" data-profile-match-unavailable>Your match results are saved. Before/after CTR is unavailable for these matches; the reason for each is shown below.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <details class="pm-values" <?php if(!$hasProgressValues): ?> open <?php endif; ?>>
        <summary><?php echo e($hasProgressValues ? 'Exact values and match results' : 'Match results and availability'); ?></summary>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $progressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <article class="pm-value-row" data-profile-match-row>
            <div><h3><?php echo e($row['opponent']); ?></h3><p><?php echo e($row['date']); ?><?php echo e($timeLabel($row) ? ' · '.$timeLabel($row) : ''); ?> · <?php echo e($row['won'] ? 'Won' : 'Lost'); ?> <?php echo e($row['score']); ?></p></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPair($row)): ?>
              <dl class="pm-numbers"><div><dt>Before</dt><dd><?php echo e(number_format($row['before_score'], 1)); ?></dd></div><div><dt>After</dt><dd><?php echo e(number_format($row['after_score'], 1)); ?></dd></div><div><dt>Change</dt><dd class="<?php echo \Illuminate\Support\Arr::toCssClasses(['pm-decrease' => $row['delta'] < 0]); ?>"><?php echo e(sprintf('%+.1f', $row['delta'])); ?> CTR</dd></div></dl>
            <?php else: ?>
              <p class="pm-unavailable"><?php echo e($row['reason'] ?? 'Comparable before/after pair unavailable.'); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($row['hidden_matches'] ?? 0) > 0): ?><p class="pm-note"><?php echo e($row['hidden_matches']); ?> evidence details are unavailable, restricted or beyond the display limit. See the full explanation for coverage.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </details>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($profileMatchProgress['restricted'] ?? 0) > 0): ?><p class="pm-note mt-2">Some match details are restricted and are not shown here.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="pm-note mt-2 mb-0">Change is after minus before, not points awarded for a result. Different matches can use different comparison contexts; changes do not add up to a monthly movement.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\profile-match-progress.blade.php ENDPATH**/ ?>