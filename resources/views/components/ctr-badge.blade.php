@props(['score' => null, 'local' => false, 'title' => null, 'compact' => false])
@php($validScore = is_numeric($score) && is_finite((float) $score))
@if($compact)
<span {{ $attributes->class(['ctr-badge', 'ctr-badge--local' => $local])->merge(['style' => 'display:inline-flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.35rem;padding:.4rem .65rem;border:1px solid '.($local ? '#e8bd76' : '#82bfc3').';border-radius:.5rem;background:'.($local ? '#fff4df' : '#e4f4f3').';color:'.($local ? '#754b0c' : '#095c60').'!important;font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums;max-width:100%;', 'aria-label' => 'CTR '.($validScore ? number_format((float) $score, 1) : 'unavailable').($local && $validScore ? ' within-group only' : ''), 'title' => $title ?? ($local ? 'Provisional within-group score only; not comparable with other groups.' : 'Cape Tennis Rating: provisional singles ability, not official ranking points.')]) }} data-ctr-badge>
    <svg aria-hidden="true" width="15" height="15" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg>
    <span>CTR</span>
    <span>{{ $validScore ? number_format((float) $score, 1).'/100' : 'unavailable' }}</span>
    @if($local && $validScore)<span style="font-size:.68em;font-weight:600">within-group only</span>@endif
</span>
@else
<span {{ $attributes->class(['ctr-rating-badge']) }} role="img" aria-label="Cape Tennis Rating: {{ !$validScore ? 'unrated' : number_format($score, 1).' out of 100' }}. {{ $local ? 'Within-group only.' : 'Provisional ability estimate.' }}" style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:.6rem;padding:.7rem .8rem;border-radius:14px;background:#233d50;color:#fff;max-width:100%">
  <span aria-hidden="true" style="display:flex;align-items:center;gap:.45rem;color:#c9e6d6">
    <svg width="24" height="24" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg>
    <strong style="font-size:1rem;letter-spacing:.06em">CTR</strong>
  </span>
  <span aria-hidden="true" style="display:flex;flex-direction:column;line-height:1.2">
    <span style="font-size:.68rem;color:#c9d8df">Cape Tennis Rating</span>
    <strong style="font-size:1.9rem;color:#fff;white-space:nowrap">{{ !$validScore ? '—' : number_format($score, 1) }} <small style="font-size:.7rem;font-weight:400;color:#c9d8df">/ 100</small></strong>
    @if($local && $validScore)<span style="font-size:.75rem;color:#ffe3ac">Within-group only</span>@endif
  </span>
</span>
@endif
