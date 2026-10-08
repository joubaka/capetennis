<div class="col-xl-12 ">
    <h3>Team Event: <?php echo e($event->name); ?></h3>

    <div class="col-xl-12">

        <div class="nav-align-top nav-tabs-shadow mb-4">
            <ul class="nav nav-tabs nav-fill" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-home" aria-controls="navs-justified-home" aria-selected="true"><i class="tf-icons ti ti-home ti-xs me-1"></i> Regions <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-danger ms-1"><?php echo e($event->regions->count()); ?></span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-profile" aria-controls="navs-justified-profile" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-user ti-xs me-1"></i> Teams in Event </span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-categories" aria-controls="navs-justified-profile" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-user ti-xs me-1"></i> Categories </span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-messages" aria-controls="navs-justified-messages" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Players</button>
                </li>

                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-order" aria-controls="navs-justified-messages" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Player order</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="result-rank-button" type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-resultRank" aria-controls="navs-justified-resultRank" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Result Ranks</button>
                </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === 584 ): ?>
                <li class="nav-item" role="presentation">
                    <a href="<?php echo e(route('headOffice.show',$event->id)); ?>" type="button" class="nav-link"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i>Dashboard </a>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade " id="navs-justified-home" role="tabpanel">
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->regions->count() == 0): ?>
                            <div class="alert alert-primary noRegions" role="alert">
                                No Regions added to event
                            </div>

                            <?php else: ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="list-group-item d-flex align-items-center">

                                <?php echo e($region->region_name); ?>

                                <a href="javascript:void(0)" class="ms-2 removeRegionEvent" data-id="<?php echo e($region->pivot->id); ?>">
                                    <i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i>Delete Region
                                </a>
                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>

                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-target="#modalToggle" data-bs-toggle="modal">
                            <span class="ti-xs ti ti-star me-1"></span>Add Region to event
                        </button>
                    </div>
                </div>
                <div class="tab-pane fade" id="navs-justified-profile" role="tabpanel">
                    <div class="col-8"></div>
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->regions->count() == 0): ?>
                            <div class="alert alert-primary noRegions" role="alert">
                                No Regions added to event
                            </div>

                            <?php else: ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="list-group-item  ">

                                <span class="badge bg-label-info m-2"><?php echo e($region->region_name); ?> Teams</span>

                                <div>
                                    <ul class="list-group team-list">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($region->teams->count() == 0): ?>
                                        <div class=" m-2 alert alert-primary" role="alert">
                                            No teams add to region!
                                        </div>
                                        <?php else: ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li class="list-group-item"><?php echo e($team->name); ?>

                                            <a href="javascript:void(0)" class="ms-2 publishTeam" data-state="<?php echo e($team->published == 0 ? '0':'1'); ?>" data-id="<?php echo e($team->id); ?>">
                                                <?php echo $team->published == 0 ? '<span class="badge bg-label-success">Publish Team<span>':'<span class="badge bg-label-danger">Unpublish Team</span>'; ?>

                                            </a>


                                            <a href="javascript:void(0)" class="ms-2 removeTeam" data-id="<?php echo e($team->id); ?>">
                                                <i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i>Delete team
                                            </a>

                                            <p><span class="category-<?php echo e($team->id); ?>"> Category: <?php echo e($team->category ? $team->category->category->name:'None'); ?></span><br>
                                                <button data-id="<?php echo e($team); ?>" class="btn btn-sm bg-label-info edit-team-category" data-bs-toggle="modal" data-bs-target="#edit-team-category-modal">Edit Category</button>
                                            </p>

                                        </li>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </ul>
                                </div>
                                <a data-regionid="<?php echo e($region->id); ?>" data-bs-target="#addTeamModal" data-bs-toggle="modal" href="javascript:void(0)" class="m-2 btn btn-primary btn-xs addTeam" data-id="<?php echo e($region->id); ?>">
                                    <i class="ti ti-plus ti-sm me-2 bg-label-success rounded-pill"></i> Add Team
                                </a>
                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>

                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-target="#modalToggle" data-bs-toggle="modal">
                            <span class="ti-xs ti ti-star me-1"></span>Add Region to event
                        </button>
                    </div>


                </div>
                <div class="tab-pane fade" id="navs-justified-categories" role="tabpanel">
                    <div class="col-8">
                        <button class="btn btn-primary-small" id="add-category-button" data-bs-toggle="modal" data-bs-target="#add-category-modal">Add Category</button>
                    </div>
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->eventCategories->count() == 0): ?>
                            <div class="alert alert-primary noRegions" role="alert">
                                No Categories added to event
                            </div>

                            <?php else: ?>
                            <ul class="list-group">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="list-group-item"> <?php echo e($category->category->name); ?> <?php echo e($category->id); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </ul>





                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>


                    </div>


                </div>
                <div class="tab-pane fade" id="navs-justified-messages" role="tabpanel">
                    <div class="nav-align-top nav-tabs-shadow mb-4">

                        <ul class="nav nav-tabs" role="tablist">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link <?php echo e($key == 0 ? 'active':''); ?>" role="tab" data-bs-toggle="tab" data-bs-target="#teamOrder<?php echo e($region->id); ?>" aria-controls="<?php echo e($region->id); ?>" aria-selected="<?php echo e($key == 0 ? 'true':''); ?>"><?php echo e($region->region_name); ?></button>
                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



                        </ul>
                        <div class="tab-content">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=> $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="tab-pane fade <?php echo e($key == 0 ? 'active show':''); ?>" id="teamOrder<?php echo e($region->id); ?>" role="tabpanel">
                                <div class="card-body">

                                    <div class="col-md-12">
                                        <div class=" d-flex justify-content-between pb-2 mb-1">

                                            <div class="dropdown">
                                          
                                                <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                                </button>
                                               
                                                <a class="btn btn-success" href="<?php echo e(route('team.import.view')); ?>">Import Player Sheet</a>
                                                
                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('region','<?php echo e($region->id); ?>')">Send e-mail to all players in region</a>
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('unregistered_event','<?php echo e($region->id); ?>')">Send e-mail to all UNREGISTERED players in region</a>
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('event','<?php echo e($event->id); ?>')">Send e-mail to all players in event</a>
                                                </div>


                                                <a href="<?php echo e(route('region.clothing.order',$region->id)); ?>" class="btn btn-info">Clothing Orders</a>
                                            </div>
                                        </div>


                                        <div class="row">

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$team->noProfile == 1): ?>
                                                <?php echo $__env->make('backend.adminPage.partials.team-profile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                            <?php else: ?>

                                            <?php echo $__env->make('backend.adminPage.partials.team-no-profile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                         
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>





                                        </div>
                                    </div>


                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="navs-justified-order" role="tabpanel">
                    <div class="nav-align-top nav-tabs-shadow mb-4">
                        <ul class="nav nav-tabs" role="tablist">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link <?php echo e($key == 0 ? 'active':''); ?>" role="tab" data-bs-toggle="tab" data-bs-target="#team<?php echo e($region->id); ?>" aria-controls="<?php echo e($region->id); ?>" aria-selected="<?php echo e($key == 0 ? 'true':''); ?>"><?php echo e($region->region_name); ?></button>
                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



                        </ul>
                        <div class="tab-content">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=> $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="tab-pane fade <?php echo e($key == 0 ? 'active show':''); ?>" id="team<?php echo e($region->id); ?>" role="tabpanel">
                                <div class="card-body">

                                    <div class="col-md-12">
                                        <div class=" d-flex justify-content-between pb-2 mb-1">

                                            <div class="dropdown">
                                                <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('region','<?php echo e($region->id); ?>')">Send e-mail to all players in region</a>


                                                </div>
                                            </div>
                                        </div>


                                        <div class="row">

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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

                                                            <tbody class="sortablePlayers">
                                                                <?php

                                                                $members = $team->players ;


                                                                ?>


                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <tr class="row-<?php echo e($member->pivot->id); ?> drag-item" data-playerteamid="<?php echo e($member->pivot->id); ?>">
                                                                    <td><span class="badge bg-label-primary"><?php echo e($i+1); ?></span></td>
                                                                    <td class="name"> <?php echo e($member->id == 1248 ? '':$member->name); ?> <?php echo e($member->id == 1248 ? 'No Player':$member->surname); ?></td>
                                                                    <td class="email"> <?php echo e($member->id == 1248 ?  '':$member->email); ?></td>
                                                                    <td class="cellNr"> <?php echo e($member->id == 1248 ?  '':$member->cellNr); ?></td>
                                                                    <td><?php echo $member->pivot->pay_status == 1 ? '<span class="badge bg-label-success">Paid</span>':'<span class="badge bg-label-danger">Not Paid</span>'; ?></td>
                                                                    <td>
                                                                        <div class="dropdown listDropdown">
                                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                                                            <div class="dropdown-menu">

                                                                                <a class="dropdown-item insertPlayer" href="javascript:void(0);" data-pivot="<?php echo e($member->pivot->id); ?>" data-position="<?php echo e(($i+1)); ?>" data-teamid="<?php echo e($team->id); ?>" data-bs-target="#insert-player-team-modal" data-bs-toggle="modal"><i class="ti ti-insert me-1"></i> Replace Player</a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>




                                                            </tbody>







                                                        </table>
                                                    </div>



                                                </div>

                                            </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>





                                        </div>
                                    </div>


                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </div>
                    </div>
                </div>

                <div class="tab-pane fade show active" id="navs-justified-resultRank" role="tabpanel">
               
                        <div class="col-2 p-6">
                            <div class="text-light small fw-medium mb-4">Default</div>
                            <div class="switches-stacked">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="switch">
                                    <input type="radio" class="switch-input" name="switches-stacked-radio" data-name='<?php echo e($category->category->name); ?>' data-id ='<?php echo e($category->id); ?>' data-event_id='<?php echo e($event->id); ?>' checked="">
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                    <span class="switch-label"><?php echo e($category->category->name); ?></span>
                                </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            </div>
                        </div>
                        <div class="col-12 p-1">
                            <div class="card" id="rank-table">
                                <div class="card-header"><h4 id="category-name"></h4></div>
                                <div class="card-body" id="category-table">
                                   
                                </div>
                               


                            </div>

                        </div>

                    

                </div>
            </div>



        </div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\team_showtest.blade.php ENDPATH**/ ?>