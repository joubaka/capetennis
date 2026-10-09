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
    $outcomeClasses = isset($fixture) ? \App\Support\ResultPresentation::classes($fixture) : ['', ''];
    if (isset($fixture) && isset($slot)) {
        $isWinner = ($outcomeClasses[(int) $slot - 1] ?? '') === 'winner-home';
    }
    $isLoser = isset($slot) && ($outcomeClasses[(int) $slot - 1] ?? '') === 'loser-home';
    $maxWidth = (float) ($maxWidth ?? 176);
    $textX = (float) $x;
    $baselineY = (float) $y;
    $display = $isBye ? 'BYE' : ($isPlaceholder ? '---' : $label);
    $badgeCount = 0;
    if (!$isBye && !$isPlaceholder && $registration && \App\Services\Performance\PlayerRatingBadgeService::visible()) {
        foreach ($registration->players as $ratedPlayer) {
            if (app(\App\Services\Performance\PlayerRatingBadgeService::class)->forPlayer($ratedPlayer->id, $draw ?? ($fixture ?? null)?->draw)) { $badgeCount++; }
        }
    }
    $badgeWidth = $badgeCount * 75;
    $badgeDisplay = $badgeCount ? \Illuminate\Support\Str::limit($display, max(5, (int) floor(($maxWidth - $badgeWidth - 14) / 6.8))) : $display;
    $estimatedWidth = min($maxWidth, max(36, (mb_strlen($badgeDisplay) * 6.8) + 14 + $badgeWidth));
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
        class="player-identity-bg {{ $isWinner ? 'winner' : ($isLoser ? 'loser' : '') }}"
    />
    <text x="{{ $textX }}" y="{{ $baselineY }}" @if($badgeCount) textLength="{{ max(30, $maxWidth - 14) }}" lengthAdjust="spacingAndGlyphs" @endif class="player-name player-identity-text {{ $isWinner ? 'winner' : ($isLoser ? 'loser' : '') }}"><title>{{ $display }}</title>{{ $badgeDisplay }}@if($registration)@foreach($registration->players as $ratedPlayer)<x-player-rating :player-id="$ratedPlayer->id" :context="$draw ?? ($fixture ?? null)?->draw ?? null" :svg="true" />@endforeach@endif</text>
@endif
