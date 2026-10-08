<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>



<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

<div class="container-full">
    <!-- Content Header (Page header) -->

    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="mr-auto">


            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">

        <div class="row">



            <div class="col-12">

                <div class="box">
                    <div class="box-header with-border">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
                        <div class="col-sm-12">
                            <div class="alert  alert-success alert-dismissible fade show" role="alert">
                                <?php echo e(session('success')); ?>

                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>

                            </div>

                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <a href="#" class="btn btn-primary">Change team Players</a>

                        <br>

                        <h3 class="box-title"><?php echo e($team->name); ?> <?php echo e($team->id); ?></h3>

                        <br><br>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all Players in event</a>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all unpaid Players in event</a>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to Players in <?php echo e($team->name); ?></a>


                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all Players in <?php echo e($team->regions['region_name']); ?></a>

                    </div>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width='5%'>Nr</th>
                                        <th width='15%'>Name and Surname</th>
                                        <th width='15%'>email</th>
                                        <th width='15%'>Cell nr</th>
                                        <th width='15%'>Pay Status</th>
                                        <td></td>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $playerDetails): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <tr>
                                        <td><?php echo e($key+1); ?></td>
                                        <td><?php echo e($playerDetails->name); ?> <?php echo e($playerDetails->surname); ?></td>
                                        <td><?php echo e($playerDetails->email); ?></td>
                                        <td><?php echo e($playerDetails->cellNr); ?></td>
                                        <td style="<?php echo e($playerDetails->pivot->pay_status == 1 ? 'background-color:green':''); ?>">
                                            <?php echo e($playerDetails->pivot->pay_status == 1 ? 'Paid':'Not-paid'); ?>

                                            <span data-id="<?php echo e($playerDetails->id); ?>" data-teamId="<?php echo e($team->id); ?>" class="ml-1 btn btn-secondary btn-sm  markPaid">Change Pay Status </span>

                                        </td>
                                        <td><a class="btn btn-warning" href="#">Email Player</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                    </div>
                    <!-- /.box-body -->
                </div>
                <!-- /.box -->


            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->

    </section>
    <!-- /.content -->

</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\_includes\team_type.blade.php ENDPATH**/ ?>