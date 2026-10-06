@if(\App\Services\Performance\PlayerRatingBadgeService::visible())
<style>@media print { .player-rating-badge { display:none !important; } }</style>
<script>
window.CTPlayerRatingConfig = {
  endpoint: @json(route('backend.player-performance.badges')),
  drawId: @json(isset($draw) && $draw instanceof \App\Models\Draw ? $draw->id : null)
};
</script>
<script src="{{ asset('assets/js/player-rating-badges.js') }}?v={{ filemtime(public_path('assets/js/player-rating-badges.js')) }}"></script>
@endif
