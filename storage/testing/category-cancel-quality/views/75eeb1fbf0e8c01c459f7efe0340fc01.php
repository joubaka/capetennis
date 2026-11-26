<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($event->name); ?> · <?php echo e($categoryEvent->category->name); ?></title>
    <style>
        body { font: 10px "DejaVu Sans", sans-serif; color: #17283b; }
        h1 { font-size: 19px; margin-bottom: 3px; }
        h2 { font-size: 13px; color: #155e59; margin: 18px 0 5px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        th, td { border-bottom: 1px solid #d9e3eb; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #eaf3f2; }
        tr { page-break-inside: avoid; }
        .lost { color: #a5252b; }
        .winless { color: #914500; }
    </style>
</head>
<body>
    <h1><?php echo e($event->name); ?></h1>
    <p><strong><?php echo e($categoryEvent->category->name); ?> · Private selection review</strong></p>
    <p>Saved roster. Inter-Districts source division: <?php echo e($preview['source_division']); ?>.</p>
    <p>Candidate regions: <?php echo e(implode(', ', $preview['candidate_regions']) ?: 'None'); ?>.
        Results excluded: <?php echo e(implode(', ', $preview['excluded_result_regions']) ?: 'None'); ?>.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['missing_regions']): ?>
        <p>No published team for: <?php echo e(implode(', ', $preview['missing_regions'])); ?>.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['platteland_notice']): ?>
        <p><?php echo e($preview['platteland_notice']); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['selected' => 'Selected', 'reserves' => 'Reserves', 'declined' => 'Declined']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <h2><?php echo e($label); ?> (<?php echo e(count($roster[$key])); ?>)</h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roster[$key]): ?>
            <table>
                <thead><tr><th>#</th><th>Player</th><th>Region and source</th></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roster[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $candidate = $evidence->get($player['id']);
                        ?>
                        <tr>
                            <td><?php echo e($loop->iteration); ?></td><td><?php echo e($player['name']); ?></td>
                            <td><?php echo e(implode(' · ', array_filter([$candidate['region'] ?? null, $candidate['source'] ?? null])) ?: 'No candidate source in this review'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>None recorded.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <h2>Inter-Districts candidates left out (<?php echo e($leftOut->count()); ?>)</h2>
    <p>Players outside Selected, Reserves and Declined. Results reflect counted singles under the current setup.
        The draft records roster choices. It does not record an individual reason for leaving a player out.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($leftOut->isNotEmpty()): ?>
        <table>
            <thead><tr><th>Player</th><th>Region / band</th><th>Singles</th><th>Selection evidence</th></tr></thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $leftOut; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($candidate['name']); ?></td>
                        <td><?php echo e($candidate['region'] ?: '—'); ?> · <?php echo e($candidate['band']); ?></td>
                        <td><?php echo e($candidate['wins'] === null ? '—' : $candidate['wins'].'–'.$candidate['losses']); ?></td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($candidate['lost_all']): ?>
                                <span class="lost">Lost all counted singles.</span>
                            <?php elseif($candidate['only_beat_winless']): ?>
                                <span class="winless">Only beat opponents who lost all counted singles.</span>
                            <?php elseif($candidate['wins'] === null): ?>
                                No counted singles results available.
                            <?php else: ?>
                                No exclusion reason recorded.
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No Inter-Districts candidates outside the saved roster for this setup.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body>
</html>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\nominations\cavaliers-roster-pdf.blade.php ENDPATH**/ ?>