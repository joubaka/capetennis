<script>
(() => {
  const editor = document.getElementById(@json($editorId));
  if (!editor) return;
  let pending = @json($pendingPreview);
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
