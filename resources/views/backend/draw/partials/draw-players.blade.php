@php $registrations = $registrations ?? collect(); @endphp

<div class="d-flex justify-content-end mb-2">
    <button class="btn btn-danger btn-sm" id="clear-draw-players" data-draw-id="{{ $draw->id }}">
        Remove All Players
    </button>
</div>

<ul class="list-group">
    @forelse($registrations as $registration)

        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ $registration->players[0]->name }} {{ $registration->players[0]->surname }}</span>
            <button class="btn btn-outline-danger btn-sm remove-player"
                    data-reg-id="{{ $registration->id }}"
                    data-draw-id="{{ $draw->id }}">
                Remove
            </button>
        </li>
    @empty
        <li class="list-group-item text-muted">No players added yet.</li>
    @endforelse
</ul>


