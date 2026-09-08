@if($reg->isAdminEntry())
  @php $adminCollectionPaid = $reg->admin_payment_status === 'paid'; @endphp
  <div class="d-flex flex-column align-items-center gap-1" data-admin-payment-note>
    <span class="badge {{ $adminCollectionPaid ? 'bg-success' : 'bg-warning text-dark' }}" data-admin-payment-badge>
      Admin entry {{ $adminCollectionPaid ? 'paid' : 'unpaid' }}
    </span>
    @if($reg->status !== 'withdrawn')
      <button type="button"
              class="btn btn-xs {{ $adminCollectionPaid ? 'btn-outline-warning' : 'btn-outline-success' }} admin-payment-toggle-btn"
              data-url="{{ route('admin.entry.admin-payment-status', $reg) }}"
              data-next-paid="{{ $adminCollectionPaid ? '0' : '1' }}">
        {{ $adminCollectionPaid ? 'Mark unpaid' : 'Mark paid' }}
      </button>
    @endif
  </div>
@else
  <span class="badge {{ $reg->payment_status_id == 1 ? 'bg-success' : 'bg-warning text-dark' }}">
    {{ $reg->payment_status_id == 1 ? 'Paid' : 'Unpaid' }}
  </span>
@endif
