@props(['score' => null, 'local' => false, 'title' => null, 'compact' => false])
@php($validScore = is_numeric($score) && is_finite((float) $score))
@if($compact)
<span {{ $attributes->class(['ctr-badge', 'ctr-badge--local' => $local])->merge(['style' => 'display:inline-flex;flex-direction:column;align-items:center;justify-content:center;position:relative;gap:.15rem;width:7rem;height:7rem;flex-shrink:0;box-sizing:border-box;padding:1rem;text-align:center;vertical-align:middle;border:0;border-radius:50%;background:'.($local ? '#fff8eb' : '#edf8f7').';color:'.($local ? '#754b0c' : '#095c60').'!important;font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums;', 'aria-label' => 'CTR '.($validScore ? number_format((float) $score, 1) : 'unavailable').($local && $validScore ? ' within-group only' : ''), 'title' => $title ?? ($local ? 'Provisional within-group score only; not comparable with other groups.' : 'Cape Tennis Rating: provisional singles ability, not official ranking points.')]) }} data-ctr-badge>
    {{-- Decorative stamp rings stay separate from the live, accessible rating. --}}
    <svg aria-hidden="true" focusable="false" viewBox="0 0 112 112" fill="none" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;color:inherit">
        <circle cx="56" cy="56" r="53" stroke="currentColor" stroke-width="2.4" stroke-dasharray="41 .8 22 .6 57 1.2" />
        <circle cx="56" cy="56" r="49.5" stroke="currentColor" stroke-width=".7" />
        <circle cx="56" cy="56" r="45" stroke="currentColor" stroke-width="2.6" stroke-dasharray=".9 4.99" />
        <circle cx="56" cy="56" r="40.5" stroke="currentColor" stroke-width=".8" />
    </svg>
    <svg aria-hidden="true" width="15" height="15" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg>
    <span style="font-size:.7rem;letter-spacing:.18em;padding-left:.18em;line-height:1">CTR</span>
    <span style="font-size:{{ $validScore ? ($local ? '1.2rem' : '1.35rem') : '.65rem' }};line-height:1.1">{{ $validScore ? number_format((float) $score, 1) : 'unavailable' }}</span>
    @if($validScore)<span style="font-size:.6rem;font-weight:500;line-height:1">/100</span>@endif
    @if($local && $validScore)<span style="font-size:.5rem;font-weight:600;line-height:1.1;white-space:nowrap">within-group only</span>@endif
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
