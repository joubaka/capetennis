@props(['playerId', 'context' => null, 'svg' => false])
@if(!request()->attributes->get('public_schedule_preview') && \App\Services\Performance\PlayerRatingBadgeService::visible())
    @php($rating = app(\App\Services\Performance\PlayerRatingBadgeService::class)->forPlayer((int) $playerId, $context))
    @php($markerContext = ['draw' => $context instanceof \App\Models\Draw ? $context->id : 0,
        'field' => $context instanceof \App\Models\CategoryEvent ? $context->id : 0,
        'category' => $context instanceof \App\Models\Category ? $context->id : 0])
    @if($svg)
        <tspan data-rating-player="{{ $playerId }}" data-rating-svg="1" data-rating-draw="{{ $markerContext['draw'] }}" data-rating-field="{{ $markerContext['field'] }}" data-rating-category="{{ $markerContext['category'] }}">
    @else
        <span data-rating-player="{{ $playerId }}" data-rating-draw="{{ $markerContext['draw'] }}" data-rating-field="{{ $markerContext['field'] }}" data-rating-category="{{ $markerContext['category'] }}">
    @endif
    @if($rating)
        @if($svg)
            <tspan class="player-rating-badge" dx="4" style="font-size:10px;fill:#075985;font-weight:700"><title>{{ $rating['title'] }}</title>[{{ $rating['display_label'] }}]</tspan>
        @else
            <span class="player-rating-badge" style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:.25rem;vertical-align:middle">
                <x-ctr-badge size="inline" class="badge player-rating-badge ms-1" :score="$rating['score']" :title="$rating['title']" :local="($rating['rating_scope'] ?? null) !== 'all_age' || ($rating['comparable'] ?? true) === false" :stage="$rating['ball_type'] ?? null" />
                <span class="small" style="font-size:.65rem;font-weight:500;white-space:normal">{{ $rating['confidence_label'] ?? $rating['confidence_band'] ?? 'Low' }} confidence {{ !empty($rating['historical_connection']) ? '· old link' : '' }}</span>
            </span>
        @endif
    @endif
    @if($svg)</tspan>@else</span>@endif
@endif
