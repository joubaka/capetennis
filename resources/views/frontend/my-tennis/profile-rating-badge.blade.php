@php
  $badgePoints = $badgeSnapshot['points'] ?? [];
  $badgeRating = $badgePoints ? $badgePoints[array_key_last($badgePoints)] : null;
@endphp
<a class="ct-my-tennis-rating-badge" href="{{ $badgeUrl }}" aria-label="Open player rating" data-profile-rating-badge data-my-tennis-link>
  @if($badgeRating)
    <x-ctr-badge size="inline" :stage="$badgeSnapshot['stage']" :overall="empty($badgeSnapshot['stage'])"
      :score="$badgeRating['score']" :local="($badgeRating['connection_status'] ?? null) === 'disconnected'" />
    <span>
      @if($badgeSnapshot['stage'] === 'standard')<strong>{{ $badgeSnapshot['label'] }}</strong><br>@endif
      <small>Saved <time datetime="{{ $badgeRating['date'] }}">{{ \Illuminate\Support\Carbon::parse($badgeRating['date'])->format('j M Y') }}</time></small>
    </span>
  @else
    <x-ctr-mark size="heading" />
    <span>{{ $badgeSnapshot ? 'Rating not available yet' : 'Player rating' }}</span>
  @endif
</a>
