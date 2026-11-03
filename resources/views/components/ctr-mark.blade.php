@props(['size' => 'small'])
{{-- A decorative identity mark, never a player score or an unavailable rating. --}}
@php($dimension = $size === 'heading' ? '3rem' : '1.8rem')
<svg {{ $attributes->class(['ctr-mark'])->merge(['style' => 'display:inline-block;width:'.$dimension.';height:'.$dimension.';flex-shrink:0;vertical-align:middle;']) }} viewBox="0 0 112 112" fill="none" aria-hidden="true" focusable="false" data-ctr-mark>
  <circle cx="56" cy="56" r="55" fill="#f1f5f9" />
  <g stroke="#64748b">
    <circle cx="56" cy="56" r="53" stroke-width="2.4" stroke-dasharray="41 .8 22 .6 57 1.2" />
    <circle cx="56" cy="56" r="49.5" stroke-width=".7" />
    <circle cx="56" cy="56" r="45" stroke-width="2.6" stroke-dasharray=".9 4.99" />
    <circle cx="56" cy="56" r="40.5" stroke-width=".8" />
  </g>
  <text x="56" y="65" text-anchor="middle" fill="#475569" style="font:700 27px Arial,sans-serif;letter-spacing:1px">CTR</text>
</svg>
