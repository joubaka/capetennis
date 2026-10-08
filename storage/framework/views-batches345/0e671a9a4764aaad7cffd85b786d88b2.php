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


                                             <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\fixture\partials\fixture2.blade.php ENDPATH**/ ?>