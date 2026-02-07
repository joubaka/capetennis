<?php

namespace App\Services;

class CtBracket
{
    protected int $boxWidth = 200;
    protected int $boxHeight = 40;
    protected int $gapX = 220;
    protected int $gapY = 50;

    protected array $players;

    public function __construct()
    {
        $this->players = [
            'Regardt Delport', 'Joubert Bornman',
            'Michael Smook', 'Alexander Nel',
        ];
    }

    public function build(): string
    {
        $svg = "<svg width='1200' height='800' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<style>
            text { font-family: Arial; }
            .score { font-size: 12px; fill: #000; }
        </style>";

        $rounds = [];

        // === ROUND 1 ===
        for ($i = 0; $i < 2; $i++) {
            $x = 0;
            $y = 50 + $i * (2 * $this->boxHeight + $this->gapY);
            $rounds[1][$i] = ['x' => $x, 'y' => $y];
            $svg .= $this->matchBoxWithConnector($x, $y, $this->players[$i * 2], $this->players[$i * 2 + 1], "6 - 0 6 - 0");
        }

        // === ROUND 2 === (Final)
        $prev1 = $rounds[1][0];
        $prev2 = $rounds[1][1];
        $x = $prev1['x'] + $this->boxWidth + $this->gapX;
        $y = ($prev1['y'] + $prev2['y']) / 2;
        $svg .= $this->matchBoxWithConnector($x, $y, "Regardt Delport", "Michael Smook", "6 - 1 6 - 1", false);

        $svg .= "</svg>";
        return $svg;
    }

    protected function matchBoxWithConnector($x, $y, $player1, $player2, $score = '', $hasConnector = true): string
    {
        $w = $this->boxWidth;
        $h = $this->boxHeight;
        $midY = $y + $h;

        $svg = "
            <!-- Match box outline (no left line) -->
            <line x1='{$x}' y1='{$y}' x2='" . ($x + $w) . "' y2='{$y}' stroke='black'/>
            <line x1='{$x}' y1='" . ($y + 2 * $h) . "' x2='" . ($x + $w) . "' y2='" . ($y + 2 * $h) . "' stroke='black'/>
            <line x1='" . ($x + $w) . "' y1='{$y}' x2='" . ($x + $w) . "' y2='" . ($y + 2 * $h) . "' stroke='black'/>

            <!-- Player names -->
            <text x='" . ($x + 5) . "' y='" . ($y + 15) . "'>{$player1}</text>
            <text x='" . ($x + 5) . "' y='" . ($y + $h + 15) . "'>{$player2}</text>

            <!-- Score -->
            <text class='score' x='" . ($x + 5) . "' y='" . ($y + 2 * $h + 20) . "'>{$score}</text>
        ";

        if ($hasConnector) {
            $xStart = $x + $w;
            $xEnd = $x + $w + 40;
            $svg .= "
                <!-- Horizontal elbow connector -->
                <line x1='{$xStart}' y1='{$midY}' x2='{$xEnd}' y2='{$midY}' stroke='black'/>
            ";
        }

        return $svg;
    }
}
