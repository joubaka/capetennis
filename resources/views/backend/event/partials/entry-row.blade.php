@php
  $player = optional($reg->registration?->players)->first();
@endphp

<tr data-row data-entry-id="{{ $reg->id }}">

  {{-- # --}}
  <td data-label="#">—</td>

  {{-- Player --}}
  <td data-label="Player">{{ $player?->name }} {{ $player?->surname }}</td>

  {{-- Status --}}
  <td class="col-status" data-label="Status">
    <span class="badge {{ $reg->status === 'withdrawn' ? 'bg-danger' : 'bg-success' }}">
      {{ ucfirst($reg->status ?? 'active') }}
    </span>
  </td>

  {{-- Payment --}}
  <td data-label="Payment">
    @include('backend.event.partials.admin-payment-note', ['reg' => $reg])
  </td>

  {{-- Contact --}}
  <td class="col-contact" data-label="Contact"><details><summary>Contact details</summary><div class="mt-2 text-break">
    @if($player?->email)<a href="mailto:{{ $player->email }}">{{ $player->email }}</a>@else<span>No email captured</span>@endif
    <div>{{ $player?->cellNr ?? 'No cell number captured' }}</div>
  </div></details></td>
  @if(auth()->user()->hasAnyRole(['super-user', 'admin']))
    <td class="col-poc" data-label="POC">
      @if($player?->is_player_of_colour === true)
        <span class="badge bg-info">Yes</span>
      @elseif($player?->is_player_of_colour === false)
        <span class="badge bg-light text-dark">No</span>
      @else
        <span class="text-muted">—</span>
      @endif
    </td>
  @endif

  {{-- Actions --}}
  <td class="col-actions text-end" data-label="Actions">
    <div class="dropdown">
      <button type="button"
              class="btn btn-outline-secondary btn-sm dropdown-toggle"
              data-bs-toggle="dropdown"
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
