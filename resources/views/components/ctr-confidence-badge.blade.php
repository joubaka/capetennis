@props(['rating' => []])
@php
    $index = $rating['confidence_index'] ?? null;
    $valid = is_numeric($index) && is_finite((float) $index) && $index >= 0 && $index <= 100;
    $label = $rating['confidence_label'] ?? ($valid ? ($index < 40 ? 'Low' : ($index < 70 ? 'Medium' : 'High')) : 'Not recorded');
    $colours = match ($label) {
        'Low' => ['#fff0ee', '#8c2820', '#e6aaa3'],
        'Medium' => ['#fff4d6', '#735000', '#d7b765'],
        'High' => ['#e4f4eb', '#1d6240', '#8dbda2'],
        default => ['#eef1f4', '#455568', '#b5c0ca'],
    };
    $explanation = 'Evidence strength and freshness, not rating accuracy or chance of winning. '.($rating['confidence_explanation'] ?? '');
@endphp
<span {{ $attributes->class(['ctr-confidence-badge'])->merge(['title' => trim($explanation), 'aria-label' => 'Evidence confidence: '.($valid ? (int) round($index).'%. ' : '').$label.'. '.trim($explanation)]) }} data-ctr-confidence style="display:inline-block;max-width:100%;padding:.3rem .5rem;border:1px solid {{ $colours[2] }};border-radius:.4rem;background:{{ $colours[0] }};color:{{ $colours[1] }};font-weight:700;line-height:1.35;overflow-wrap:anywhere">{{ $valid ? (int) round($index).'%' : $label }} evidence confidence</span>
