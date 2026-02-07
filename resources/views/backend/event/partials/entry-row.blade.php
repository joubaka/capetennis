@php
  $player = optional($reg->registration?->players)->first();
@endphp

<tr data-row>
  <td>—</td>
  <td>{{ $player?->name }} {{ $player?->surname }}</td>

  <td>
    <span class="badge bg-success">Active</span>
  </td>

  <td>
    <span class="badge bg-warning">Unpaid</span>
  </td>

  <td class="text-end">
    <button class="btn btn-sm btn-outline-danger remove-player-btn"
            data-url="{{ route('admin.category.removePlayer', [$reg->category_event_id, $reg->registration_id]) }}">
      Remove
    </button>
  </td>
</tr>
