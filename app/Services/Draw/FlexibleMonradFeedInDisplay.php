<?php

namespace App\Services\Draw;

/** Restore unplayed template paths for presentation only; never persist these cards. */
final class FlexibleMonradFeedInDisplay
{
    public static function expand(array $matches, array $draft): array
    {
        $size = (int) ($draft['size'] ?? 0);
        if (! in_array($size, [16, 32], true)) return $matches;
        $section = null;
        $main = [];
        foreach ($matches as $key => $match) {
            if (str_ends_with($match['section'], ' · Feed-in')) $section ??= $match['section'];
            if ($match['section'] === 'Main draw' && empty($match['displayOnly'])) $main[$match['path']] = $key;
        }
        if ($section === null) return $matches;
        $signature = static fn (array $sources): string => json_encode(array_map(static fn ($source) => [
            $source['type'], $source['match'] ?? null, $source['id'] ?? null,
        ], $sources));
        $real = [];
        foreach ($matches as $key => $match) {
            if ($match['section'] === $section) $real[$signature($match['sources'])] = [$key, $match];
        }
        $band = static function (int $depth) use ($main): array {
            $entries = [];
            for ($i = 0; $i < 2 ** $depth; $i++) {
                $path = strtr(str_pad(decbin($i), $depth, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
                $entries[] = ['source' => isset($main[$path]) ? ['type' => 'loser', 'match' => $main[$path]] : ['type' => 'bye']];
            }
            return $entries;
        };
        $player = static function (array $entry) use ($matches): ?int {
            $source = $entry['source'];
            if ($source['type'] === 'player') return (int) $source['id'];
            $match = $matches[$source['match'] ?? ''] ?? null;
            if (empty($match['winner'])) return null;
            if ($source['type'] === 'winner') return (int) $match['winner'];
            foreach ($match['players'] as $id) {
                if ($id && (int) $id !== (int) $match['winner']) return (int) $id;
            }
            return null;
        };
        $projected = $used = [];
        $round = 0;
        $failed = false;
        $step = static function (array $survivors, ?array $incoming = null) use (&$round, &$failed, &$projected, &$used, $section, $real, $signature, $player): array {
            $round++;
            $pairs = $incoming === null ? array_chunk($survivors, 2)
                : array_map(static fn ($entry, $survivor) => [$entry, $survivor], $incoming, $survivors);
            $next = [];
            foreach ($pairs as $index => $pair) {
                $sources = array_map(static function ($entry) {
                    $source = $entry['source'];
                    if (isset($entry['key'])) {
                        $source['original_match'] = $source['match'] ?? null;
                        $source['match'] = $entry['key'];
                    }
                    return $source;
                }, $pair);
                $actual = array_column($pair, 'source');
                if ($actual[0]['type'] !== 'bye' && $actual[1]['type'] !== 'bye') {
                    $existing = $real[$signature($actual)] ?? null;
                    if ($existing === null) {
                        $failed = true;
                        $next[] = ['source' => ['type' => 'bye']];
                        continue;
                    }
                    [$key, $match] = $existing;
                    $used[$key] = true;
                    $projected[$key] = array_replace($match, ['sources' => $sources, 'round' => $round]);
                    $next[] = ['key' => $key, 'source' => ['type' => 'winner', 'match' => $key]];
                    continue;
                }
                $key = 'display_feed_bye_'.$round.'_'.$index;
                $survivor = $actual[0]['type'] !== 'bye' ? $pair[0] : $pair[1];
                $projected[$key] = ['section' => $section, 'round' => $round, 'path' => null,
                    'label' => 'Feed-in round '.$round, 'sources' => $sources, 'players' => array_map($player, $pair),
                    'byes' => array_map(static fn ($source) => $source['type'] === 'bye', $actual),
                    'winner' => $player($survivor), 'sets' => [], 'displayOnly' => true];
                $next[] = ['key' => $key, 'source' => $survivor['source']];
            }
            return $next;
        };
        $depth = (int) log($size, 2) - 1;
        $survivors = $step($band($depth));
        $survivors = $step($survivors, array_reverse($band($depth - 1)));
        if ($size === 32) {
            $survivors = $step($survivors);
            $survivors = $step($survivors, $band(2));
        }
        while (count($survivors) > 1) $survivors = $step($survivors);
        if ($failed || count($used) !== count($real)) return $matches;
        $result = [];
        $inserted = false;
        foreach ($matches as $key => $match) {
            if ($match['section'] !== $section) $result[$key] = $match;
            elseif (! $inserted) {
                $result += $projected;
                $inserted = true;
            }
        }
        return $result;
    }
}
