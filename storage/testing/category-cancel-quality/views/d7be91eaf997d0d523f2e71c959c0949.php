<script>
(() => {
  const editor = document.getElementById(<?php echo json_encode($editorId, 15, 512) ?>);
  if (!editor) return;
  let pending = <?php echo json_encode($pendingPreview, 15, 512) ?>;
  let submitting = false;
  const markPending = () => { pending = true; };
  editor.addEventListener('input', markPending);
  editor.addEventListener('change', markPending);
  editor.addEventListener('drop', markPending);
  editor.addEventListener('submit', () => { submitting = true; });
  window.addEventListener('beforeunload', event => {
    if (!pending || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });
})();
</script>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\schedule\partials\unsaved-guard.blade.php ENDPATH**/ ?>