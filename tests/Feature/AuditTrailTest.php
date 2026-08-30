<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Player;
use App\Models\User;
use App\Models\RegistrationOrder;
use App\Support\Audit\AuditIntegrity;
use App\Support\Audit\AuditWriter;
use App\Support\Audit\AuditModelSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Mockery;
use RuntimeException;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $superUser;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->superUser = User::factory()->create()->assignRole('super-user');
        DB::table('audit_events')->delete();
    }

    public function test_model_create_update_and_delete_records_actor_and_snapshots(): void
    {
        $this->actingAs($this->superUser);

        $player = Player::factory()->create(['name' => 'Audit', 'surname' => 'Player']);
        $player->update(['surname' => 'Changed']);
        $playerId = $player->id;
        $player->delete();

        $created = AuditEvent::where('action', 'player.created')->where('subject_id', $playerId)->firstOrFail();
        $updated = AuditEvent::where('action', 'player.updated')->where('subject_id', $playerId)->firstOrFail();
        $deleted = AuditEvent::where('action', 'player.deleted')->where('subject_id', $playerId)->firstOrFail();

        $this->assertSame($this->superUser->id, $created->actor_id);
        $this->assertSame('Player', $created->after['surname']);
        $this->assertSame('Player', $updated->before['surname']);
        $this->assertSame('Changed', $updated->after['surname']);
        $this->assertSame('Changed', $deleted->before['surname']);
        $this->assertDatabaseMissing('players', ['id' => $playerId]);
    }

    public function test_writer_redacts_secrets_and_integrity_hash_verifies_after_reload(): void
    {
        $this->actingAs($this->superUser);

        $id = app(AuditWriter::class)->record([
            'category' => 'security',
            'action' => 'test.secret-redaction',
            'after' => [
                'name' => 'Safe Name',
                'password' => 'not-safe',
                'refund_account_number' => '123456789',
            ],
        ], true);

        $event = AuditEvent::findOrFail($id);
        $this->assertSame('Safe Name', $event->after['name']);
        $this->assertSame('[REDACTED]', $event->after['password']);
        $this->assertSame('[REDACTED]', $event->after['refund_account_number']);
        $raw = $event->getRawOriginal();
        $this->assertSame($event->integrity_hash, AuditIntegrity::hash($raw), json_encode($raw));
    }

    public function test_audit_centre_is_super_user_only_and_records_the_page_journey(): void
    {
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser)->get(route('superadmin.audit.index'))->assertForbidden();

        $this->actingAs($this->superUser)
            ->get(route('superadmin.audit.index'))
            ->assertOk()
            ->assertSee('Audit Centre');

        $view = AuditEvent::where('action', 'page.viewed')
            ->where('route_name', 'superadmin.audit.index')
            ->where('actor_id', $this->superUser->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($view->request_id);
        $this->assertNotNull($view->journey_id);
        $this->assertSame(200, $view->status_code);
    }

    public function test_audit_export_blocks_spreadsheet_formula_injection(): void
    {
        $this->superUser->forceFill(['name' => '=HYPERLINK("https://invalid.test")'])->save();

        $response = $this->actingAs($this->superUser)->get(route('superadmin.audit.export'));

        $response->assertOk()->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_audit_centre_rejects_invalid_date_filters(): void
    {
        $this->actingAs($this->superUser)
            ->from(route('superadmin.audit.index'))
            ->get(route('superadmin.audit.index', ['from' => 'not-a-date']))
            ->assertRedirect(route('superadmin.audit.index'))
            ->assertSessionHasErrors('from');
    }

    public function test_all_application_mutation_routes_include_canonical_request_auditing(): void
    {
        $missing = collect(Route::getRoutes())->filter(function ($route): bool {
            $action = $route->getActionName();
            $isApplicationAction = str_starts_with($action, 'App\\') || str_contains((string) ($route->getAction()['path'] ?? ''), 'routes/');
            $isMutation = count(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) > 0;
            if (! $isApplicationAction || ! $isMutation) {
                return false;
            }

            return ! in_array(
                \App\Http\Middleware\RecordAuditRequest::class,
                app('router')->gatherRouteMiddleware($route),
                true
            );
        })->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())->values()->all();

        $this->assertSame([], $missing, 'Mutation routes missing audit middleware: '.implode(', ', $missing));
    }

    public function test_client_side_button_interaction_is_recorded_for_authenticated_user(): void
    {
        $this->actingAs($this->superUser)->postJson(route('audit.interactions.store'), [
            'action' => 'lesson.mark-attended',
            'label' => 'Mark attended',
            'element' => 'button',
            'page_path' => '/backend/practice/10',
            'target_path' => '/backend/practice/10/attendance',
            'viewport_width' => 390,
            'viewport_height' => 844,
        ])->assertAccepted();

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $this->superUser->id,
            'category' => 'interaction',
            'action' => 'ui.lesson.mark-attended',
        ]);
    }

    public function test_payment_interaction_is_linked_to_owned_registration_order(): void
    {
        $user = User::factory()->create();
        $order = RegistrationOrder::create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('audit.interactions.store'), [
            'action' => 'payment.payfast-submit',
            'label' => 'Pay with PayFast',
            'element' => 'form',
            'page_path' => '/registration/checkout/'.$order->id,
            'order_id' => $order->id,
        ])->assertAccepted();

        $event = AuditEvent::where('action', 'ui.payment.payfast-submit')->latest('id')->firstOrFail();
        $this->assertSame((string) $order->id, $event->subject_id);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame($order->id, $event->metadata['order_id']);
    }

    public function test_payment_interaction_cannot_be_attached_to_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = RegistrationOrder::create(['user_id' => $owner->id]);

        $this->actingAs($other)->postJson(route('audit.interactions.store'), [
            'action' => 'payment.payfast-submit',
            'page_path' => '/registration/checkout/'.$order->id,
            'order_id' => $order->id,
        ])->assertForbidden();
    }

    public function test_public_button_interaction_is_recorded_without_form_values(): void
    {
        $this->postJson(route('audit.interactions.store'), [
            'action' => 'navigate.events',
            'label' => 'View events',
            'element' => 'a',
            'page_path' => '/',
            'target_path' => '/events',
        ])->assertAccepted();

        $event = AuditEvent::where('action', 'ui.navigate.events')->latest('id')->firstOrFail();
        $this->assertNull($event->actor_id);
        $this->assertSame('/', $event->metadata['page_path']);
        $this->assertArrayNotHasKey('input', $event->metadata);
    }

    public function test_audit_failure_prevents_a_delete_from_starting(): void
    {
        $this->actingAs($this->superUser);
        $player = Player::factory()->create();

        $writer = Mockery::mock(AuditWriter::class);
        $writer->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(AuditWriter::class, $writer);
        $this->app->instance(AuditModelSubscriber::class, new AuditModelSubscriber($writer));

        try {
            $player->delete();
            $this->fail('Delete should fail closed when the attempted audit cannot be written.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_daily_seal_detects_tampering(): void
    {
        $this->actingAs($this->superUser);
        $date = now()->subDay()->toDateString();
        app(AuditWriter::class)->record([
            'category' => 'business',
            'action' => 'test.sealed',
            'occurred_at' => now()->subDay(),
        ], true);

        $this->artisan("audit:seal {$date}")->assertSuccessful();
        $this->artisan("audit:seal {$date}")->assertSuccessful();

        $eventId = AuditEvent::where('action', 'test.sealed')->value('id');
        DB::table('audit_events')->where('id', $eventId)->update(['action' => 'test.tampered']);

        $this->artisan("audit:seal {$date}")->assertFailed();
    }

    public function test_retention_command_is_dry_run_by_default_and_audits_apply(): void
    {
        $oldId = app(AuditWriter::class)->record([
            'category' => 'navigation',
            'action' => 'page.viewed',
            'occurred_at' => now()->subDays(config('audit.retention.journey_days') + 1),
        ], true);

        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertDatabaseHas('audit_events', ['id' => $oldId]);

        $this->artisan('audit:prune --apply')->assertSuccessful();
        $this->assertDatabaseMissing('audit_events', ['id' => $oldId]);
        $this->assertDatabaseHas('audit_events', ['action' => 'audit.retention-pruned']);
    }

    public function test_bulk_delete_bypassing_model_events_still_has_database_receipt(): void
    {
        $this->actingAs($this->superUser);
        $players = Player::factory()->count(2)->create();
        DB::table('audit_events')->delete();

        Player::whereIn('id', $players->pluck('id'))->delete();

        $receipt = AuditEvent::where('action', 'database.players.deleted')->firstOrFail();
        $this->assertSame($this->superUser->id, $receipt->actor_id);
        $this->assertSame('players', $receipt->subject_type);
        $this->assertStringContainsString('delete from', strtolower($receipt->metadata['sql_template']));
        $this->assertSame(2, $receipt->metadata['binding_count']);
    }

    public function test_database_receipt_redacts_inline_string_and_numeric_literals(): void
    {
        DB::statement("update players set name = 'MustNotLeak' where id = 987654321");

        $receipt = AuditEvent::where('action', 'database.players.updated')
            ->latest('id')
            ->firstOrFail();
        $template = $receipt->metadata['sql_template'];

        $this->assertStringNotContainsString('MustNotLeak', $template);
        $this->assertStringNotContainsString('987654321', $template);
        $this->assertSame('update players set name = ? where id = ?', strtolower($template));
    }

    public function test_denied_mutation_records_attempt_and_denial_with_real_status(): void
    {
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser)->postJson(route('backend.roles.store'), ['name' => 'forbidden-role'])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $normalUser->id,
            'action' => 'route.backend.roles.store.attempted',
            'outcome' => 'attempted',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $normalUser->id,
            'action' => 'route.backend.roles.store',
            'outcome' => 'denied',
            'status_code' => 403,
        ]);
        $this->assertDatabaseMissing('roles', ['name' => 'forbidden-role']);
    }
}
