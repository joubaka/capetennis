@php
  $player = optional($reg->registration?->players)->first();
@endphp

<tr data-row data-entry-id="{{ $reg->id }}">

  {{-- # --}}
  <td>—</td>

  {{-- Player --}}
  <td>{{ $player?->name }} {{ $player?->surname }}</td>

  {{-- Email --}}
  <td class="col-email">
    @if($player?->email)
      <a href="mailto:{{ $player->email }}" class="text-decoration-none">{{ $player->email }}</a>
    @else
      —
    @endif
  </td>

  {{-- Cell --}}
  <td class="col-cell">{{ $player?->cellNr ?? '—' }}</td>

  {{-- Status --}}
  <td>
    <span class="badge {{ $reg->status === 'withdrawn' ? 'bg-danger' : 'bg-success' }}">
      {{ ucfirst($reg->status ?? 'active') }}
    </span>
  </td>

  {{-- Payment --}}
  <td>
    <span class="badge {{ $reg->payment_status_id == 1 ? 'bg-success' : 'bg-warning' }}">
      {{ $reg->payment_status_id == 1 ? 'Paid' : 'Unpaid' }}
    </span>
    @if($reg->payfast_id === 'Admin')
      <br><span class="badge bg-info text-dark mt-1" style="font-size:.65rem;">Admin Added</span>
    @endif
  </td>

  {{-- Actions --}}
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
        <li>
          <button type="button"
                  class="dropdown-item email-btn"
                  data-scope="player"
                  data-registration="{{ $reg->registration_id }}">
            <i class="ti ti-mail me-1"></i>Email
          </button>
        </li>
        <li>
          <button type="button"
                  class="dropdown-item move-player-btn"
                  data-entry="{{ $reg->id }}"
                  data-player="{{ $player?->name }} {{ $player?->surname }}"
                  data-from-category="">
            <i class="ti ti-arrows-transfer-up me-1"></i>Move
          </button>
        </li>
        @if($reg->status !== 'withdrawn')
          <li>
            <button type="button"
                    class="dropdown-item text-warning withdraw-player-btn"
                    data-url="{{ route('admin.category.registration.withdraw', $reg) }}"
                    data-player="{{ trim(($player?->name ?? '') . ' ' . ($player?->surname ?? '')) }}">
              <i class="ti ti-user-minus me-1"></i>Withdraw
            </button>
          </li>
        @endif
        @if(auth()->user()->hasRole('super-user'))
          <li><hr class="dropdown-divider"></li>
          <li>
            <button type="button"
                    class="dropdown-item text-info view-entry-details-btn"
                    data-url="{{ route('admin.entry.details', $reg) }}">
              <i class="ti ti-info-circle me-1"></i>View Details
            </button>
          </li>
        @endif
      </ul>
    </div>
  </td>

</tr>
