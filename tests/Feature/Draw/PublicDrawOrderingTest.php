<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicDrawOrderingTest extends TestCase
{
    public function test_published_draws_sort_by_numeric_age_and_name_without_exposing_unpublished_draws(): void
    {
        Gate::define('event.score', fn () => false);
        $names = ['u/13 Girls – Singles', 'u/10 Girls – Singles', 'u/12 Boys – Singles', 'u/9 Boys – Singles', 'u/10 Boys – Singles', 'Under 11 Girls – Singles'];
        $draws = collect($names)->map(function ($name, $index) {
            $draw = new Draw(['drawName' => $name, 'published' => true, 'drawType_id' => 1]);
            $draw->id = $index + 1;
            $draw->setRelation('draw_types', null);
            $draw->setRelation('flexibleMonrad', null);
            $draw->setRelation('settings', null);

            return $draw;
        });
        $hidden = new Draw(['drawName' => 'u/8 Hidden', 'published' => false]);
        $html = view('frontend.event.partials._draws_and_order_of_play', [
            'event' => new Event(['id' => 1]), 'eventDraws' => $draws->push($hidden),
        ])->render();

        $previous = -1;
        foreach (['u/9 Boys', 'u/10 Boys', 'u/10 Girls', 'Under 11 Girls', 'u/12 Boys', 'u/13 Girls'] as $label) {
            $position = strpos($html, $label);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position);
            $previous = $position;
        }
        $this->assertStringNotContainsString('u/8 Hidden', $html);
    }
}
