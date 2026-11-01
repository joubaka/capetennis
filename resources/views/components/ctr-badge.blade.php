@props(['score' => null, 'local' => false, 'title' => null, 'compact' => false, 'size' => 'standard', 'stage' => null])
@php
    $validScore = is_numeric($score) && is_finite((float) $score);
    $small = $size === 'small';
    $inline = $size === 'inline';
    // Stage is explicit playing-condition metadata, never inferred from age, score or category names.
    $ball = is_string($stage) ? strtolower(trim($stage)) : '';
    $stageLabels = ['red' => 'Red ball', 'orange' => 'Orange ball', 'green' => 'Green ball', 'yellow' => 'Yellow ball', 'standard' => 'Standard ball'];
    $stageKnown = isset($stageLabels[$ball]);
    $stampStage = in_array($ball, ['red', 'orange'], true) ? $ball : 'standard';
    [$ink, $paper, $ring] = match ($stampStage) {
        'red' => ['#c91822', '#fff0f0', '#ed2029'],
        'orange' => ['#b94a00', '#fff1df', '#ed7100'],
        default => ['#095c60', '#edf8f7', '#095c60'],
    };
    $stageLabel = $stageKnown ? $stageLabels[$ball] : 'Ball stage not recorded';
@endphp
<span {{ $attributes->class(['ctr-badge', 'ctr-badge--local' => $local])->merge(['style' => 'display:inline-flex;flex-direction:column;align-items:center;justify-content:center;position:relative;gap:'.($inline ? '0' : ($small ? '.08rem' : '.15rem')).';width:'.($inline ? '3rem' : ($small ? '4.5rem' : '7rem')).';height:'.($inline ? '3rem' : ($small ? '4.5rem' : '7rem')).';flex-shrink:0;box-sizing:border-box;padding:'.($inline ? '.4rem' : '.8rem').';text-align:center;vertical-align:middle;border:0;border-radius:50%;background:'.$paper.';color:'.$ink.'!important;font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums;', 'aria-label' => 'CTR '.($validScore ? number_format((float) $score, 1) : 'unavailable').($local && $validScore ? ' within-group only' : '').' · '.$stageLabel, 'title' => $title ?? ($local ? 'Provisional within-group score only; not comparable with other groups.' : 'Cape Tennis Rating: provisional singles ability, not official ranking points.')]) }} data-ctr-badge data-ctr-stage="{{ $stampStage }}" data-ctr-stage-known="{{ $stageKnown ? 'true' : 'false' }}">
    {{-- Decorative stamp rings stay separate from the live, accessible rating. --}}
    <svg aria-hidden="true" focusable="false" viewBox="0 0 112 112" fill="none" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;color:{{ $ring }}">
        <circle cx="56" cy="56" r="53" stroke="currentColor" stroke-width="2.4" stroke-dasharray="41 .8 22 .6 57 1.2" />
        <circle cx="56" cy="56" r="49.5" stroke="currentColor" stroke-width=".7" />
        <circle cx="56" cy="56" r="45" stroke="currentColor" stroke-width="2.6" stroke-dasharray=".9 4.99" />
        <circle cx="56" cy="56" r="40.5" stroke="currentColor" stroke-width=".8" />
    </svg>
    @unless($inline || $small)<svg aria-hidden="true" width="{{ $small ? 12 : 15 }}" height="{{ $small ? 12 : 15 }}" viewBox="0 0 32 32" fill="none" style="flex-shrink:0"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="2"/><path d="M6 7c10 1 13 5 13 10s3 7 7 8M6 25c1-8 5-12 10-12s8-3 10-6" stroke="currentColor" stroke-width="1.5"/></svg>@endunless
    <span style="font-size:{{ $inline ? '.45rem' : ($small ? '.6rem' : '.7rem') }};letter-spacing:.18em;padding-left:.18em;line-height:1">CTR</span>
    <span style="font-size:{{ $validScore ? ($inline ? '.75rem' : ($small ? '1.1rem' : ($local ? '1.2rem' : '1.35rem'))) : '.65rem' }};line-height:1.1">{{ $validScore ? number_format((float) $score, 1) : (($inline || $small) ? '—' : 'unavailable') }}</span>
    @if($validScore)<span style="font-size:{{ $inline ? '.375rem' : '.6rem' }};font-weight:500;line-height:1">/100</span>@endif
    @if($local && $validScore && !$inline && !$small)<span style="color:#754b0c;font-size:.5rem;font-weight:600;line-height:1.1;white-space:nowrap">within-group only</span>@endif
</span>
@if($stageKnown)<span class="small" style="color:{{ $ink }};font-size:.65rem;white-space:normal">{{ $stageLabel }}</span>@endif
@if($local && $validScore && ($inline || $small))<span class="small" style="color:#754b0c;font-size:.65rem;white-space:normal">within-group only</span>@endif
@if(!$validScore && ($inline || $small))<span class="small" style="font-size:.65rem;white-space:normal">unavailable</span>@endif
