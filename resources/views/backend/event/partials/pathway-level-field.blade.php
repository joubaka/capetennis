@php($pathwayOptions = \App\Services\Performance\EventPathwayClassification::HOSTED_LEVELS)
<div class="mb-3">
  <label for="{{ $pathwayFieldId ?? 'event-pathway-level' }}" class="form-label">{{ $pathwayFieldLabel ?? 'Default tournament level' }}</label>
  <select id="{{ $pathwayFieldId ?? 'event-pathway-level' }}" name="pathway_level" class="form-select" aria-describedby="{{ $pathwayFieldId ?? 'event-pathway-level' }}-help">
    <option value="">{{ $pathwayBlankLabel ?? 'Not classified — choose levels per category' }}</option>
    @foreach($pathwayOptions as $code => $label)
      <option value="{{ $code }}" @selected(old('pathway_level', $pathwayValue ?? null) === $code)>{{ $label }}</option>
    @endforeach
  </select>
  <p class="form-text" id="{{ $pathwayFieldId ?? 'event-pathway-level' }}-help">{{ $pathwayHelp ?? 'Applies to categories using the event default. Override individual categories for mixed-level events. This guides discovery and does not change match rating evidence.' }}</p>
  @error('pathway_level')<p class="text-danger small" role="alert">{{ $message }}</p>@enderror
</div>
