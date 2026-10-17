<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('teamPaymentRecoveryModal');
  const form = document.getElementById('teamPaymentRecoveryForm');
  const retry = document.getElementById('teamPaymentRecoveryRetry');
  if (!modal || !form || !retry) return;
  let submitted = false;
  form.addEventListener('submit', function (event) {
    if (submitted || !form.checkValidity()) {
      event.preventDefault();
      return;
    }
    submitted = true;
    retry.disabled = true;
    retry.textContent = 'Restarting registration…';
  });
  bootstrap.Modal.getOrCreateInstance(modal).show();
});
</script>
