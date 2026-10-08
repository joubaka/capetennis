<?php

use App\Helpers\Fixtures;

?>
<?php
    $firstFix = $fixtures->first();
?>
<?php
    $ids = $fixtures->pluck('id')->toArray();
?>
<h3><a class="btn btn-danger btn-sm ms-2" href="<?php echo e(url()->previous()); ?>">Back</a>
    <a class="btn btn-primary btn-sm" href="<?php echo e(route('fixture.create.pdf.venue',['fixtures' => $ids])); ?>">Create PDF</a>

</h3>
<div class="table-responsive">

    <table class="table" id="schedule">
        <thead class="table-dark">
            <tr>
                <th width="2%">Match #</th>
                <th width="2%">Rank #</th>
                <th>Fix #</th>
                <th width="5%">Team</th>
                <th>vs</th>
                <th width="5%">Team</th>

                <th>Not Before</th>
                <th>Venue</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody class="table-border-bottom-0">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->rank_nr == 1): ?>
                    <tr class="m-4">

                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                        <td style="display: none;"></td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <tr id='<?php echo e($fixture->id); ?>'>
                <td> <?php echo e($key+1); ?> </td>
                <td><?php echo e($fixture->rank_nr); ?></td>
                <td><?php echo e($fixture->id); ?></td>
<!-- check if team has players -->

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->team1->count() == 1): ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1): ?>

                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p1"> <?php echo e(Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)); ?>

                                                            (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                    </td>

                                                    <td>vs</td>
                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p2 "> <?php echo e(Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)); ?>

                                                            (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                    </td>

                                        <?php elseif($fixture->region2Name->no_profile == 1): ?>

                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p1"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p2"> <?php echo e(Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)); ?>

                                                            (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                    </td>
                                        <?php elseif($fixture->region1Name->no_profile == 1): ?>

                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p1"> <?php echo e(Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)); ?>

                                                            (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p2"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                    </td>


                                        <?php else: ?>

                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p1"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p2"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                    </td>





                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php else: ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixture_type == 2): ?>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1): ?>

                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)); ?>

                                                                (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                        </td>

                                                        <td>vs</td>
                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)); ?>

                                                                (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                        </td>

                                                <?php elseif($fixture->region2Name->no_profile == 1): ?>
                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                                                (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileTeam($fixture,2,$fixture->rank_nr)); ?>

                                                                (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                        </td>
                                                <?php elseif($fixture->region1Name->no_profile == 1): ?>

                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileTeam($fixture,1,$fixture->rank_nr)); ?>

                                                                (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team2[1]->getFullNameAttribute()); ?>

                                                                (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                        </td>
                                                        <?php else: ?>


                                                         <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary">
                                                            <?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                                            </span>
                                                        </td>
                                                        <td>vs</td>
                                                        <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                            <span class="badge bg-label-primary">
                                                            <?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team2[1]->getFullNameAttribute()); ?>


                                                            </span>
                                                        </td>


                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                            <?php else: ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->region1Name->no_profile == 1 && $fixture->region2Name->no_profile == 1): ?>
                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileMixedTeam($fixture,1,$fixture->rank_nr)); ?>

                                                        (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                </td>

                                                <td>vs</td>
                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileMixedTeam($fixture,2,$fixture->rank_nr)); ?>

                                                        (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                </td>

                                            <?php elseif($fixture->region2Name->no_profile == 1): ?>
                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                                        (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                </td>
                                                <td>vs</td>
                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileMixedTeam($fixture,2,$fixture->rank_nr)); ?>

                                                        (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                </td>
                                            <?php elseif($fixture->region1Name->no_profile == 1): ?>

                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"> <?php echo e(Fixtures::getNoProfileMixedTeam($fixture,1,$fixture->rank_nr)); ?>

                                                        (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                </td>
                                                <td>vs</td>
                                                <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                    <span class="badge bg-label-primary"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                                        (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                </td>
                                            <?php else: ?>

                                            <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p1"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                                                    </td>
                                                    <td>vs</td>
                                                    <td class="<?php echo e(Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success':''); ?>">
                                                        <span class="badge bg-label-primary p2"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team2[1]->getFullNameAttribute()); ?>

                                                            (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                                                    </td>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>




                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <td><span class="time <?php echo e($fixture->schedule ? 'badge bg-label-warning':'badge bg-label-danger'); ?>"><?php echo e($fixture->schedule ? date('D d M @ H:i', strtotime($fixture->schedule->time)):''); ?></span> </td>
                    <td><span class="venue <?php echo e($fixture->schedule ? 'badge bg-label-secondary':'badge bg-label-danger'); ?>"><?php echo e($fixture->schedule ? $fixture->schedule->venue->name:''); ?></span></td>
                    <td class="resultTd">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->teamResults->count() > 0): ?>
                        <span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixture->teamResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php echo e($result->team1_score); ?> - <?php echo e($result->team2_score); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key < (count($fixture->teamResults)-1) ): ?>
                                        ,
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </span>
                    <?php else: ?>

                    <button type="button" data-id="<?php echo e($fixture); ?>" data-reg1="<?php echo e($fixture->team1->count() > 1 ? $fixture->team1[0]->getFullNameAttribute().'/'.$fixture->team1[1]->getFullNameAttribute():$fixture->team1[0]->getFullNameAttribute()); ?> " data-reg2="<?php echo e($fixture->team2->count() > 1 ? $fixture->team2[0]->getFullNameAttribute().'/'.$fixture->team2[1]->getFullNameAttribute():$fixture->team2[0]->getFullNameAttribute()); ?> " class="btn btn-sm btn-secondary insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal">Insert Score</button><!-- Modal Trigger -->


                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>




                </td>
                <td>
                    <div class="dropdown">
                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                        <div class="dropdown-menu">

                            <form action="<?php echo e(route('draw.delete.result',$fixture->id)); ?>" method="post">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="dropdown-item" href="javascript:void(0);"><i class="ti ti-trash me-1"></i>Delete</button>

                            </form>

                            <button data-bs-toggle="modal" data-bs-target="#change-schedule-modal" type="button" data-id="<?php echo e($fixture); ?>" class="dropdown-item change-schedule" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Change Schedule</button>

                            <button data-bs-toggle="modal" data-bs-target="#change-player-modal" type="button" data-id="<?php echo e($fixture); ?>" class="dropdown-item change-players" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Change Players</button>
                        </div>
                    </div>
                </td>
            </tr>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="change-player-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Change Players</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form action="<?php echo e(route('fixture.update.players')); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="defaultSelect" class="form-label">Player 1</label>
                        <select name="player1" id="player1" class="form-select">
                            <option>Default select</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($player->id); ?>"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </select>
                    </div>
                    <div>vs</div>
                    <div class="mb-3">
                        <label for="defaultSelect" class="form-label">Player 2</label>
                        <select name="player2" id="player2" class="form-select">
                            <option>Default select</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($player->id); ?>"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>
                    <input type="hidden" name="fixture" id="fixutureValue">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary save-fixture-names">Save changes</button>
                    </div>
                </form>

            </div>

        </div>
    </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\fixture\fixturesPerVenue.blade.php ENDPATH**/ ?>