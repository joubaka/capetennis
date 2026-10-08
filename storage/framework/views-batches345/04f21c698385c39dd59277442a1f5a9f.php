<?php

use App\Helpers\Fixtures;



?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h3 class="mb-1"><?php echo e($draw->drawName); ?> <?php echo e($draw->age); ?></h3>
        <span class="badge bg-label-success">Draw published</span>
        <span class="badge <?php echo e($draw->scheduleIsPublished() ? 'bg-label-success' : 'bg-label-secondary'); ?>">
            <?php echo e($draw->scheduleIsPublished() ? 'Match times published' : 'Match times to follow'); ?>

        </span>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('events.show', $event)); ?>">
        <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to tournament
    </a>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($draw->scheduleIsPublished())): ?>
    <div class="alert alert-info" role="status">
        The draw is available, but match times and venues have not been published yet.
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="table-responsive">
    <table class="table" id="schedule">
        <thead class="table-dark">
            <tr>
                <th width="2%">#</th>
                <th width="5%">Team</th>
                <th>vs</th>
                <th width="5%">Team</th>
                <th>Score</th>
                <th>Not Before</th>
                <th>Venue</th>

            </tr>
        </thead>
        <tbody class="table-border-bottom-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>


            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->rank_nr == 1): ?>
            <tr class="m-4">
                <td colspan="8"><span class=" ">
                        <h4><?php echo e($fixture->region1Name->region_name); ?> vs <?php echo e($fixture->region2Name->region_name); ?>

                            <?php echo e($fixture->draw_type->drawTypeName); ?>

                        </h4>
                    </span></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
                <td style="display: none;"></td>
            </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <tr>
              <td><?php echo e($fixture->rank_nr); ?> </td>

              <?php
                  $winner1 = Fixtures::getWinner($fixture->id) == 1 ? 'bg-label-success border border-2 border-success' : '';
                  $winner2 = Fixtures::getWinner($fixture->id) == 2 ? 'bg-label-success border border-2 border-success' : '';
                  $profile1 = $fixture->region1Name->no_profile == 1;
                  $profile2 = $fixture->region2Name->no_profile == 1;
              ?>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->team1->count() == 1): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile1 && $profile2): ?>
                      <!-- No profile for both teams -->
                      <td class="<?php echo e($winner1); ?>">
                          <span class="badge bg-label-primary p1"><?php echo e(Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr)); ?>

                              (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                      </td>
                      <td>vs</td>
                      <td class="<?php echo e($winner2); ?>">
                          <span class="badge bg-label-primary p2"><?php echo e(Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr)); ?>

                              (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                      </td>
                  <?php elseif($profile2): ?>
                      <!-- Region 1 has profile, Region 2 does not -->
                      <td class="<?php echo e($winner1); ?>">
                          <span class="badge bg-label-primary p1"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>

                              (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                      </td>
                      <td>vs</td>
                      <td class="<?php echo e($winner2); ?>">
                          <span class="badge bg-label-primary p2"><?php echo e(Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr)); ?>

                              (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                      </td>
                  <?php elseif($profile1): ?>
                      <!-- Region 2 has profile, Region 1 does not -->
                      <td class="<?php echo e($winner1); ?>">
                          <span class="badge bg-label-primary p1"><?php echo e(Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr)); ?>

                              (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                      </td>
                      <td>vs</td>
                      <td class="<?php echo e($winner2); ?>">
                          <span class="badge bg-label-primary p2"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>

                              (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                      </td>
                  <?php else: ?>
                      <!-- Both teams have profiles -->
                      <td class="<?php echo e($winner1); ?>">
                          <span class="badge bg-label-primary p1"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>

                              (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                      </td>
                      <td>vs</td>
                      <td class="<?php echo e($winner2); ?>">
                          <span class="badge bg-label-primary p2"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>

                              (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                      </td>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php else: ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixture_type == 2): ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile1 && $profile2): ?>
                          <!-- No profile for both teams -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php elseif($profile2): ?>
                          <!-- Region 1 has profile, Region 2 does not -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php elseif($profile1): ?>
                          <!-- Region 2 has profile, Region 1 does not -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team2[1]->getFullNameAttribute()); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php else: ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile1 && $profile2): ?>
                          <!-- No profile for both teams -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileMixedTeam($fixture, 1, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileMixedTeam($fixture, 2, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php elseif($profile2): ?>
                          <!-- Region 1 has profile, Region 2 does not -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e($fixture->team1[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team1[1]->getFullNameAttribute()); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileMixedTeam($fixture, 2, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php elseif($profile1): ?>
                          <!-- Region 2 has profile, Region 1 does not -->
                          <td class="<?php echo e($winner1); ?>">
                              <span class="badge bg-label-primary"><?php echo e(Fixtures::getNoProfileMixedTeam($fixture, 1, $fixture->rank_nr)); ?>

                                  (<?php echo e($fixture->region1Name->short_name); ?>)</span>
                          </td>
                          <td>vs</td>
                          <td class="<?php echo e($winner2); ?>">
                              <span class="badge bg-label-primary"><?php echo e($fixture->team2[0]->getFullNameAttribute()); ?>/<?php echo e($fixture->team2[1]->getFullNameAttribute()); ?>

                                  (<?php echo e($fixture->region2Name->short_name); ?>)</span>
                          </td>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <!-- Team Results and Match Time -->
              <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->teamResults->count() > 0): ?>
                      <span>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixture->teamResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <?php echo e($result->team1_score); ?> - <?php echo e($result->team2_score); ?>

                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->draw->scheduleIsPublished()): ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->schedule): ?>
                          <span class="badge bg-label-warning"><?php echo e(date('D d M @ H:i', strtotime($fixture->schedule->time))); ?></span>
                      <?php else: ?>
                          <span class="badge bg-label-danger"></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php else: ?>
                      <span class="badge bg-label-danger"></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->draw->scheduleIsPublished()): ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->schedule): ?>
                          <span class="badge bg-label-warning"><?php echo e($fixture->schedule->venue->name); ?></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php else: ?>
                      <span class="badge bg-label-danger"></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
          </tr>


            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\fixture-table-no-profile.blade.php ENDPATH**/ ?>