<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ (!empty($containerNav) ? $containerNav : 'container-fluid') }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-2 flex-md-row flex-column">
      <div>
        &copy; {{ now()->year }} Cape Tennis. All rights reserved.
        <span class="d-block d-md-inline">Developed by SportStack Technologies.</span>
      </div>
      <div>
   
      </div>
    </div>
  </div>
</footer>
<!--/ Footer-->
<script>
var APP_URL = {!! json_encode(url('/')) !!}
</script>
