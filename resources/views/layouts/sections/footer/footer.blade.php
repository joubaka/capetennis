<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ (!empty($containerNav) ? $containerNav : 'container-fluid') }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-2 flex-md-row flex-column">
      <div>
        © <script>
          document.write(new Date().getFullYear())

        </script>
        , made with ❤️ by JTA Sollutions
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