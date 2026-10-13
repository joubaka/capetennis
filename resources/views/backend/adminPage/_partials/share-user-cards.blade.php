<div class="share-user-list">
@forelse($choices as $choice)
  <label class="share-user-card"><input type="checkbox" class="form-check-input" data-share-user name="recipient_ids[]" value="{{ $choice['id'] }}" data-name="{{ $choice['name'] }}" data-email="{{ $choice['email'] }}" data-selectable="{{ $choice['selectable'] ? '1' : '0' }}" @disabled(!$choice['selectable']) @checked(in_array($choice['id'], old('recipient_ids', [])))><span><strong>{{ $choice['name'] }}</strong><small>Account #{{ $choice['id'] }} · {{ $choice['email'] }}</small>@if($choice['shared'])<small class="share-access-label">Already has preview access</small>@endif @if(!$choice['selectable'])<small class="text-danger">Valid account email needed</small>@endif</span></label>
@empty<div class="share-empty"><strong>No matching accounts</strong><p>Try part of a name, surname or email address.</p></div>@endforelse
</div>
@include('backend.adminPage._partials.share-pagination', ['paginator' => $users, 'label' => 'Account search', 'ajax' => true])
