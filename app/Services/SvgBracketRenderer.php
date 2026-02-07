<?php

namespace App\Services;

use App\Models\Fixture;
use Illuminate\Support\Facades\Auth;

class SvgBracketRenderer
{
    protected $fixtures;
    protected $rounds = [];
    protected $coords = [];
    protected $boxWidth = 200;
    protected $boxHeight = 60;
    protected $gapY = 40;
    protected $gapX = 180;

    public function __construct($draw)
    {
        $this->fixtures = $draw->drawFixtures()
            ->orderBy('round')
            ->orderBy('match_nr')
            ->get();

        $this->groupByRound();
        $this->computeCoordinates();
    }

    /** GROUP FIXTURES BY ROUND */
    protected function groupByRound()
    {
        foreach ($this->fixtures as $fx) {
            $this->rounds[$fx->round][] = $fx;
        }
    }

    /** CALCULATE (x,y) FOR EVERY FIXTURE */
    protected function computeCoordinates()
    {
        $currentY = 40;

        foreach ($this->rounds as $round => $fixtures) {

            $x = ($round - 1) * ($this->boxWidth + $this->gapX);

            $y = 40;
            foreach ($fixtures as $fx) {
                $this->coords[$fx->id] = [
                    'x' => $x,
                    'y' => $y
                ];
                $y += $this->boxHeight + $this->gapY;
            }

            $currentY = max($currentY, $y);
        }
    }

    /** RENDER THE FULL SVG */
    public function render()
    {
        $height = $this->computeSvgHeight();
        $width  = $this->computeSvgWidth();

        $svg = "<svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<g font-family='Arial'>";

        foreach ($this->fixtures as $fx) {
            $svg .= $this->renderFixtureBox($fx);
            $svg .= $this->renderConnections($fx);
        }

        $svg .= "</g></svg>";
        return $svg;
    }

    protected function computeSvgHeight()
    {
        $max = 0;
        foreach ($this->coords as $id => $c) {
            $max = max($max, $c['y'] + $this->boxHeight + 50);
        }
        return $max + 100;
    }

    protected function computeSvgWidth()
    {
        $max = 0;
        foreach ($this->coords as $id => $c) {
            $max = max($max, $c['x']);
        }
        return $max + $this->boxWidth + 200;
    }

    /** ONE FIXTURE BOX */
    protected function renderFixtureBox(Fixture $fx)
    {
        $c = $this->coords[$fx->id];
        $x = $c['x'];
        $y = $c['y'];

        $p1 = $this->resolveName($fx->registration1_id, $fx);
        $p2 = $this->resolveName($fx->registration2_id, $fx);

        $winner = $fx->winner_registration;
    $isAdmin = Auth::check() && auth()->user()->hasRole('admin');


    $score = trim($this->getScore($fx));

        $oop = $this->getOop($fx);

        $svg = "";

        // Outer bracket box
        $svg .= "<rect x='$x' y='$y' width='{$this->boxWidth}' height='{$this->boxHeight}' fill='white' stroke='black'/>";

        // Player 1
        $style1 = ($winner == $fx->registration1_id) ? "font-weight:bold;" :
                  (($winner && $winner != $fx->registration1_id) ? "fill:#777;" : "");
        $svg .= "<text x='".($x+8)."' y='".($y+18)."' style='$style1' font-size='14'>{$p1}</text>";

        // Player 2
        $style2 = ($winner == $fx->registration2_id) ? "font-weight:bold;" :
                  (($winner && $winner != $fx->registration2_id) ? "fill:#777;" : "");
        $svg .= "<text x='".($x+8)."' y='".($y+38)."' style='$style2' font-size='14'>{$p2}</text>";

        // Divider line
        $svg .= "<line x1='$x' y1='".($y+30)."' x2='".($x+$this->boxWidth)."' y2='".($y+30)."' stroke='black'/>";

        // Score
        if ($score !== "") {
            $svg .= "<text x='".($x+$this->boxWidth+10)."' y='".($y+25)."' font-size='13' fill='green'>{$score}</text>";
        }

        // Fixture ID (top right)
        $svg .= "<text x='".($x + $this->boxWidth - 5)."' y='".($y + 12)."' font-size='10' text-anchor='end' fill='red'>#{$fx->id}</text>";

        // Match number (admin only)
        if ($isAdmin) {
            $svg .= "<text x='".($x - 5)."' y='".($y + 25)."' font-size='10' text-anchor='end' fill='red'>({$fx->match_nr})</text>";
        }

        // Not before time + venue
        if ($oop) {
            $svg .= "<text x='".($x)." ' y='".($y - 5)."' fill='orange' font-size='10'>{$oop}</text>";
        }

        return $svg;
    }

    /** CONNECTION TO PARENT FIXTURE */
    protected function renderConnections(Fixture $fx)
    {
        if (!$fx->parent_fixture_id) {
            return "";
        }

        $child = $this->coords[$fx->id];
        $parent = $this->coords[$fx->parent_fixture_id];

        $cx = $child['x'] + $this->boxWidth;
        $cy = $child['y'] + ($this->boxHeight / 2);

        $px = $parent['x'];
        $py = $parent['y'] + ($this->boxHeight / 2);

        return "
            <line x1='{$cx}' y1='{$cy}' x2='".($cx + 40)."' y2='{$cy}' stroke='black'/>
            <line x1='".($cx + 40)."' y1='{$cy}' x2='".($cx + 40)."' y2='{$py}' stroke='black'/>
            <line x1='".($cx + 40)."' y1='{$py}' x2='{$px}' y2='{$py}' stroke='black'/>
        ";
    }

    /** PLAYER NAME / BYE */
    protected function resolveName($registration_id, Fixture $fx)
    {
        if ($registration_id == 0) {
            return "Bye";
        }
        if (!$registration_id) {
            return "";
        }
        return $fx->resolveName($registration_id);
    }

    /** SCORE (your existing logic) */
    protected function getScore(Fixture $fx)
    {
        if (!$fx->fixtureResults->count()) {
            return "";
        }

        $sets = $fx->fixtureResults;
        $out = "";

        $winner = $fx->winner_registration;
        $p1 = $fx->registration1_id;

        foreach ($sets as $set) {
            if ($winner == $p1) {
                $out .= "{$set->registration1_score}-{$set->registration2_score} ";
            } else {
                $out .= "{$set->registration2_score}-{$set->registration1_score} ";
            }
        }
        return trim($out);
    }

    /** OOP TIME + VENUE */
    protected function getOop(Fixture $fx)
    {
        if (!$fx->oop || !$fx->draws || $fx->draws->oop_published != 1) {
            return "";
        }

        $time = date('D g:i A', strtotime($fx->oop->time));
        return "NB: {$time} / {$fx->oop->venue->name}";
    }
}
