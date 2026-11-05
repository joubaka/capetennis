<details {{ $attributes }}>
  <summary class="fw-semibold">Court Convenor Guide</summary>
  <p class="text-muted small mt-2 mb-2">Copy the current guide into your assignment message to the convenor.</p>
  <textarea class="form-control mb-2" rows="8" aria-label="Court Convenor Guide" readonly>{{ \App\Models\SiteSetting::courtConvenorGuide() }}</textarea>
  <button type="button" class="btn btn-sm btn-outline-primary"
          onclick="copyCourtConvenorGuide(this)">Copy guide</button>
  <span class="small ms-2" role="status"></span>
</details>

@once
  <script>
    async function copyCourtConvenorGuide(button) {
      const guide = button.previousElementSibling;
      const status = button.nextElementSibling;

      try {
        await navigator.clipboard.writeText(guide.value);
        status.textContent = 'Guide copied.';
      } catch {
        guide.focus();
        guide.select();
        status.textContent = 'Use your device’s Copy command.';
      }
    }
  </script>
@endonce
