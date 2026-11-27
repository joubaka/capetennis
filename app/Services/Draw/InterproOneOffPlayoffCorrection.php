<?php

namespace App\Services\Draw;

use App\Models\{Draw, Fixture};

/** Read-only persistence for the reviewed one-off draw 1654 correction; no mutation entry point. */
final class InterproOneOffPlayoffCorrection
{
    public static function rootSource(Draw $draw, Fixture $fixture, int $slot): int
    {
        $definition = InterproRoundRobinFormat::template($draw)[(int) $fixture->match_nr] ?? null;
        $route = $definition[2][$slot - 1] ?? '';
        abort_unless(InterproRoundRobinFormat::selected($draw) && $definition && $fixture->stage === $definition[0]
            && (int) $fixture->draw_id === (int) $draw->id && preg_match('/^([A-D])([1-3])$/', $route, $match),
            409, 'Choose an opening Interpro playoff place qualified from a group.');
        $group = $draw->groups()->where('name', $match[1])->firstOrFail();
        abort_unless((int) $fixture->getAttribute("registration{$slot}_source_group_id") === (int) $group->id
            && (int) $fixture->getAttribute("registration{$slot}_source_position") === (int) $match[2], 409, 'The qualifying source changed. Review the playoff place.');
        $fixtures = $draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $group->id)->with('fixtureResults')->get();
        $rows = app(\App\Domain\Draws\Services\StandingsService::class)->forGroup($group, $fixtures);
        $service = app(TwoStageRoundRobinService::class);
        abort_unless($service->complete($draw, $group, $fixtures) && $service->positionsResolved($draw, array_values($rows), $fixtures),
            409, 'Complete and confirm decisive group standings before swapping playoff places.');
        $source = (int) ($rows[(int) $match[2] - 1]['reg_id'] ?? 0);
        abort_unless($source > 0, 409, 'The qualifying player is unavailable.');
        return $source;
    }

    public static function sourceOverride(Draw $draw, Fixture $fixture, int $slot, ?int $source): ?int
    {
        $key = $fixture->id.':'.$slot;
        $records = $draw->team_draw_selection['interpro_one_off_1654_places'] ?? [];
        $record = $records[$key] ?? null;
        if (!$record) return $source;
        $expectedPeer = match ((int) $fixture->match_nr) { 3002 => 3004, 3004 => 3002, default => 0 };
        abort_unless((int) $draw->id === 1654 && (int) $draw->event_id === 253 && $slot === 2 && $expectedPeer,
            409, 'This stored one-off playoff correction does not belong to this draw or place.');
        abort_unless($source > 0 && is_string($record['counterpart'] ?? null)
            && preg_match('/^\d+:[12]$/', $record['counterpart'])
            && is_string($record['request_key'] ?? null) && strlen($record['request_key']) === 64,
            409, 'The paired playoff position change is invalid.');
        $peer = $records[$record['counterpart'] ?? ''] ?? null;
        abort_unless($peer && ($peer['counterpart'] ?? null) === $key
            && ($peer['request_key'] ?? null) === ($record['request_key'] ?? null)
            && (int) ($record['base_registration_id'] ?? 0) === (int) $source
            && (int) ($peer['base_registration_id'] ?? 0) === (int) ($record['incoming_registration_id'] ?? 0)
            && (int) ($peer['incoming_registration_id'] ?? 0) === (int) $source,
            409, 'The paired playoff position change is invalid. Review both original places.');
        [$peerId, $peerSlot] = array_map('intval', explode(':', $record['counterpart']));
        $peerFixture = $draw->drawFixtures()->findOrFail($peerId);
        abort_unless($peerSlot === 2 && (int) $peerFixture->match_nr === $expectedPeer
            && isset($draw->team_draw_selection['playoff_lucky_losers'][
                ((int) $fixture->match_nr === 3004 ? $fixture->id : $peerFixture->id).':2']),
            409, 'The original lucky-loser place for this one-off correction is unavailable.');
        $original = self::rootSource($draw, $fixture, $slot);
        $peerOriginal = self::rootSource($draw, $peerFixture, $peerSlot);
        abort_unless((int) ($record['original_source_registration_id'] ?? 0) === $original
            && (int) ($peer['original_source_registration_id'] ?? 0) === $peerOriginal
            && (int) PlayoffLuckyLoserService::luckyLoserSourceOverride($draw, $peerFixture, $peerSlot, $peerOriginal)
                === (int) $peer['base_registration_id'], 409, 'A swapped playoff source changed. Review the original qualification and lucky loser.');
        return (int) $record['incoming_registration_id'];
    }

}
