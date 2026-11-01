@if(\App\Services\Performance\PlayerRatingBadgeService::visible())
<style>@media print { .player-rating-badge { display:none !important; } }</style>
@php
    // Use the same component for initial and refreshed badges; only escaped data is substituted in JS.
    $stampTemplates = [];
    foreach (['unknown' => null, 'red' => 'red', 'orange' => 'orange', 'green' => 'green', 'yellow' => 'yellow', 'standard' => 'standard'] as $stageKey => $ballStage) {
        foreach ([false, true] as $localStamp) {
            $stampTemplates[$stageKey][$localStamp ? 'local' : 'shared'] = str_replace('98.7', '__CTR_SCORE__',
                \Illuminate\Support\Facades\Blade::render('<x-ctr-badge size="inline" class="badge player-rating-badge ms-1" :score="98.7" :local="$local" :stage="$stage" title="__CTR_TITLE__" />', ['local' => $localStamp, 'stage' => $ballStage]));
        }
    }
@endphp
<script>
window.CTPlayerRatingConfig = {
  endpoint: @json(route('backend.player-performance.badges')),
  drawId: @json(isset($draw) && $draw instanceof \App\Models\Draw ? $draw->id : null),
  stampTemplates: @json($stampTemplates)
};
</script>
<script src="{{ asset('assets/js/player-rating-badges.js') }}?v={{ filemtime(public_path('assets/js/player-rating-badges.js')) }}"></script>
@endif
