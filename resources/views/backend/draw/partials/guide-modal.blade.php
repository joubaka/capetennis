@once
<div class="modal fade" id="draw-setup-guide-modal" tabindex="-1" aria-labelledby="draw-setup-guide-title" aria-hidden="true" data-draw-guide-modal>
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="draw-setup-guide-title">Continue draw setup</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close draw setup"></button></div>
    <div class="modal-body" data-draw-guide-body></div>
  </div></div>
</div>
<script src="{{ asset('js/draw-setup-guide.js') }}?v={{ filemtime(public_path('js/draw-setup-guide.js')) }}" defer></script>
@endonce
