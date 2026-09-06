@php
    $fixtureRegistration = null;
    if (isset($fixture) && isset($slot)) {
        $fixtureRegistration = (int) $slot === 1 ? $fixture?->registration1 : $fixture?->registration2;
    }
    $registration = $registration ?? $fixtureRegistration;
    $opponent = null;
    if (isset($fixture) && isset($slot)) {
        $opponent = (int) $slot === 1 ? $fixture?->registration2 : $fixture?->registration1;
    }
    $resolvedName = $registration?->players?->pluck('full_name')->join(' / ');
    $label = trim((string) ($name ?? $resolvedName ?? ''));
    if ($label === '' && $opponent) {
        $label = 'BYE';
    }
    $isBye = (bool) ($isBye ?? false) || strtoupper($label) === 'BYE';
    $isPlaceholder = $label === '' || $label === '---';
    $isWinner = (bool) ($isWinner ?? (
        isset($fixture) && $registration
            && (int) ($fixture?->winner_registration ?? 0) === (int) $registration->id
    ));
    $maxWidth = (float) ($maxWidth ?? 176);
    $textX = (float) $x;
    $baselineY = (float) $y;
    $display = $isBye ? 'BYE' : ($isPlaceholder ? '---' : $label);
    $estimatedWidth = min($maxWidth, max(36, (mb_strlen($display) * 6.8) + 14));
@endphp

@if($isBye || $isPlaceholder)
    <text x="{{ $textX }}" y="{{ $baselineY }}" class="player-name {{ $isBye ? 'bye' : '' }}">{{ $display }}</text>
@else
    <rect
        x="{{ $textX - 5 }}"
        y="{{ $baselineY - 14 }}"
        width="{{ $estimatedWidth }}"
        height="17"
        rx="5"
        class="player-identity-bg {{ $isWinner ? 'winner' : '' }}"
    />
    <text x="{{ $textX }}" y="{{ $baselineY }}" class="player-name player-identity-text {{ $isWinner ? 'winner' : '' }}">{{ $display }}</text>
@endif
