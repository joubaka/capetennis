@auth
@if($event->registrationIsOpen())
@php
  $myCategoryEntries = \App\Models\CategoryEventRegistration::with('categoryEvent.category', 'registration.players')
    ->where('user_id', auth()->id())->activeAndPaid()->where('status', 'active')
    ->whereHas('categoryEvent', fn($query) => $query->where('event_id', $event->id))->limit(100)->get();
  $pendingCategoryChanges = \App\Models\CategoryAdjustment::whereIn('entry_id', $myCategoryEntries->pluck('id'))
    ->where('user_id', auth()->id())->where('status', 'pending')->where('expires_at', '>', now())->get()->keyBy('entry_id');
  $moveCategories = $event->categoryEvents()->with('category')->orderBy('ordering')->get();
@endphp
@if($myCategoryEntries->isNotEmpty())
<div class="card mt-3"><div class="card-body">
  <h3 class="h5">Your category changes</h3>
  <p class="small text-muted">Before entries close, you can change an eligible entry. Pay any increase through PayFast; any decrease is credited to your wallet.</p>
  @foreach($myCategoryEntries as $moveEntry)
    @php($movePlayerNames = $moveEntry->registration?->players->map(fn($player) => $player->full_name)->implode(' / '))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top py-2">
      <span>{{ $movePlayerNames }} — {{ $moveEntry->categoryEvent->category?->name }}</span>
      @if($pendingCategoryChanges->has($moveEntry->id))
        <a class="btn btn-sm btn-outline-primary" href="{{ route('registrations.categoryAdjustment.show', $pendingCategoryChanges->get($moveEntry->id)) }}">Resume or cancel category payment</a>
      @else
        <button type="button" class="btn btn-sm btn-outline-warning move-category-btn" data-bs-toggle="modal" data-bs-target="#moveCategoryModal"
          data-entry-id="{{ $moveEntry->id }}" data-player="{{ $movePlayerNames }}"
          data-current-category="{{ $moveEntry->categoryEvent->category?->name }}" data-current-category-id="{{ $moveEntry->category_event_id }}">Change category</button>
      @endif
    </div>
  @endforeach
</div></div>
@endif
<div class="modal fade" id="moveCategoryModal" tabindex="-1" aria-hidden="true" aria-labelledby="mc-title">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="mc-title">Change category</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p><strong id="mc-player"></strong></p><p>Current: <span id="mc-current-cat"></span></p>
      <label class="form-label" for="mc-new-category">Move to</label>
      <select id="mc-new-category" class="form-select"><option value="">Select category…</option>
        @foreach($moveCategories as $moveCategory)<option value="{{ $moveCategory->id }}">{{ $moveCategory->category?->name }}</option>@endforeach
      </select>
      <p class="small text-muted mt-2">Review the price difference before confirming. Category eligibility and draw restrictions apply.</p>
      <p id="mc-error" class="text-danger" role="alert"></p>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="mc-submit-btn">Review change</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('moveCategoryModal');
  if (!modal) return;
  const select = document.getElementById('mc-new-category');
  const button = document.getElementById('mc-submit-btn');
  const error = document.getElementById('mc-error');
  const base = @json(url('/registrations'));
  let entryId;
  modal.addEventListener('show.bs.modal', function (event) {
    const data = event.relatedTarget.dataset;
    entryId = data.entryId;
    document.getElementById('mc-player').textContent = data.player || '';
    document.getElementById('mc-current-cat').textContent = data.currentCategory || '';
    select.value = ''; error.textContent = '';
    Array.from(select.options).forEach(option => option.disabled = option.value === data.currentCategoryId);
  });
  async function post(path, body) {
    const response = await fetch(path, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(body)});
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to change category.');
    return result;
  }
  button.addEventListener('click', async function () {
    if (!select.value) { error.textContent = 'Select a category.'; return; }
    button.disabled = true; error.textContent = '';
    const destination = select.value;
    try {
      const quote = await post(base+'/'+entryId+'/category-quote', {new_category_event_id:destination});
      const amount = Math.abs(Number(quote.difference)).toFixed(2);
      const text = quote.difference > 0 ? 'Pay R'+amount+' through PayFast to complete this change. Your entry stays in its current category until payment is verified.'
        : quote.difference < 0 ? 'Change category and receive R'+amount+' in your wallet.' : 'Change category with no additional payment.';
      const instance = bootstrap.Modal.getInstance(modal);
      if (instance) {
        await new Promise(resolve => { modal.addEventListener('hidden.bs.modal', resolve, {once:true}); instance.hide(); });
      }
      const result = await Swal.fire({title:'Confirm category change',text:text,showCancelButton:true,confirmButtonText:quote.difference>0?'Continue to payment':quote.difference<0?'Change and credit wallet':'Change category'});
      if (!result.isConfirmed) return;
      const changed = await post(base+'/'+entryId+'/move-category', {new_category_event_id:destination,quote_token:quote.quote_token,request_token:quote.request_token});
      if (changed.checkout_url) { window.location.assign(changed.checkout_url); return; }
      bootstrap.Modal.getInstance(modal)?.hide();
      await Swal.fire({icon:'success',title:'Category changed',text:changed.message});
      window.location.reload();
    } catch (exception) {
      if (modal.classList.contains('show')) error.textContent = exception.message;
      else await Swal.fire({icon:'error',title:'Unable to change category',text:exception.message});
    }
    finally { button.disabled = false; }
  });
});
</script>
@endif
@endauth
