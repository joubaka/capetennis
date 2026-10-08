
<div class="row">
    <div class="col-12">
     
        <div class="card">
            <div class="row">

            
                <div class="col-12 col-md-12">
                    <div class="list-group m-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <?php echo $__env->make('backend.draw._includes.draw_tab', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                </div>
            </div>


        </div>
    </div>

</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\admin_tournament_show.blade.php ENDPATH**/ ?>