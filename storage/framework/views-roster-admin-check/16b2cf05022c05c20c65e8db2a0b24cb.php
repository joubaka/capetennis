<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="<?php echo e((!empty($containerNav) ? $containerNav : 'container-fluid')); ?>">
    <div class="footer-container d-flex align-items-center justify-content-between py-2 flex-md-row flex-column">
      <div>
        &copy; <?php echo e(now()->year); ?> Cape Tennis. All rights reserved.
        <span class="d-block d-md-inline">Developed by SportStack Technologies.</span>
      </div>
      <div>
   
      </div>
    </div>
  </div>
</footer>
<!--/ Footer-->
<script>
var APP_URL = <?php echo json_encode(url('/')); ?>

</script>
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\sections\footer\footer.blade.php ENDPATH**/ ?>