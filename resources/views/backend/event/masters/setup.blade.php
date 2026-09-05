@extends('layouts.backend')
@section('title', 'Masters setup')
@section('page-style')<style>.masters-page .card{border:1px solid #ebeaf0;box-shadow:0 .25rem 1rem rgba(47,43,61,.05)}.masters-page .card-header{background:#fff;border-bottom:1px solid #ebeaf0}.masters-page .category-row{border-bottom:1px solid #ebeaf0;padding:.7rem 0}.masters-page .stepper{display:inline-flex;align-items:center;border:1px solid #d9d7e2;border-radius:.4rem;overflow:hidden}.masters-page .stepper button{border:0;background:#f7f6fa;width:2rem;height:2rem;color:#5f596d}.masters-page .stepper output{min-width:2.2rem;text-align:center;font-weight:600}</style>@endsection
@section('content')<div class="container-xl masters-page">@include('backend.event.partials.header',['event'=>$event])<div class="mb-3"><h4 class="mb-1">Masters setup</h4><p class="text-muted mb-0">Configure the ranking categories used for this Masters event.</p></div>@include('backend.event.masters._setup')</div>@endsection
@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const manager = document.getElementById('masters-category-manager');
  if (!manager) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  const save = async (row, payload) => {
    row.classList.add('opacity-50');
    row.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(manager.dataset.updateUrl.replace('__LINK__', row.dataset.linkId), {
        method: 'PATCH',
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
        body: JSON.stringify(payload),
      });
      if (!response.ok) throw await AppFeedback.responseError(response, 'Could not save category settings.');
      const data = await response.json();
      row.dataset.saved = '1';
      AppFeedback.success(data.message || 'Category settings updated.');
    } catch (error) {
      AppFeedback.fromError(error, 'Could not save category settings.');
      window.location.reload();
    } finally {
      row.classList.remove('opacity-50');
      row.removeAttribute('aria-busy');
    }
  };

  manager.querySelectorAll('.category-row').forEach(row => {
    const output = row.querySelector('[data-top-x]');
    row.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => {
      const value = Math.max(1, Math.min(100, Number(output.textContent) + Number(button.dataset.step)));
      output.textContent = value;
      save(row, {top_x: value});
    }));
    row.querySelector('.category-toggle').addEventListener('click', function () {
      const enabled = this.dataset.enabled !== '1';
      this.dataset.enabled = enabled ? '1' : '0';
      this.classList.toggle('btn-success', enabled);
      this.classList.toggle('btn-outline-secondary', !enabled);
      this.querySelector('.category-state').textContent = enabled ? 'Enabled' : 'Off';
      save(row, {enabled});
    });
  });
});
</script>
@endsection
