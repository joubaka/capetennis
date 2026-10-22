<section class="border-top" id="finance-clothing-settlements" aria-labelledby="clothing-settlement-title">
  <div class="p-3">
    <h5 class="mb-1" id="clothing-settlement-title">Clothing payouts and recovery</h5>
    <p class="small text-muted mb-0">Recorded clothing payouts reduce the amount still payable. Completed refunds after a payout can leave an amount to recover or offset. Pending refunds remain a separate liability. Registration income and profit are unaffected.</p>
  </div>
  @php($visibleClothingRegions = $clothingReconciliation->filter(fn ($region) => $region['net_cents'] !== 0 || $region['entries']->isNotEmpty() || $region['refunds']->isNotEmpty()))
  @php($recordableClothingRegions = $clothingReconciliation->filter(fn ($region) => $region['recording_available']))
  @if($clothingReconciliation->contains(fn ($region) => ! $region['recording_available']))
    <div class="alert alert-warning mx-3" role="alert">A region has duplicate or incomplete event associations. Its clothing funds and audit history are counted once; recording payouts, recoveries or offsets for that region is disabled until its setup is reviewed.</div>
  @endif
  @if($errors->any() && $recordableClothingRegions->isEmpty())<div class="alert alert-danger mx-3" role="alert">{{ $errors->first() }}</div>@endif
  <ul class="list-unstyled d-xl-none px-3 mb-3" aria-label="Regional clothing balances at a glance">
    @forelse($visibleClothingRegions as $region)
      <li class="border rounded p-3 mb-2">
        <strong class="d-block mb-2">{{ $region['scope']->region?->region_name ?? 'Region not recorded' }}</strong>
        @if(! $region['recording_available'])<small class="d-block text-warning mb-2">Ambiguous event associations — settlement recording unavailable</small>@endif
        <span class="d-block fs-4 fw-semibold {{ $region['balance_cents'] < 0 ? 'text-danger' : '' }}">R {{ number_format(abs($region['balance_cents']) / 100, 2) }}</span>
        <span class="d-block fw-semibold {{ $region['balance_cents'] < 0 ? 'text-danger' : '' }}">{{ $region['balance_cents'] < 0 ? 'Recover or offset' : 'Still payable' }}</span>
        <small class="d-block text-muted mt-2">Already paid out: R {{ number_format($region['payout_cents'] / 100, 2) }}</small>
        @if($region['shortfall_cents'] > 0)<small class="d-block text-warning">Original overpayment / shortfall: R {{ number_format($region['shortfall_cents'] / 100, 2) }}</small>@endif
        @if($region['pending_cents'] > 0)
          <small class="d-block text-muted">Pending refund liability: R {{ number_format($region['pending_cents'] / 100, 2) }}</small>
          @if($region['balance_cents'] >= 0)<small class="d-block text-muted">Available after pending: R {{ number_format($region['available_cents'] / 100, 2) }}</small>@endif
        @endif
      </li>
    @empty
      <li class="text-muted">No regional clothing settlement balance recorded.</li>
    @endforelse
  </ul>
  <p class="small text-muted px-3 d-xl-none">Swipe the table for the full clothing breakdown.</p>
  <div class="table-responsive">
    <table class="table table-sm mb-0" aria-label="Regional clothing settlement balances">
      <thead class="table-light"><tr><th>Region</th><th class="text-end">Net clothing receipts</th><th class="text-end">Already paid out</th><th class="text-end">Recovered</th><th class="text-end">Agreed offsets (+ / −)</th><th class="text-end">Pending refunds</th><th class="text-end">Still payable / recover</th></tr></thead>
      <tbody>
        @forelse($visibleClothingRegions as $region)
          <tr>
            <td style="min-width:160px">{{ $region['scope']->region?->region_name ?? 'Region not recorded' }}<small class="d-block text-muted">{{ $region['scope']->region?->clothing_order ? 'Ordering currently open' : 'Ordering currently closed' }}</small>@if(! $region['recording_available'])<small class="d-block text-warning">Ambiguous event associations — recording unavailable</small>@endif</td>
            <td class="text-end">R {{ number_format($region['net_cents'] / 100, 2) }}</td>
            <td class="text-end">R {{ number_format($region['payout_cents'] / 100, 2) }}@if($region['shortfall_cents'] > 0)<small class="d-block text-warning">Recorded overpayment / shortfall: R {{ number_format($region['shortfall_cents'] / 100, 2) }}</small>@endif</td>
            <td class="text-end">R {{ number_format($region['recovery_cents'] / 100, 2) }}</td>
            <td class="text-end">R {{ number_format($region['offset_cents'] / 100, 2) }}</td>
            <td class="text-end">R {{ number_format($region['pending_cents'] / 100, 2) }}</td>
            <td class="text-end fw-semibold {{ $region['balance_cents'] < 0 ? 'text-danger' : '' }}">
              @if($region['balance_cents'] < 0)
                R {{ number_format(abs($region['balance_cents']) / 100, 2) }}<small class="d-block">Recover or offset</small>
              @else
                R {{ number_format($region['balance_cents'] / 100, 2) }}<small class="d-block text-muted">Still payable</small>
                @if($region['pending_cents'] > 0)<small class="d-block text-muted">Available after pending: R {{ number_format($region['available_cents'] / 100, 2) }}</small>@endif
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-muted p-3">No regional clothing settlement balance recorded.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @php($projectedClothingRegions = ($convenorPayoutProjection['cards'] ?? collect())->flatMap(fn ($card) => $card['regions'])->keyBy('region_id'))
  <div class="px-3 py-3">
    @foreach($visibleClothingRegions as $region)
      @php($regionProjection = $projectedClothingRegions->get($region['scope']->region_id))
      <details class="border rounded mb-2" id="finance-clothing-region-{{ $region['scope']->region_id }}" data-finance-details>
        <summary class="p-3 fw-semibold">{{ $region['scope']->region?->region_name ?? 'Region not recorded' }} — clothing breakdown</summary>
        <div class="px-3 pb-3">
          <dl class="row mb-2 small">
            <dt class="col-8">Net clothing receipts after completed refunds</dt><dd class="col-4 text-end">R {{ number_format($region['net_cents'] / 100, 2) }}</dd>
            <dt class="col-8">Less payments already made</dt><dd class="col-4 text-end">−R {{ number_format($region['payout_cents'] / 100, 2) }}</dd>
            <dt class="col-8">Plus money recovered</dt><dd class="col-4 text-end">+R {{ number_format($region['recovery_cents'] / 100, 2) }}</dd>
            <dt class="col-8">Recorded agreed offsets</dt><dd class="col-4 text-end">{{ $region['offset_cents'] < 0 ? '−' : '+' }}R {{ number_format(abs($region['offset_cents']) / 100, 2) }}</dd>
            <dt class="col-8 border-top pt-2">{{ $region['balance_cents'] < 0 ? 'Clothing balance to recover' : 'Clothing balance still payable' }}</dt><dd class="col-4 border-top pt-2 text-end fw-semibold">R {{ number_format(abs($region['balance_cents']) / 100, 2) }}</dd>
            <dt class="col-8">Pending refunds reserved</dt><dd class="col-4 text-end">R {{ number_format($region['pending_cents'] / 100, 2) }}</dd>
          </dl>
          @if($regionProjection)
            <div class="border-top pt-2 fw-semibold d-flex justify-content-between gap-2 {{ $regionProjection['contribution_cents'] < 0 ? 'text-danger' : 'text-primary' }}">
              <span>{{ $regionProjection['contribution_cents'] < 0 ? 'Proposed deduction from convenor payout' : 'Proposed addition to convenor payout' }}</span>
              <span class="text-nowrap">{{ $regionProjection['contribution_cents'] < 0 ? '−' : '+' }}R {{ number_format(abs($regionProjection['contribution_cents']) / 100, 2) }}</span>
            </div>
            <p class="small text-muted mt-1">This is the same clothing adjustment shown in the Convenor Payout Breakdown. Pending refunds reduce available payments; completed refunds are already included in net receipts above.</p>
          @elseif(isset($convenorPayoutProjection))
            <p class="small text-muted">This regional balance has no adjustment assigned to a director payout.</p>
          @endif
          <h6 class="mt-3">Recorded payments and adjustments</h6>
          <ul class="list-unstyled small">
            @forelse($region['entries']->sortBy('occurred_at') as $entry)
              <li class="mb-2">{{ $entry->occurred_at->format('d M Y') }} · {{ match($entry->kind) { 'payout' => 'Payment', 'recovery' => 'Recovery', 'offset_in' => 'Offset received', 'offset_out' => 'Offset deducted' } }} · <strong>R {{ $entry->amount }}</strong> · {{ $entry->recipient_name }}<span class="d-block text-muted">{{ $entry->reference }}</span></li>
            @empty<li class="text-muted">No payments or adjustments recorded for this region.</li>@endforelse
          </ul>
          <h6 class="mt-3">Clothing refunds</h6>
          <ul class="list-unstyled small mb-0">
            @forelse($region['refunds'] as $refund)
              <li class="mb-2"><a href="{{ route('backend.clothing.refunds.show', [$event, $refund->order]) }}">Order #{{ $refund->clothing_order_id }}</a> · <strong>R {{ $refund->refund_net }}</strong> · {{ ucfirst($refund->refund_status) }}<span class="d-block text-muted">{{ $refund->reason }}{{ $refund->refunded_at ? ' · '.$refund->refunded_at->format('d M Y') : '' }}</span></li>
            @empty<li class="text-muted">No refunds recorded for this region.</li>@endforelse
          </ul>
        </div>
      </details>
    @endforeach
  </div>

  @if(auth()->user()->hasRole('super-user') && $recordableClothingRegions->isNotEmpty())
    <details class="p-3 border-top no-print" data-finance-details @if($errors->any()) open @endif>
      <summary class="fw-semibold">Record clothing payment</summary>
      <p class="small text-muted mt-2">Record a payment already made. Choose recovery or offset when recording a clothing adjustment.</p>
      @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
      <form method="POST" action="{{ route('admin.events.clothing-settlements.store', $event) }}" data-clothing-settlement-form>
        @csrf
        <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="clothing-settlement-region">Region</label><select required class="form-select" id="clothing-settlement-region" name="event_region_id"><option value="">Choose region</option>@foreach($recordableClothingRegions as $region)<option value="{{ $region['scope']->id }}" @selected((string) old('event_region_id') === (string) $region['scope']->id)>{{ $region['scope']->region?->region_name ?? 'Region not recorded' }}</option>@endforeach</select></div>
          <div class="col-md-6"><label class="form-label" for="clothing-settlement-kind">Action</label><select required class="form-select" id="clothing-settlement-kind" name="kind"><option value="payout" @selected(old('kind') === 'payout')>Payment</option><option value="recovery" @selected(old('kind') === 'recovery')>Recovery received</option><option value="offset" @selected(old('kind') === 'offset')>Agreed offset</option></select></div>
          <div class="col-md-4"><label class="form-label" for="clothing-settlement-amount">Amount (R)</label><input required class="form-control" id="clothing-settlement-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}"></div>
          <div class="col-md-4"><label class="form-label" for="clothing-settlement-date">Date</label><input required type="date" class="form-control" id="clothing-settlement-date" name="occurred_at" max="{{ now()->format('Y-m-d') }}" value="{{ substr((string) old('occurred_at'), 0, 10) }}"></div>
          <div class="col-md-4"><label class="form-label" for="clothing-settlement-recipient">Person</label><select required class="form-select" id="clothing-settlement-recipient" name="recipient_user_id" data-users-url="{{ route('admin.events.clothing-settlements.users', $event) }}"><option value="">Search users</option>@if(old('recipient_user_id') && ($settlementRecipient = \App\Models\User::find(old('recipient_user_id'))))<option selected value="{{ $settlementRecipient->id }}">{{ $settlementRecipient->name }} ({{ $settlementRecipient->email }})</option>@endif</select></div>
          <div class="col-md-6"><label class="form-label" for="clothing-settlement-reference">Payment / agreement reference</label><input required maxlength="150" class="form-control" id="clothing-settlement-reference" name="reference" value="{{ old('reference') }}"></div>
          <div class="col-md-6" data-clothing-offset-field><label class="form-label" for="clothing-offset-source">Clothing region whose payable funds the offset</label><select class="form-select" id="clothing-offset-source" name="source_event_region_id"><option value="">Choose source region (offset only)</option>@foreach($recordableClothingRegions as $region)<option value="{{ $region['scope']->id }}" @selected((string) old('source_event_region_id') === (string) $region['scope']->id)>{{ $region['scope']->region?->region_name }} — available R {{ number_format($region['available_cents'] / 100, 2) }}</option>@endforeach</select></div>
          <div class="col-12"><label class="form-label" for="clothing-settlement-evidence">Notes <span class="text-muted" data-clothing-notes-optional>(optional for payments)</span></label><textarea minlength="10" maxlength="2000" class="form-control" id="clothing-settlement-evidence" name="evidence" rows="3" placeholder="For an offset, record both recipients and their agreement reference.">{{ old('evidence') }}</textarea></div>
          <div class="col-12" data-clothing-overpayment-field><details @if(old('overpayment_confirmed')) open @endif><summary>Actual payout exceeded recorded clothing funds</summary><p class="small text-muted">Use only when payment evidence proves a larger payout already happened. This records the discrepancy as an amount to recover; it does not authorize a new overpayment.</p><label class="d-flex gap-2 align-items-start mb-2"><input type="checkbox" class="form-check-input flex-shrink-0" name="overpayment_confirmed" value="1" @checked(old('overpayment_confirmed'))>I confirm the actual payout exceeded the recorded available clothing funds.</label><label class="form-label" for="clothing-overpayment-reason">Explain the overpayment and identify supporting evidence</label><textarea class="form-control" id="clothing-overpayment-reason" name="overpayment_reason" maxlength="1000" rows="2">{{ old('overpayment_reason') }}</textarea></details></div>
          <div class="col-12" data-clothing-offset-field><label class="d-flex gap-2 align-items-start"><input type="checkbox" class="form-check-input flex-shrink-0" name="offset_agreement_confirmed" value="1" @checked(old('offset_agreement_confirmed'))>Both affected regional recipients authorized this offset; their names and agreement evidence are recorded above. The source clothing payable is reduced by the same amount. This does not draw on registration funds.</label></div>
          <div class="col-12"><label class="d-flex gap-2 align-items-start"><input required type="checkbox" class="form-check-input flex-shrink-0" name="confirmed" value="1">I confirm the amount, date and person are correct.</label></div>
          <div class="col-12"><button class="btn btn-primary" type="submit">Save payment</button></div>
        </div>
      </form>
    </details>
  @endif

  <details class="border-top" data-finance-details>
    <summary class="p-3 fw-semibold">Clothing refund history and actors</summary>
    <p class="small text-muted px-3">Every completed refund affects the regional balance, even when no deadline was recorded. “After payout” compares the actual payout date with the refund completion date. A currently closed ordering switch alone cannot establish a historical closing date. Aggregate recovery does not mark an individual refund resolved.</p>
    <div class="table-responsive"><table class="table table-sm mb-0" aria-label="Clothing refunds and recorded actors"><thead class="table-light"><tr><th>Region / order</th><th>Refund / reason</th><th>Requested by</th><th>Completed by</th><th>Reconciliation context</th></tr></thead><tbody>
      @forelse($clothingReconciliation->flatMap(fn ($region) => $region['refunds']->map(fn ($refund) => ['scope' => $region['scope'], 'refund' => $refund])) as $row)
        @php($refund = $row['refund'])
        <tr><td>{{ $row['scope']->region?->region_name }}<br><a href="{{ route('backend.clothing.refunds.show', [$event, $refund->order]) }}">Order #{{ $refund->clothing_order_id }}</a></td>
          <td><strong>R {{ $refund->refund_net }} · {{ ucfirst($refund->refund_status) }}</strong><small class="d-block">{{ ucfirst($refund->refund_method) }} · {{ $refund->reason }}</small>@include('backend.clothing.partials.historical-refund-source')</td>
          <td>{{ $refund->requested_by_name ?? $refund->requestedBy?->name ?? 'Not recorded' }}<small class="d-block text-muted">{{ $refund->created_at?->format('d M Y H:i') }}</small></td>
          <td>{{ $refund->completed_by_name ?? $refund->completedBy?->name ?? 'Not recorded' }}<small class="d-block text-muted">{{ $refund->refunded_at?->format('d M Y H:i') }}</small>@if($refund->refund_method === 'payfast' && $refund->refund_status === 'completed' && ! $refund->completed_by)<small class="d-block text-muted">PayFast completion; initiator shown separately</small>@endif</td>
          <td>@if($refund->after_recorded_payout)<span class="badge bg-label-warning">After recorded payout</span>@endif @if($refund->after_recorded_deadline)<span class="badge bg-label-warning">After recorded deadline</span>@endif<small class="d-block text-muted">{{ $row['scope']->region?->clothing_order ? 'Ordering currently open' : 'Ordering currently closed' }} · {{ $refund->clothing_deadline_at ? 'Recorded refund deadline '.$refund->clothing_deadline_at->format('d M Y H:i') : 'No deadline recorded' }}</small></td>
        </tr>
      @empty<tr><td colspan="5" class="text-muted p-3">No clothing refunds recorded.</td></tr>@endforelse
    </tbody></table></div>
  </details>

  <details class="border-top" data-finance-details>
    <summary class="p-3 fw-semibold">Clothing settlement audit history</summary>
    <div class="table-responsive"><table class="table table-sm mb-0" aria-label="Clothing settlement audit history"><thead class="table-light"><tr><th>Region / type</th><th class="text-end">Amount</th><th>Recipient</th><th>Actual date</th><th>Reference / evidence</th><th>Recorded by / date</th></tr></thead><tbody>
      @forelse($clothingReconciliation->flatMap(fn ($region) => $region['entries'])->sortByDesc('occurred_at') as $entry)
        <tr><td>{{ $entry->region_name }}<small class="d-block">{{ match($entry->kind) { 'payout' => 'Payout already paid', 'recovery' => 'Recovery received', 'offset_in' => 'Agreed offset received', 'offset_out' => 'Agreed offset deducted' } }}</small>@if($entry->counterparty_event_region_id)<small class="d-block text-muted">Paired region record #{{ $entry->counterparty_event_region_id }} · {{ $entry->request_token }}</small>@endif</td><td class="text-end">R {{ $entry->amount }}</td><td>{{ $entry->recipient_name }}</td><td>{{ $entry->occurred_at->format($entry->hasDateOnlyEvidence() ? 'd M Y' : 'd M Y H:i') }}@if($entry->hasDateOnlyEvidence())<small class="d-block text-muted">Time not recorded</small>@endif</td><td>{{ $entry->reference }}<small class="d-block text-muted">{{ $entry->evidence }}</small>@if($entry->funding_shortfall > 0)<small class="d-block text-warning">Original funding shortfall R {{ $entry->funding_shortfall }} · {{ $entry->overpayment_reason }}</small>@endif</td><td>{{ $entry->recorded_by_name }}<small class="d-block text-muted">{{ $entry->created_at->format('d M Y H:i') }}</small></td></tr>
      @empty<tr><td colspan="6" class="text-muted p-3">No clothing payouts, recoveries or offsets recorded. Net clothing receipts alone do not confirm a payout.</td></tr>@endforelse
    </tbody></table></div>
  </details>
</section>
