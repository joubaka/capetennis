<?php

namespace App\Services\Pilot;

use App\Models\Draw;
use App\Models\DrawSetting;

/**
 * PilotEligibility
 *
 * Determines whether a draw/event is eligible for the limited public
 * canonical RR pilot.
 *
 * ALLOWED:
 *   - Small RR draws (≤ MAX_PLAYER_COUNT players)
 *   - Pure round-robin structure (supports_boxes = true, no feed-in)
 *   - No consolation sub-draw on the same category_event
 *   - Non-national events
 *
 * BLOCKED:
 *   - Feed-in draws
 *   - Large playoffs
 *   - National-level events
 *   - High-volume scheduling events (> MAX_DRAW_COUNT draws in event)
 *   - Interpro draws
 */
class PilotEligibility
{
    public const MAX_PLAYER_COUNT  = 32;
    public const MAX_DRAWS_PER_EVENT = 6;

    // Event name patterns that flag national/interpro tournaments
    private const BLOCKED_PATTERNS = [
        'interpro', 'national', 'sa open', 'sa closed',
        'provincial', 'u18 national', 'u16 national',
    ];

    /**
     * Full eligibility check with reasons.
     *
     * @return array{eligible: bool, reasons: string[], warnings: string[]}
     */
    public static function check(Draw $draw): array
    {
        $reasons  = [];
        $warnings = [];

        // ── 1. Must be a round-robin draw (has at least 1 box/group)
        $settings = $draw->settings;
        if (! $settings || ! ($settings->boxes >= 1)) {
            $reasons[] = 'Draw is not a round-robin format (no boxes/groups configured).';
        }

        // ── 2. No feed-in structure
        if ($settings && ($settings->playoff_size ?? 0) > 0) {
            $reasons[] = 'Draw has a feed-in playoff structure — not eligible for canonical RR pilot.';
        }

        // ── 3. Player count (check draw_registrations first, fall back to group registrations)
        $playerCount = $draw->registrations()->count();
        if ($playerCount === 0) {
            // Players may only be in groups (draw_group_registrations) without draw_registrations
            $playerCount = \DB::table('draw_group_registrations')
                ->join('draw_groups', 'draw_groups.id', '=', 'draw_group_registrations.draw_group_id')
                ->where('draw_groups.draw_id', $draw->id)
                ->distinct('draw_group_registrations.registration_id')
                ->count('draw_group_registrations.registration_id');
        }
        if ($playerCount > self::MAX_PLAYER_COUNT) {
            $reasons[] = "Draw has {$playerCount} players, exceeding limit of " . self::MAX_PLAYER_COUNT . ".";
        }
        if ($playerCount === 0) {
            $warnings[] = 'Draw has no registered players yet.';
        }

        // ── 4. No consolation sibling draw on the same category_event
        if ($draw->category_event_id) {
            $siblingConsolation = Draw::where('category_event_id', $draw->category_event_id)
                ->where('id', '!=', $draw->id)
                ->get()
                ->filter(fn($d) => $d->getStructureType() === 'knockout')
                ->count();
            if ($siblingConsolation > 0) {
                $reasons[] = 'Category has a consolation/knockout sibling draw — RR pilot blocked for complex structures.';
            }
        }

        // ── 5. Event-level checks
        $event = $draw->relationLoaded('event') ? $draw->event : $draw->event()->first();

        if ($event) {
            // National / interpro keyword check
            $nameLower = strtolower($event->name ?? '');
            foreach (self::BLOCKED_PATTERNS as $pattern) {
                if (str_contains($nameLower, $pattern)) {
                    $reasons[] = "Event name contains blocked keyword '{$pattern}' (national/interpro).";
                    break;
                }
            }

            // Too many draws in event (high-volume scheduling events)
            $drawCount = Draw::where('event_id', $event->id)->count();
            if ($drawCount > self::MAX_DRAWS_PER_EVENT) {
                $warnings[] = "Event has {$drawCount} draws — high-volume event, review carefully before enabling.";
            }

            // Event must not already be published in a way that locks draws
            if ($event->published && $draw->published) {
                $warnings[] = 'Draw is already published — enabling canonical mode on a published draw requires extra caution.';
            }
        }

        // ── 6. No unresolved HIGH/MEDIUM mismatches
        if (! $draw->canonicalAllowed()) {
            $reasons[] = 'Draw has unresolved HIGH or MEDIUM engine mismatches — resolve before enabling canonical mode.';
        }

        return [
            'eligible' => empty($reasons),
            'reasons'  => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * Quick boolean check (for guards).
     */
    public static function eligible(Draw $draw): bool
    {
        return self::check($draw)['eligible'];
    }
}
