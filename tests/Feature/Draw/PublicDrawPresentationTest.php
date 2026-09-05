<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawSetting, Event, User};
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicDrawPresentationTest extends TestCase
{
    public function test_round_robin_draft_preview_uses_shared_navigation_and_schedule_contract(): void
    {
        Gate::before(fn (?User $user) => true);
        $this->actingAs((new User)->forceFill(['id' => 99]));

        $event = (new Event)->forceFill([
            'id' => 233,
            'name' => 'Overberg Tennis Trials - Leg 3 2026',
            'start_date' => '2026-09-06 08:00:00',
        ]);
        $draw = (new Draw)->forceFill([
            'id' => 1449,
            'event_id' => 233,
            'drawName' => 'u/19 Boys',
            'published' => false,
            'oop_published' => true,
        ]);
        $draw->setRelation('event', $event);
        $draw->setRelation('venues', collect());
        $draw->setRelation('settings', (new DrawSetting)->forceFill(['workflow' => 'round_robin']));
        $draw->setRelation('flexibleMonrad', null);

        $html = view('frontend.roundrobin.show', [
            'draw' => $draw,
            'groupsJson' => collect(),
            'rrFixtures' => [],
            'oops' => collect(),
            'standings' => [],
            'svg' => null,
        ])->render();

        $this->assertStringContainsString('Draft preview', $html);
        $this->assertStringContainsString('Draw not published', $html);
        $this->assertStringContainsString('Manage this draw', $html);
        $this->assertStringContainsString('Match times', $html);
        $this->assertStringContainsString('<th class="text-center">Court</th>', $html);
        $this->assertStringNotContainsString('<span class="badge bg-label-success">Draw published</span>', $html);
    }

    public function test_public_script_preserves_backend_order_and_renders_court(): void
    {
        $script = file_get_contents(public_path('assets/js/roundrobin-public.js'));

        $this->assertStringContainsString('const sorted = RR_OOP.slice();', $script);
        $this->assertStringContainsString('data-label="Court"', $script);
        $this->assertStringContainsString("'Court ' + fx.court", $script);
        $this->assertStringNotContainsString('if (a.round !== b.round)', $script);
    }
}
