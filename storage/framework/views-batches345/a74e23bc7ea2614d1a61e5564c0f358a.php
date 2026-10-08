<div class="col-12">
                                                <div class="card-header mb-0">
                                                    <h5 class="m-0 me-2 m-4"> <?php echo e($team->name); ?></h5><span>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i>Options</button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="team" onclick="changeRecipants('team','<?php echo e($team->id); ?>')">Send e-mail to all players in <?php echo e($team->name); ?></a>

                                                            </div>
                                                        </div>
                                                    </span>


                                                </div>

                                                <div class="card-body">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$team->published == 1): ?>

                                                    <div class="mt-4 alert alert-danger" role="alert">
                                                        Team not yet published!
                                                    </div>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <div class="table-responsive">
                                                        <table class="table">
                                                            <thead>
                                                                <th>Nr</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Cell</th>
                                                                <th>Pay Status</th>
                                                                <th>Actions</th>
                                                            </thead>

                                                            <tbody class="">
                                                                <?php

                                                                $members = $team->players ;


                                                                ?>


                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <tr class="row-<?php echo e($member->pivot->id); ?> drag-item" data-playerteamid="<?php echo e($member->pivot->id); ?>">
                                                                    <td><span class="badge bg-label-primary"><?php echo e($i+1); ?></span></td>
                                                                    <td class="name"> <?php echo e($member->id == 1248 ? '':$member->name); ?> <?php echo e($member->id == 1248 ? 'No Player':$member->surname); ?></td>
                                                                    <td class="email"> <?php echo e($member->id == 1248 ?  '':$member->email); ?></td>
                                                                    <td class="cellNr"> <?php echo e($member->id == 1248 ?  '':$member->cellNr); ?></td>
                                                                    <td class=""><?php echo $member->pivot->pay_status == 1 ? '<span class="payStatus badge bg-label-success">Paid</span>':'<span class="payStatus badge bg-label-danger">Not Paid</span>'; ?></td>
                                                                    <td>
                                                                        <div class="dropdown listDropdown">
                                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                                                            <div class="dropdown-menu">
                                                                                <a class="dropdown-item insertPlayer" href="javascript:void(0);" data-pivot="<?php echo e($member->pivot->id); ?>" data-position="<?php echo e(($i+1)); ?>" data-teamid="<?php echo e($team->id); ?>" data-bs-target="#insert-player-team-modal" data-bs-toggle="modal"><i class="ti ti-insert me-1"></i> Replace Player</a>
                                                                                <a class="dropdown-item changePayStatus" href="javascript:void(0);" data-pivot="<?php echo e($member->pivot->id); ?>" data-position="<?php echo e(($i+1)); ?>" data-teamid="<?php echo e($team->id); ?>"><i class="ti ti-insert me-1"></i> Change Pay status</a>
                                                                                <a class="dropdown-item refundToWallet" href="javascript:void(0);" data-pivot="<?php echo e($member->pivot->id); ?>" data-position="<?php echo e(($i+1)); ?>" data-teamid="<?php echo e($team->id); ?>"><i class="ti ti-pay me-1"></i> Refund to Wallet</a>

                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>




                                                            </tbody>







                                                        </table>

                                                    </div>


                                                </div>

                                            </div><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\partials\team-profiletest.blade.php ENDPATH**/ ?>