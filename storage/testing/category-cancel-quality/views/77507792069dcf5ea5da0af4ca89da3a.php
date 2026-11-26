<style>
.file-item:hover {
  background-color: #f8f9fa;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.card .btn-outline-primary:hover {
  background-color: #0d6efd;
  color: #fff;
}
</style>

<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

   
      
      <?php echo $__env->make('frontend.event.partials.interpro-draws-mobile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->make('frontend.event.partials.event-region-directory', ['regions' => $regions, 'directoryAlert' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>


    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

   

      <?php echo $__env->make('frontend.event.partials.event-documents', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->make('frontend.event.partials._venue-scoring', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->make('frontend.event.partials.interpro-draws-desktop', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      
      <?php echo $__env->renderWhen(isset($myClothingOrders) && $myClothingOrders->isNotEmpty(),
          'frontend.event.partials._my_clothing_orders', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

    </div>
  </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\eventTypes\interpro.blade.php ENDPATH**/ ?>