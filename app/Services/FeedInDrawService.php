<?php


namespace App\Services;

class FeedInDrawService
{
    protected int $boxWidth = 180;
    protected int $boxHeight = 40;
    protected int $gapY = 20;
    protected int $gapX = 220;
    protected int $currentY = 60;

    protected function box($x, $y, $text)
    {
        return "<rect x='{$x}' y='{$y}' width='{$this->boxWidth}' height='{$this->boxHeight}' fill='#fff' stroke='#000'/>
                <text x='" . ($x + 10) . "' y='" . ($y + 25) . "' font-size='14'>{$text}</text>";
    }

    protected function line($x1, $y1, $x2, $y2)
    {
        return "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='black'/>";
    }

    public function build(int $players = 8): string
    {


        $svg = "<svg width='2400' height='3000' xmlns='http://www.w3.org/2000/svg'>";

        $rounds = log($players, 2);
        $matchesPerRound = [];

        // --- MAIN BRACKET ---
        $svg .= "<text x='10' y='20' font-size='18' font-weight='bold'>Main Draw</text>";
        for ($r = 0; $r < $rounds; $r++) {
            $matches = pow(2, $rounds - $r - 1);
            $startY = 60 * pow(2, $r);
            for ($m = 0; $m < $matches; $m++) {
                $x = $r * $this->gapX;
                $y = $startY + ($m * $this->boxHeight * pow(2, $r + 1));
                $svg .= $this->box($x, $y, "R" . ($r + 1) . "M" . ($m + 1));
                $matchesPerRound[$r][] = ['x' => $x, 'y' => $y];
            }
        }

        // --- CONSOLATION BRACKET ---
        $svg .= "<text x='10' y='1200' font-size='18' font-weight='bold'>Consolation Draw</text>";

        $baseY = 1250;
        foreach ($matchesPerRound as $r => $matches) {
            foreach ($matches as $mIndex => $match) {
                $x = $r * $this->gapX;
                $y = $baseY + $mIndex * ($this->boxHeight + $this->gapY);
                $svg .= $this->box($x, $y, "C-R" . ($r + 1) . "M" . ($mIndex + 1));

                // Connect from main bracket
                $mainX = $match['x'] + $this->boxWidth;
                $mainY = $match['y'] + ($this->boxHeight / 2);
                $svg .= $this->line($mainX, $mainY, $x, $y + $this->boxHeight / 2);
            }
        }

        // Optional: playoff matches for placement after they lose in consolation
        $svg .= "<text x='10' y='2000' font-size='16'>Placement Playoffs (after consolation loss)</text>";
        $svg .= $this->box(0, 2050, "5th Place Match");
        $svg .= $this->box(250, 2050, "7th Place Match");

        return $svg . "</svg>";
    }

    public function testMatchBox(): string
{
    $svg = "<svg width='600' height='200' xmlns='http://www.w3.org/2000/svg'>";

    // Coordinates
    $x = 20;
    $y1 = 40;
    $y2 = 80;

    // Match box
    $svg .= "<text x='{$x}' y='{$y1}' font-size='16' font-family='Arial'>Joubert Bornman</text>";
    $svg .= "<text x='{$x}' y='{$y2}' font-size='16' font-family='Arial'>Alexander Nel</text>";
    $svg .= "<text x='{$x}' y='" . ($y2 + 20) . "' font-size='14' font-family='Arial'>6 - 4 - 3</text>";

    // Bracket lines: elbow style
    $lineX1 = $x + 150;
    $lineYmid = ($y1 + $y2) / 2;

    // horizontal from top player
    $svg .= "<line x1='{$x}' y1='{$y1}' x2='{$lineX1}' y2='{$y1}' stroke='black'/>";
    // horizontal from bottom player
    $svg .= "<line x1='{$x}' y1='{$y2}' x2='{$lineX1}' y2='{$y2}' stroke='black'/>";
    // vertical connector
    $svg .= "<line x1='{$lineX1}' y1='{$y1}' x2='{$lineX1}' y2='{$y2}' stroke='black'/>";

    // forward connection line (to next match)
    $svg .= "<line x1='{$lineX1}' y1='{$lineYmid}' x2='" . ($lineX1 + 50) . "' y2='{$lineYmid}' stroke='black'/>";

    $svg .= "</svg>";
    return $svg;
}

}
