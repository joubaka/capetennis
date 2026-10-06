@props(['playerId', 'context' => null, 'svg' => false])
@if(\App\Services\Performance\PlayerRatingBadgeService::visible())
    @php($rating = app(\App\Services\Performance\PlayerRatingBadgeService::class)->forPlayer((int) $playerId, $context))
    @if($rating)
        @if($svg)
            <tspan class="player-rating-badge" dx="4" style="font-size:10px;fill:#075985;font-weight:700"><title>{{ $rating['title'] }}</title>[{{ $rating['display_label'] }}]</tspan>
        @else
            <span class="badge player-rating-badge ms-1" style="font-size:.68rem;vertical-align:middle;background:#e0f2fe;color:#075985;padding:.2em .4em;border:1px solid #7dd3fc;white-space:nowrap" title="{{ $rating['title'] }}" aria-label="{{ $rating['title'] }}">{{ $rating['display_label'] }}</span>
        @endif
    @endif
@endif
