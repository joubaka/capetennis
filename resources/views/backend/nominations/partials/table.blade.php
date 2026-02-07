<tbody>
  @forelse($category->nominations as $k => $nomination)
    <tr data-nominationid="{{ $nomination->id }}">
      <td><strong>{{ $k + 1 }}</strong></td>
      <td>{{ $nomination->player->name }} {{ $nomination->player->surname }}</td>
      <td>{{ $nomination->player->email }}</td>
      <td><span class="badge bg-label-primary me-1">{{ $nomination->player->cellNr }}</span></td>
      <td>
        <span class="btn btn-sm btn-secondary sendEmail"
              data-bs-target="#createEmail"
              data-bs-toggle="modal"
              data-email="{{ $nomination->player->email }}"
              data-totype="one">
          <i class="ti ti-pencil me-1"></i>Email Player
        </span>
        <span class="btn btn-sm btn-danger nomination-remove"
              data-id="{{ $nomination->id }}"
              data-player="{{ $nomination->player->name }} {{ $nomination->player->surname }}"
              data-categoryeventid="{{ $category->id }}">
          <i class="ti ti-trash me-1"></i>Remove
        </span>
      </td>
    </tr>
  @empty
    <tr><td colspan="5" class="text-center text-muted">No nominations yet</td></tr>
  @endforelse
</tbody>
