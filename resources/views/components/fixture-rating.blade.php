@props(['fixtureId', 'side', 'drawId' => null])
@if(\App\Services\Performance\PlayerRatingBadgeService::visible())
    <span data-rating-fixture="{{ (int) $fixtureId }}" data-rating-side="{{ (int) $side }}" data-rating-draw="{{ (int) $drawId }}"></span>
@endif
