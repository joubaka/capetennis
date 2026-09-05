<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\FlexibleMonradDraw;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HeadOfficeDrawOverviewTest extends TestCase
{
    private function draw(bool $flexible = false): Draw
    {
        $draw = (new Draw)->forceFill([
            'id' => 42, 'event_id' => 233, 'drawName' => 'U/10 Boys',
            'published' => false, 'locked' => false, 'is_flexible' => $flexible,
            'draw_fixtures_count' => 12,
        ]);
        $draw->setRelation('settings', (new DrawSetting)->forceFill([
            'workflow' => $flexible ? 'custom_monrad' : 'round_robin_playoffs',
        ]));
        $draw->setRelation('venues', collect());
        $draw->setRelation('flexibleMonrad', $flexible
            ? (new FlexibleMonradDraw)->forceFill(['draw_id' => 42, 'revision' => 7])
            : null);

        return $draw;
    }

    public function test_flexible_draw_uses_editor_publication_instead_of_rejected_legacy_toggle(): void
    {
        Gate::before(fn (?User $user) => true);
        $html = view('backend.headOffice.partials.individual-draw-row', ['draw' => $this->draw(true)])->render();

        $this->assertStringContainsString(route('backend.draw.roundrobin.show', 42), $html);
        $this->assertStringContainsString(route('backend.draw.roundrobin.show', 42).'#schedule', $html);
        $this->assertStringContainsString(route('backend.draw.roundrobin.show', 42).'#print', $html);
        $this->assertStringContainsString(route('flexible-monrad.publish', 42), $html);
        $this->assertStringContainsString('data-revision="7"', $html);
        $this->assertStringContainsString('draw-publish-button draws-button-primary', $html);
        $this->assertStringContainsString('>Publish</span>', $html);
        $this->assertStringNotContainsString(route('draw.toggle.publish', 42), $html);
        $this->assertStringNotContainsString('Publication in draw editor', $html);
        $this->assertStringContainsString('Custom Monrad', $html);
        $this->assertStringContainsString('12 matches', $html);
    }

    public function test_legacy_draw_retains_settings_and_publication_routes(): void
    {
        Gate::before(fn (?User $user) => true);
        $html = view('backend.headOffice.partials.individual-draw-row', ['draw' => $this->draw()])->render();

        foreach (['backend.draw.roundrobin.show', 'draw.toggle.publish', 'draws.destroy'] as $route) {
            $this->assertStringContainsString(route($route, 42), $html);
        }
        $this->assertStringNotContainsString('btn-add-venues', $html);
        $this->assertStringContainsString('aria-label="Publish U/10 Boys"', $html);
    }

    public function test_prepared_schedule_has_an_explicit_publication_action(): void
    {
        Gate::before(fn (?User $user) => true);
        $draw = $this->draw();
        $draw->published = true;
        $draw->order_of_play_count = 3;

        $html = view('backend.headOffice.partials.individual-draw-row', compact('draw'))->render();

        $this->assertStringContainsString('toggle-schedule-publication', $html);
        $this->assertStringContainsString(route('draw.toggle.publish.schedule', 42), $html);
        $this->assertStringContainsString('Publish times', $html);
    }

    public function test_published_or_locked_draw_never_offers_delete_even_with_super_user_gate(): void
    {
        Gate::before(fn (?User $user) => true);
        foreach (['locked', 'published'] as $state) {
            $draw = $this->draw();
            $draw->$state = true;
            $html = view('backend.headOffice.partials.individual-draw-row', compact('draw'))->render();
            $this->assertStringNotContainsString('btn-delete-draw', $html);
        }
    }

    public function test_published_draw_uses_secondary_unpublish_action(): void
    {
        Gate::before(fn (?User $user) => true);
        $draw = $this->draw();
        $draw->published = true;

        $html = view('backend.headOffice.partials.individual-draw-row', compact('draw'))->render();

        $this->assertStringContainsString('draw-publish-button draws-button-secondary', $html);
        $this->assertStringContainsString('aria-label="Unpublish U/10 Boys"', $html);
        $this->assertStringContainsString('>Unpublish</span>', $html);
    }

    public function test_unauthorized_view_does_not_offer_mutations_or_diagnostics(): void
    {
        $html = view('backend.headOffice.partials.individual-draw-row', ['draw' => $this->draw()])->render();
        foreach (['btn-add-venues', 'draw-publish-button', 'btn-delete-draw', 'Engine diagnostics'] as $action) {
            $this->assertStringNotContainsString($action, $html);
        }
    }

    public function test_overview_has_accessible_status_filters_and_searchable_formats(): void
    {
        Gate::before(fn (?User $user) => true);
        $event = (new Event)->forceFill(['id' => 233, 'name' => 'Trial <event>']);
        $published = $this->draw();
        $published->forceFill(['id' => 43, 'published' => true]);
        $event->setRelation('draws', collect([$this->draw(true), $published]));

        $html = view('backend.headOffice.partials.individual-draw-overview', compact('event'))->render();

        $this->assertStringContainsString('Trial &lt;event&gt;', $html);
        $this->assertStringContainsString('data-draw-filter="" aria-pressed="true">All draws <span>2</span>', $html);
        $this->assertStringContainsString('data-draw-filter="0" aria-pressed="false"', $html);
        $this->assertStringContainsString('data-draw-filter="1" aria-pressed="false"', $html);
        $this->assertStringContainsString('Draft <span>1</span>', $html);
        $this->assertStringContainsString('Published <span>1</span>', $html);
        $this->assertStringContainsString('aria-live="polite">Showing 2 draws', $html);
        $this->assertStringContainsString('id="draw-select-all"', $html);
        $this->assertStringContainsString('id="schedule-selected"', $html);
        $this->assertStringContainsString('id="publish-selected-draws"', $html);
        $this->assertStringContainsString('id="unpublish-selected-draws"', $html);
        $this->assertStringContainsString('id="publish-selected-times"', $html);
        $this->assertStringContainsString('id="unpublish-selected-times"', $html);
        $this->assertStringContainsString('Schedule all matches', $html);
        $this->assertStringContainsString('data-bs-target="#scheduleVisibilityModal"', $html);
        $this->assertStringContainsString('Time display', $html);
        $this->assertStringContainsString('data-format="Custom Monrad"', $html);
        $this->assertStringContainsString('id="draw-select-42"', $html);
        $this->assertStringContainsString('aria-label="More actions for U/10 Boys"', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_unknown_format_is_explicit_without_guessing_a_workflow(): void
    {
        Gate::before(fn (?User $user) => true);
        $draw = $this->draw();
        $draw->setRelation('settings', null);
        $html = view('backend.headOffice.partials.individual-draw-row', compact('draw'))->render();

        $this->assertStringContainsString('Not specified', $html);
        $this->assertStringNotContainsString('Custom Monrad', $html);
        $this->assertStringContainsString(route('backend.draw.roundrobin.show', 42).'#schedule', $html);
        $this->assertStringContainsString('Select a draw format before publishing', $html);
    }

    public function test_empty_event_has_creation_guidance_without_unusable_filters(): void
    {
        $event = (new Event)->forceFill(['id' => 233, 'name' => 'Empty event']);
        $event->setRelation('draws', collect());
        $html = view('backend.headOffice.partials.individual-draw-overview', compact('event'))->render();

        $this->assertStringContainsString('Create your first draw', $html);
        $this->assertStringContainsString('data-bs-target="#createDrawModal"', $html);
        $this->assertStringNotContainsString('id="draw-search"', $html);
        $this->assertStringNotContainsString('data-bs-target="#printAllDrawsModal"', $html);
    }
}
