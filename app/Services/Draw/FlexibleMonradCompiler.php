<?php

namespace App\Services\Draw;

use Illuminate\Validation\ValidationException;

/** Pure compiler: slot paths describe topology, never array or match-number order. */
final class FlexibleMonradCompiler
{
    private array $nodes = [];
    private array $positions = [];
    private array $losses = [];

    public function compile(array $draft): array
    {
        $this->nodes = $this->positions = $this->losses = [];
        $size = $draft['size'] ?? 32;
        if (! in_array($size, [4, 8, 16, 32, 64], true)) {
            $this->fail('Choose a bracket size of 4, 8, 16, 32 or 64.');
        }
        $depth = (int) log($size, 2);
        $slots = $draft['slots'] ?? [];
        $seen = [];
        foreach ($slots as $path => $source) {
            $path = (string) $path;
            if (! preg_match('/^[ab]{1,6}$/', $path) || strlen($path) > $depth) {
                $this->fail('Invalid starting position.');
            }
            if (! in_array($source['type'] ?? '', ['player', 'bye'], true)) {
                $this->fail('A starting position must contain a player or an explicit bye.');
            }
            for ($i = 1; $i < strlen($path); $i++) {
                if (isset($slots[substr($path, 0, $i)])) {
                    $this->fail('A direct entrant or bye conflicts with an earlier qualifying path.');
                }
            }
            if ($source['type'] === 'player') {
                $id = $source['id'] ?? null;
                if (! is_int($id) || $id < 1 || isset($seen[$id])) {
                    $this->fail('Each player must have one unique starting position.');
                }
                $seen[$id] = true;
            }
        }
        if (count($seen) < 2) {
            $this->fail('Place at least two players before generating.');
        }
        $winner = $this->main('', $depth, $slots);
        $this->positions[1] = $winner;
        ksort($this->losses);
        $place = 2;
        foreach ($this->losses as $sources) {
            $this->classify($sources, $place);
            $place += count($sources);
        }
        ksort($this->positions);

        return ['nodes' => $this->nodes, 'positions' => $this->positions, 'players' => array_keys($seen)];
    }

    private function main(string $path, int $depth, array $slots): array
    {
        if (isset($slots[$path])) {
            return $slots[$path];
        }
        if (strlen($path) === $depth) {
            $this->fail('Some starting slots are empty. Place a player or mark the unused path as a bye.');
        }
        $a = $this->main($path.'a', $depth, $slots);
        $b = $this->main($path.'b', $depth, $slots);
        if ($a['type'] === 'bye') return $b;
        if ($b['type'] === 'bye') return $a;
        $key = 'main_'.($path ?: 'final');
        $round = $depth - strlen($path);
        $this->nodes[$key] = ['sources' => [$a, $b], 'section' => 'Main draw',
            'round' => $round, 'label' => $this->roundLabel(strlen($path)), 'path' => $path];
        $this->losses[strlen($path)][] = ['type' => 'loser', 'match' => $key];
        return ['type' => 'winner', 'match' => $key];
    }

    /** Classify any number of sources, including non-powers of two, into unique positions. */
    private function classify(array $sources, int $start): void
    {
        $count = count($sources);
        if ($count === 1) {
            $this->positions[$start] = $sources[0];
            return;
        }
        $section = 'Positions '.$start.'–'.($start + $count - 1);
        $round = 1;
        $eliminated = [];
        // Seeded byes: first reduce to a power of two, then play full rounds.
        $power = 2 ** (int) floor(log($count, 2));
        $matches = $count === $power ? intdiv($count, 2) : $count - $power;
        while (count($sources) > 1) {
            $next = array_slice($sources, $matches * 2);
            $losers = [];
            for ($i = 0; $i < $matches; $i++) {
                $key = 'place_'.count($this->nodes);
                $this->nodes[$key] = ['sources' => [$sources[$i * 2], $sources[$i * 2 + 1]],
                    'section' => $section, 'round' => $round, 'label' => 'Round '.$round, 'path' => null];
                $next[] = ['type' => 'winner', 'match' => $key];
                $losers[] = ['type' => 'loser', 'match' => $key];
            }
            $eliminated[] = $losers;
            $sources = $next;
            $matches = intdiv(count($sources), 2);
            $round++;
        }
        $this->positions[$start] = $sources[0];
        $offset = $start + 1;
        foreach (array_reverse($eliminated) as $losers) {
            $this->classify($losers, $offset);
            $offset += count($losers);
        }
    }

    private function roundLabel(int $depth): string
    {
        return match ($depth) {
            0 => 'Final', 1 => 'Semifinals', 2 => 'Quarterfinals', default => 'Round of '.(2 ** ($depth + 1)),
        };
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['draft' => $message]);
    }
}
