<tr data-entry-id="{{ $reg->id ?? '' }}">
  <td>{{ $loop->iteration ?? '—' }}</td>
  <td>
    {{ $pairName }}
    <br><small class="text-muted" style="font-size:.7rem;">doubles</small>
  </td>
  <td class="col-email">—</td>
  <td class="col-cell">—</td>
  <td>
    <span class="badge bg-success">Active</span>
  </td>
  <td>
    <span class="badge {{ ($reg->payment_status_id ?? 0) == 1 ? 'bg-success' : 'bg-warning' }}">
      {{ ($reg->payment_status_id ?? 0) == 1 ? 'Paid' : 'Unpaid' }}
    </span>
    @if(($reg->payfast_id ?? null) === 'Admin')
      <br><span class="badge bg-info text-dark mt-1" style="font-size:.65rem;">Admin Added</span>
    @endif
  </td>
  <td class="col-actions text-end">
    <div class="dropdown">
      <button type="button"
              class="btn btn-outline-secondary btn-sm dropdown-toggle"
              data-bs-toggle="dropdown"
              data-bs-strategy="fixed"
              aria-expanded="false">
        Actions
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        @if($reg ?? null)
          <li>
            <button type="button"
                    class="dropdown-item text-warning withdraw-player-btn"
                    data-url="{{ route('admin.category.registration.withdraw', $reg) }}"
                    data-player="{{ $pairName }}">
              <i class="ti ti-user-minus me-1"></i>Withdraw
            </button>
          </li>
        @endif
      </ul>
    </div>
  </td>
</tr>
