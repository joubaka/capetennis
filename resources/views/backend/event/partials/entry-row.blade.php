@php
  $player = optional($reg->registration?->players)->first();
@endphp

<tr data-row data-entry-id="{{ $reg->id }}">
  <td>—</td>
  <td>{{ $player?->name }} {{ $player?->surname }}</td>

  <td>
    <span class="badge bg-success">Active</span>
  </td>

  <td>
    <span class="badge bg-success">Paid</span>
    <br><span class="badge bg-info text-dark mt-1" style="font-size:.65rem;">Admin Added</span>
  </td>

  <td class="text-end">
    <button class="btn btn-sm btn-outline-danger remove-player-btn"
            data-url="{{ route('admin.category.removePlayer', [$reg->category_event_id, $reg->registration_id]) }}">
      Remove
    </button>
  </td>
</tr>
