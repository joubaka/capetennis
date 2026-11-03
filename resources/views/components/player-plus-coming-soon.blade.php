@if(app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon())
  <span {{ $attributes->class(['badge rounded-pill']) }} style="background:#ff9f43;color:#172e45;font-size:1rem;padding:.65rem 1rem;white-space:normal" data-player-plus-coming-soon>Coming Soon</span>
@endif
