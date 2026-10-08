    <?php
        $bracketColors = [
            1 => 'table-primary',
            2 => 'table-success',
            3 => 'table-warning',
            4 => 'table-danger',
            5 => 'table-info',
            6 => 'table-secondary',
            7 => 'table-light',
            8 => 'table-dark',
        ];
    ?>

    <div class="text-center mb-4">

        <div id="roundrobin-matrix">




            <?php echo $__env->make('backend.draw.partials.roundrobin-matrix', ['draw' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>



        </div>
    </div>


    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawFixtures->isNotEmpty()): ?>
        <div class="fixtures-preview-area">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Id</th>
                            <th>Match #</th>
                            <th>Round</th>
                            <th>Group</th>
                            <th>Player 1</th>
                            <th>Player 2</th>
                            <th>Status</th>
                            <th>Result</th>

                        </tr>
                    </thead>

                    <tbody>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->drawFixtures->where('stage', 'RR')->sortBy(['round', 'match_nr']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>


                            <?php
                                $player1 =
                                    optional(optional($match->registration1)->players)->first()?->full_name ??
                                    ($match->hint_registration1_id ?? 'TBD');
                                $player2 =
                                    optional(optional($match->registration2)->players)->first()?->full_name ??
                                    ($match->hint_registration2_id ?? 'TBD');
                                $bracket = $match->bracket?->name ?? ($match->bracket_id ?? '-');

                                // Get CSS class for bracket ID
                                $rowClass = $bracketColors[$match->draw_group_id] ?? '';
                            ?>

                            <tr class="<?php echo e($rowClass); ?>"     >
                                <td><?php echo e($match->id); ?></td>
                                <td><?php echo e($match->match_nr ?? '-'); ?></td>
                                <td><?php echo e($match->round ?? '-'); ?></td>
                                <td><?php echo e($match->draw_group_id); ?></td>

                                <td><?php echo e($player1); ?></td>
                                <td><?php echo e($player2); ?></td>


                                <td><?php echo e($match->match_status == 2 ? 'finished' : 'pending'); ?></td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($match->fixtureResults->isNotEmpty()): ?>
                                        <?php
                                            $resultText = $match->fixtureResults
                                                ->map(function ($set) use ($match) {
                                                    return $match->registration1_id === $set->winner_registration
                                                        ? "{$set->registration1_score}-{$set->registration2_score}"
                                                        : "{$set->registration2_score}-{$set->registration1_score}";
                                                })
                                                ->implode(', ');
                                        ?>
                                        <?php echo e($resultText); ?>

                                    <?php else: ?>
                                        <span class="text-muted">

                                            <button class="btn btn-sm btn-outline-primary set-result-btn"
                                                data-bs-toggle="modal" data-bs-target="#tennisResultModal"
                                                data-fixture-id="<?php echo e($match->id); ?>"
                                                data-player1="<?php echo e($player1); ?>" data-player2="<?php echo e($player2); ?>">
                                                Insert Result
                                            </button>

                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>



                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted">No fixtures available yet.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\draw-preview.blade.php ENDPATH**/ ?>