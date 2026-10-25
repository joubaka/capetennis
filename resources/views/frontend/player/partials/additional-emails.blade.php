@foreach ([2, 3] as $emailNumber)
  <div class="col-md-6 mb-3">
    <label class="form-label" for="{{ $emailIdPrefix ?? 'player-contact' }}-email-{{ $emailNumber }}">Email {{ $emailNumber }} (optional)</label>
    <input type="email" maxlength="255" class="form-control @error('email_'.$emailNumber) is-invalid @enderror"
           id="{{ $emailIdPrefix ?? 'player-contact' }}-email-{{ $emailNumber }}" name="email_{{ $emailNumber }}"
           value="{{ old('email_'.$emailNumber, $contactPlayer?->{'email_'.$emailNumber}) }}">
    @error('email_'.$emailNumber)<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted">Receives player updates and announcements. Does not grant account access.</small>
  </div>
@endforeach
