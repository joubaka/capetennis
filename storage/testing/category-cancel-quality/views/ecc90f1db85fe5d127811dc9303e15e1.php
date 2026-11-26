<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Private nomination report</title>
<style>
@page { margin: 34px 42px 48px; }
body { font: 11px "DejaVu Sans", sans-serif; line-height: 1.4; color: #17283b; }
h1 { font-size: 21px; line-height: 1.2; margin: 5px 0 9px; }
h2 { font-size: 17px; color: #17487c; margin: 14px 0 4px; }
h3 { font-size: 12px; color: #17487c; margin: 15px 0 5px; }
h2, h3 { page-break-after: avoid; }
p { margin: 4px 0; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
thead { display: table-header-group; }
th, td { padding: 4px 7px; text-align: left; vertical-align: top; border-bottom: 1px solid #d9e3eb; }
th { background: #edf3fa; color: #17487c; font-size: 9px; }
tr { page-break-inside: avoid; }
.division-next { page-break-before: always; }
.eyebrow { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #17487c; text-transform: uppercase; }
.muted { color: #526379; }
.note { background: #f2f5f8; border-left: 3px solid #17487c; padding: 7px 10px; margin: 8px 0; }
.overview { font-size: 9px; }
.summary { margin: 6px 0; color: #17487c; font-weight: bold; }
.candidate-head { background: #edf3fa; padding: 6px 8px; margin-top: 10px; page-break-inside: avoid; page-break-after: avoid; }
.candidate-head strong { font-size: 11px; }
.candidate-facts { margin: 0 0 4px; }
.candidate-facts td { font-size: 9px; padding: 4px 8px; }
.candidate-note { margin: 4px 8px; }
.badge { font-weight: bold; color: #914500; }
.match { margin: 2px 8px; font-size: 10px; page-break-inside: avoid; }
</style></head><body>
<div class="eyebrow">Cape Tennis / Private nomination review</div>
<h1><?php echo e($report['event_name']); ?></h1>
<p class="muted">Saved nominations and supporting evidence for selection review. This report does not publish the rosters.</p>
<table class="overview"><thead><tr><th>Prepared division</th><th>Selected</th><th>Reserves</th><th>Review required*</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $report['sections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php ($summary = app(\App\Services\CavaliersRosterReport::class)->sectionSummary($section)); ?>
<tr><td><?php echo e($section['division']); ?></td><td><?php echo e($summary['selected']); ?></td><td><?php echo e($summary['reserves']); ?></td><td><?php echo e($summary['review_required']); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table>
<p class="muted">*Explained candidates with an unrecorded selector reason or an explicit review-order flag. Performance flags alone do not confirm why a player was omitted.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report['unsaved_divisions']): ?><p><strong>Not prepared:</strong> <?php echo e(implode(', ', $report['unsaved_divisions'])); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $report['sections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php ($summary = app(\App\Services\CavaliersRosterReport::class)->sectionSummary($section)); ?>
<section class="<?php echo e($loop->first ? 'division-first' : 'division-next'); ?>">
<h2><?php echo e($section['division']); ?></h2>
<p class="summary"><?php echo e($summary['selected']); ?> selected / <?php echo e($summary['reserves']); ?> reserves / <?php echo e($summary['declined']); ?> recorded declines</p>
<p><?php echo e($summary['explained']); ?> candidates explained below; <?php echo e($summary['review_required']); ?> require selection review. Counted singles evidence is unavailable for <?php echo e($summary['missing_evidence']); ?> of these candidates.</p>
<div class="note">
<p><strong>Evidence scope:</strong> <?php echo e($section['preview']['source_division']); ?>. Regions: <?php echo e(implode(', ', $section['preview']['candidate_regions']) ?: 'None'); ?>.</p>
<p>Results excluded against: <?php echo e(implode(', ', $section['preview']['excluded_result_regions']) ?: 'None'); ?>.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section['preview']['missing_regions']): ?><p>No published team for: <?php echo e(implode(', ', $section['preview']['missing_regions'])); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section['preview']['platteland_notice']): ?><p><?php echo e($section['preview']['platteland_notice']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['selected' => 'Selected players', 'reserves' => 'Next five reserves']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<h3><?php echo e($title); ?></h3>
<table><thead><tr><th style="width:6%">#</th><th style="width:47%">Player</th><th>Region / source</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = collect($section['rows'])->where('status', $status); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><?php echo e($row['position']); ?></td><td><?php echo e($row['name']); ?></td><td><?php echo e($row['evidence']['region'] ?? '-'); ?> / <?php echo e($row['evidence']['source'] ?? 'No source evidence'); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="3">None recorded.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="note muted">Review order follows the source band/results order, then the finalized Platteland additions. Selection credit is separate from played wins. Scores retain the source fixture's home-away order. Playing numbers come from match evidence; a roster place does not prove a match was played.</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['next_candidates' => 'Next five outside the nomination list', 'inter_districts' => 'Inter-Districts players outside the selected list']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<h3><?php echo e($heading); ?></h3>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($group === 'inter_districts'): ?><p class="muted">Additional eligible Inter-Districts candidates, including reserves and declines. Candidates explained above are not repeated.</p>
<?php else: ?><p class="muted">Next available candidates in review order, excluding selected players, saved reserves and declines.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $section['explanations'][$group]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php ($evidence = $row['evidence']); ?>
<div class="candidate-head"><strong><?php echo e($loop->iteration); ?>. <?php echo e($row['name']); ?></strong><br><span class="muted"><?php echo e($evidence['region'] ?? 'Region unavailable'); ?> / <?php echo e($evidence['source'] ?? 'No candidate source'); ?> / Review order #<?php echo e($row['source_order']); ?></span></div>
<table class="candidate-facts"><thead><tr><th>Saved status</th><th>Counted wins / losses</th><th>Playing No.</th><th>Selection credit / band</th></tr></thead><tbody><tr>
<td><?php echo e($row['selection_status']); ?></td>
<td><?php echo e(isset($evidence['wins'], $evidence['losses']) ? $evidence['wins'].' / '.$evidence['losses'] : 'Unavailable'); ?></td>
<td><?php echo e(($row['playing_numbers'] ?? []) ? implode(', ', $row['playing_numbers']) : 'Not recorded'); ?></td>
<td><?php echo e($evidence['starting_credit'] ?? 'Not recorded'); ?> / <?php echo e($evidence['band'] ?? 'Not recorded'); ?></td>
</tr></tbody></table>
<p class="candidate-note"><span class="badge"><?php echo e($row['badge']); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['review_note']): ?> - <?php echo e($row['review_note']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!isset($evidence['wins'], $evidence['losses'])): ?><p class="candidate-note muted">No counted singles evidence.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $evidence['matches'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="match"><?php echo e($match['outcome']); ?>: <?php echo e($match['opponent']); ?><?php echo e($match['score'] ? ' ('.$match['score'].')' : ''); ?><?php echo e(isset($match['playing_number']) ? ' · No. '.$match['playing_number'] : ''); ?></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="muted">No further available candidates.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\nominations\cavaliers-report-pdf.blade.php ENDPATH**/ ?>