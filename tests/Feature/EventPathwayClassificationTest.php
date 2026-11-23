<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\EventCategoryController;
use App\Models\{CategoryEvent, Event, User};
use App\Services\Performance\EventPathwayClassification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Route, Schema};
use Tests\TestCase;

class EventPathwayClassificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('events', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); });
        Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); });
        Schema::create('category_events', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_id'); });
        Schema::create('event_admins', function (Blueprint $t) { $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('user_id'); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        (require database_path('migrations/2026_11_23_150000_add_pathway_levels_to_events_and_categories.php'))->up();
        Route::middleware('web')->group(function () {
            Route::patch('/_pathway-test/category/{categoryEvent}', [EventCategoryController::class, 'updatePathway']);
            Route::patch('/_pathway-test/event/{event}', [EventCategoryController::class, 'updateEventPathway']);
            Route::post('/_pathway-test/event/{event}/attach', [EventCategoryController::class, 'attach']);
            Route::post('/_pathway-test/event/{event}/create', [EventCategoryController::class, 'createAndAttach']);
        });
    }

    private function event(?string $level = null): Event
    {
        $id = DB::table('events')->insertGetId(['name' => 'Fictional pathway event', 'pathway_level' => $level]);
        return Event::findOrFail($id);
    }

    private function field(Event $event, string $name = 'U12 Boys', ?string $level = null): CategoryEvent
    {
        $id = DB::table('categories')->insertGetId(['name' => $name]);
        return $event->categoryEvents()->create(['category_id' => $id, 'pathway_level' => $level]);
    }

    private function actor(Event $event): User
    {
        $user = (new User)->forceFill(['id' => 51, 'name' => 'Fictional organiser']);
        $user->setRelation('roles', collect());
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return $user;
    }

    public function test_category_overrides_event_default_without_affecting_same_global_category_elsewhere(): void
    {
        $resolver = app(EventPathwayClassification::class);
        $event = $this->event('wilson-a');
        $field = $this->field($event);
        $otherEvent = $this->event('wilson-b');
        $other = $otherEvent->categoryEvents()->create(['category_id' => $field->category_id]);
        $this->assertSame('Wilson A', $resolver->forCategory($field));
        $this->assertSame('Wilson B', $resolver->forCategory($other));
        $field->update(['pathway_level' => 'red']);
        $this->assertSame('red', $resolver->forCategory($field));
        $this->assertSame('wilson-a', $event->fresh()->pathway_level);
        $this->assertSame('Wilson B', $resolver->forCategory($other));
        $field->update(['pathway_level' => null]);
        $this->assertSame('Wilson A', $resolver->forCategory($field));
    }

    public function test_ordinary_event_forms_cannot_bypass_pathway_authorization(): void
    {
        $owned = $this->event('red');
        $foreign = $this->event('wilson-a');
        $this->actor($owned);
        $this->withoutMiddleware();
        $this->patchJson(route('backend.events.update', $foreign), ['name' => 'Tampered', 'pathway_level' => 'orange'])->assertForbidden();
        $this->postJson(route('backend.events.store'), ['name' => 'Unauthorized new event', 'pathway_level' => 'red'])->assertForbidden();
        $this->assertSame('wilson-a', $foreign->fresh()->pathway_level);
        $this->assertDatabaseCount('events', 2);
    }

    public function test_actual_ball_evidence_conflicts_fail_closed_without_changing_rating_context(): void
    {
        $resolver = app(EventPathwayClassification::class);
        $event = $this->event('wilson-a');
        $orange = $this->field($event, 'U9 Boys A');
        $this->assertTrue($resolver->hasExplicit($orange));
        $this->assertNull($resolver->forCategory($orange));
        $orange->update(['pathway_level' => 'orange']);
        $this->assertSame('orange', $resolver->forCategory($orange));
        $this->assertNull($resolver->forCategory($this->field($event, 'Red ball')));
        $this->assertNull($resolver->forCategory($this->field($event, 'Green ball', 'red')));
        $unknown = $this->field($this->event());
        $this->assertFalse($resolver->hasExplicit($unknown));
        $this->assertNull($resolver->forCategory($unknown));
        DB::table('category_events')->where('id', $unknown->id)->update(['pathway_level' => 'tsa']);
        $this->assertTrue($resolver->hasExplicit($unknown->fresh()));
        $this->assertNull($resolver->forCategory($unknown->fresh()));
    }

    public function test_category_endpoint_saves_and_resets_override_and_rejects_tsa(): void
    {
        $event = $this->event('wilson-a');
        $field = $this->field($event);
        $this->actor($event);
        $url = '/_pathway-test/category/'.$field->id;
        $this->patch($url, ['pathway_level' => 'orange'])->assertRedirect();
        $this->assertSame('orange', $field->fresh()->pathway_level);
        foreach (['tsa', 'Wilson A', ['red']] as $invalid) {
            $this->patchJson($url, ['pathway_level' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('pathway_level');
            $this->assertSame('orange', $field->fresh()->pathway_level);
        }
        $this->patchJson($url, [])->assertUnprocessable();
        $this->patch($url, ['pathway_level' => ''])->assertRedirect();
        $this->assertNull($field->fresh()->pathway_level);
        $this->assertSame('Wilson A', app(EventPathwayClassification::class)->forCategory($field->fresh()));
    }

    public function test_court_evidence_is_event_scoped_and_mixed_ball_assignments_are_withheld(): void
    {
        Schema::create('draws', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_event_id'); });
        Schema::create('draw_venue_court_allocations', function (Blueprint $t) { $t->unsignedBigInteger('draw_id'); $t->unsignedBigInteger('venue_id'); $t->string('court_label'); });
        Schema::create('event_venue_courts', function (Blueprint $t) { $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('venue_id'); $t->string('label'); $t->string('ball_type'); });
        $event = $this->event('red');
        $field = $this->field($event);
        $drawId = DB::table('draws')->insertGetId(['event_id' => $event->id, 'category_event_id' => $field->id]);
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $drawId, 'venue_id' => 1, 'court_label' => '1']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => 1, 'label' => '1', 'ball_type' => 'red']);
        $resolver = app(EventPathwayClassification::class);
        $this->assertSame('red', $resolver->forCategory($field));
        DB::table('event_venue_courts')->where('event_id', $event->id)->update(['ball_type' => 'orange']);
        $this->assertNull($resolver->forCategory($field));
        $otherEvent = $this->event('red');
        $otherField = $this->field($otherEvent);
        $this->assertSame('red', $resolver->forCategory($otherField));
        $otherField->setRelation('event', $event);
        $this->assertNull($resolver->forCategory($otherField));
        DB::table('event_venue_courts')->where('event_id', $event->id)->update(['ball_type' => 'red']);
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $drawId, 'venue_id' => 1, 'court_label' => '2']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => 1, 'label' => '2', 'ball_type' => 'orange']);
        $this->assertNull($resolver->forCategory($field));
    }

    public function test_event_default_changes_only_inherited_categories_and_rejects_tsa(): void
    {
        $event = $this->event('wilson-a');
        $inherited = $this->field($event);
        $override = $this->field($event, 'Orange ball', 'orange');
        $this->actor($event);
        $url = '/_pathway-test/event/'.$event->id;
        $this->patch($url, ['pathway_level' => 'wilson-b'])->assertRedirect();
        $this->assertSame('wilson-b', $event->fresh()->pathway_level);
        $this->assertNull($inherited->fresh()->pathway_level);
        $this->assertSame('orange', $override->fresh()->pathway_level);
        $this->patchJson($url, ['pathway_level' => 'tsa'])->assertUnprocessable();
        $this->assertSame('wilson-b', $event->fresh()->pathway_level);
    }

    public function test_other_event_admin_cannot_change_defaults_or_category_override(): void
    {
        $event = $this->event('wilson-a');
        $field = $this->field($event);
        $this->actor($this->event());
        $this->patchJson('/_pathway-test/event/'.$event->id, ['pathway_level' => 'red'])->assertForbidden();
        $this->patchJson('/_pathway-test/category/'.$field->id, ['pathway_level' => 'red'])->assertForbidden();
        $this->assertSame('wilson-a', $event->fresh()->pathway_level);
        $this->assertNull($field->fresh()->pathway_level);
    }

    public function test_attach_override_applies_only_to_new_associations_and_create_is_event_scoped(): void
    {
        $event = $this->event('wilson-a');
        $existing = $this->field($event, 'Existing', 'red');
        $newId = DB::table('categories')->insertGetId(['name' => 'New shared category']);
        $this->actor($event);
        $this->post('/_pathway-test/event/'.$event->id.'/attach', ['category_ids' => [$existing->category_id, $newId], 'pathway_level' => 'orange'])->assertRedirect();
        $this->assertSame('red', $existing->fresh()->pathway_level);
        $this->assertDatabaseHas('category_events', ['event_id' => $event->id, 'category_id' => $newId, 'pathway_level' => 'orange']);
        $this->post('/_pathway-test/event/'.$event->id.'/create', ['name' => 'Fictional new division', 'pathway_level' => 'wilson-b'])->assertRedirect();
        $this->assertDatabaseHas('category_events', ['event_id' => $event->id, 'category_id' => DB::table('categories')->where('name', 'Fictional new division')->value('id'), 'pathway_level' => 'wilson-b']);
        $this->assertDatabaseCount('category_events', 3);
    }
}
