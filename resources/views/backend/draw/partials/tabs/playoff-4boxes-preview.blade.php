<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Seeded Draws by Position</title>
    @php
        // Prepare boxes and final positions
        $boxes = $draw->registrations
            ->filter(fn($r) => $r->pivot->box_number)
            ->groupBy(fn($r) => $r->pivot->box_number);

        $fixtureMap = $draw->drawFixtures->groupBy('draw_group_id');
        $finalPositions = [];

        foreach ($boxes as $boxNumber => $registrations) {
            $boxFixtures = $fixtureMap[$boxNumber] ?? collect();
            $stats = [];

            foreach ($registrations as $reg) {
                $rid = $reg->id;
                $wins = 0;
                $games = 0;

                foreach ($boxFixtures as $f) {
                    foreach ($f->fixtureResults as $r) {
                        if ($r->winner_registration === $rid) {
                            $wins++;
                        }
                        if ($f->registration1_id === $rid) {
                            $games += $r->registration1_score;
                        } elseif ($f->registration2_id === $rid) {
                            $games += $r->registration2_score;
                        }
                    }
                }

                $stats[$rid] = ['wins' => $wins, 'games' => $games];
            }

            $rankings = collect($stats)
                ->map(fn($s, $rid) => ['rid' => $rid] + $s)
                ->sort(function ($a, $b) use ($boxFixtures) {
                    if ($a['wins'] !== $b['wins']) {
                        return $b['wins'] <=> $a['wins'];
                    }
                    if ($a['games'] !== $b['games']) {
                        return $b['games'] <=> $a['games'];
                    }

                    $fixture = $boxFixtures->first(
                        fn($f) => ($f->registration1_id === $a['rid'] && $f->registration2_id === $b['rid']) ||
                            ($f->registration1_id === $b['rid'] && $f->registration2_id === $a['rid']),
                    );

                    if ($fixture && $fixture->fixtureResults->first()) {
                        return $fixture->fixtureResults->first()->winner_registration === $a['rid'] ? -1 : 1;
                    }

                    return 0;
                })
                ->values()
                ->pluck('rid')
                ->flip();

            foreach ($registrations as $reg) {
                $rankIndex = $rankings[$reg->id] ?? null;
                $position = $rankIndex !== null ? $rankIndex + 1 : null;

                $finalPositions[$reg->id] = [
                    'box' => $boxNumber,
                    'player' => $reg->players->first()?->full_name ?? 'TBD',
                    'position' => $position,
                ];
            }
        }

        // Map seeding code (A1, A2, B1, B2...) to real player names
        $codeToName = [];
        $boxLetters = range('A', 'Z');
        foreach ($boxes as $boxNumber => $registrations) {
            $positioned = collect($registrations)
                ->sortBy(fn($r) => $finalPositions[$r->id]['position'] ?? 999)
                ->values();

            foreach ($positioned as $i => $reg) {
                $code = $boxLetters[$boxNumber - 1] . ($i + 1); // A1, A2...
                $codeToName[$code] = $reg->players->first()?->full_name ?? 'TBD';
            }
        }
    @endphp
    <script>

        const seededPlayers = @json($codeToName);
    </script>
</head>

<body>



    <div id="draw-container"></div>

    <script>
        const numberOfDraws = Math.ceil(Object.keys(seededPlayers).length / 8);


        const basePattern = ['A', 'B', 'C', 'D', 'C', 'D', 'A', 'B'];
        const positionPattern = [1, 2, 2, 1, 1, 2, 2, 1];

        function getDrawSet(drawIndex) {
            const offset = drawIndex * 2;
            return basePattern.map((letter, i) => {
                const code = `${letter}${positionPattern[i] + offset}`;
                const name = seededPlayers[code];
                return name ? name : 'Bye';
            });
        }


        function generateMatch(x, y, p1, p2, height = 60) {
            return `
        <g>
          <line x1="${x}" y1="${y+20}" x2="${x+220}" y2="${y+20}" stroke="black" stroke-width="1"></line>
          <line x1="${x}" y1="${y+height}" x2="${x+220}" y2="${y+height}" stroke="black" stroke-width="1"></line>
          <line x1="${x+220}" y1="${y+20}" x2="${x+220}" y2="${y+height}" stroke="black" stroke-width="1"></line>
          <text x="${x+10}" y="${y+15}" font-family="Helvetica" font-weight="bold">${p1}</text>
          <text x="${x+10}" y="${y+height - 5}" font-family="Helvetica" font-weight="bold">${p2}</text>
        </g>
        `;
        }

        function buildSingleDraw(players, yOffset = 0, drawNumber = 1) {
            let svg = '';
            const qHeight = 90,
                sHeight = 120,
                fHeight = 240;
            const conHeight = 90,
                conFinalHeight = conHeight * 2;

            const quarterY = [40, 160, 280, 400].map(y => y + yOffset);
            const semiY = [
                (quarterY[0] + qHeight / 2 + quarterY[1] + qHeight / 2) / 2 - sHeight / 2,
                (quarterY[2] + qHeight / 2 + quarterY[3] + qHeight / 2) / 2 - sHeight / 2
            ];
            const finalY = (semiY[0] + sHeight / 2 + semiY[1] + sHeight / 2) / 2 - fHeight / 2;

            const startNum = (drawNumber - 1) * 8 + 1;
            const endNum = startNum + players.length - 1;
            svg += `<text x="10" y="${yOffset + 20}" font-size="18" font-weight="bold">Draw ${startNum}–${endNum}</text>`;

            for (let i = 0; i < 4; i++) {
                const p1 = players[i * 2] || 'TBD';
                const p2 = players[i * 2 + 1] || 'TBD';
                svg += generateMatch(10, quarterY[i], p1, p2, qHeight);
            }

            svg += generateMatch(230, semiY[0], 'W QF1', 'W QF2', sHeight);
            svg += generateMatch(230, semiY[1], 'W QF3', 'W QF4', sHeight);
            const finalX = 230 + 220;
            svg += generateMatch(finalX, finalY, 'W SF1', 'W SF2', fHeight);
            svg +=
                `<line x1="${finalX + 220}" y1="${finalY + fHeight/2}" x2="${finalX + 320}" y2="${finalY + fHeight/2}" stroke="black" stroke-width="2" />`;

            const consolationOffsetY = yOffset + 550;
            const conSemiY = [consolationOffsetY, consolationOffsetY + 150];
            const conFinalY = (conSemiY[0] + conHeight / 2 + conSemiY[1] + conHeight / 2) / 2 - conFinalHeight / 2;
            const conFinalX = 230;
            const con78Y = conFinalY + conFinalHeight + 40;

            svg += generateMatch(10, conSemiY[0], 'L QF1', 'L QF2', conHeight);
            svg += generateMatch(10, conSemiY[1], 'L QF3', 'L QF4', conHeight);
            svg += generateMatch(conFinalX, conFinalY, 'W C-SF1', 'W C-SF2', conFinalHeight);
            svg +=
                `<line x1="${conFinalX + 220}" y1="${conFinalY + conFinalHeight / 2}" x2="${conFinalX + 320}" y2="${conFinalY + conFinalHeight / 2}" stroke="black" stroke-width="2" />`;
            svg += generateMatch(conFinalX + 300, con78Y, 'L C-SF1', 'L C-SF2', conHeight);
            svg +=
                `<line x1="${conFinalX + 520}" y1="${con78Y + conHeight / 2}" x2="${conFinalX + 620}" y2="${con78Y + conHeight / 2}" stroke="black" stroke-width="2" />`;

            return svg;
        }

        function buildAllDraws(drawSets, showNames = false) {
            let svg = `<svg width="1800" height="${drawSets.length * 900}" xmlns="http://www.w3.org/2000/svg">`;

            for (let i = 0; i < drawSets.length; i++) {
                const yOffset = i * 900;

                const players = showNames ?
                    drawSets[i] :
                    drawSets[i].map(name => (name === 'Bye' ? 'Bye' : 'TBD'));

                svg += buildSingleDraw(players, yOffset, i + 1);
            }

            svg += '</svg>';

            $('#draw-container').html(svg);

        }


        const allDraws = [];
        for (let i = 0; i < numberOfDraws; i++) {
            allDraws.push(getDrawSet(i));
        }

       document.addEventListener('DOMContentLoaded', function () {
    buildAllDraws(allDraws, isDrawLocked); // ✅ show names only if locked
});

    </script>
</body>

</html>
