@props(['score' => null])
<span {{ $attributes->class(['ctr-rating-badge']) }} role="img" aria-label="Cape Tennis Rating: {{ $score === null ? 'unrated' : number_format($score, 1).' out of 100' }}. Provisional ability estimate." style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:.6rem;padding:.7rem .8rem;border-radius:14px;background:#233d50;color:#fff;max-width:100%">
  <span aria-hidden="true" style="display:flex;align-items:center;gap:.45rem;color:#c9e6d6">
    <svg width="24" height="24" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg>
    <strong style="font-size:1rem;letter-spacing:.06em">CTR</strong>
  </span>
  <span aria-hidden="true" style="display:flex;flex-direction:column;line-height:1.2">
    <span style="font-size:.68rem;color:#c9d8df">Cape Tennis Rating</span>
    <strong style="font-size:1.9rem;color:#fff;white-space:nowrap">{{ $score === null ? '—' : number_format($score, 1) }} <small style="font-size:.7rem;font-weight:400;color:#c9d8df">/ 100</small></strong>
  </span>
</span>
