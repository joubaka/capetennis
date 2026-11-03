@foreach($eventCats as $value)
  @php($placesRemaining = app(\App\Services\DivisionCapacityService::class)->remaining($value))
  <option value="{{ $value->id }}"
          data-price="{{ $value->entry_fee }}"
          data-name="{{ $value->category->name }}"
          @disabled($placesRemaining === 0)>
    {{ $value->category->name }}@if($placesRemaining !== null) — {{ $placesRemaining === 0 ? 'Full' : $placesRemaining.' '.\Illuminate\Support\Str::plural('spot', $placesRemaining).' left' }}@endif
  </option>
@endforeach
