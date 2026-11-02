<?php

namespace Tests\Feature\Assistant;

use App\Models\{AuditEvent, CapesterSupportRequest, Event, User};
use App\Services\Assistant\CapesterSupportQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapesterSupportQueueTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        config(['capester.enabled' => true]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        return [$actor, $event];
    }

    private function data(array $extra = []): array
    {
        return array_replace(['submission_uuid' => (string) Str::uuid(), 'kind' => 'support',
            'capester_description' => 'Fictional support text without player data.', 'reviewed' => '1'], $extra);
    }

    public function test_web_requires_super_user_event_scope_and_review_and_is_idempotent(): void
    {
        [$actor, $event] = $this->scenario();
        $url = route('backend.capester.store', $event);
        $this->actingAs(User::factory()->create())->post($url, $this->data())->assertForbidden();
        $this->actingAs($actor)->post($url, $this->data(['reviewed' => null]))->assertSessionHasErrors('reviewed');
        $data = $this->data();
        $this->post($url, $data)->assertRedirect(route('backend.capester.index', $event));
        $this->post($url, $data)->assertRedirect();
        $this->assertDatabaseCount('capester_support_requests', 1);
        $this->post($url, array_replace($data, ['capester_description' => 'Different']))->assertStatus(409);
        $other = Event::factory()->create();
        $otherData = $this->data(['capester_description' => 'Different event request.']);
        $this->post(route('backend.capester.store', $other), $otherData)->assertRedirect();
        $response = $this->get(route('backend.capester.index', $event))->assertOk()->assertSee($data['capester_description']);
        $response->assertDontSee($otherData['capester_description']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        if (getenv('CT_CAPESTER_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/capester-queue.html'), $response->getContent());
        }
        $audit = AuditEvent::get()->toJson();
        $this->assertStringNotContainsString($data['capester_description'], $audit);
        $this->assertStringContainsString('[REDACTED]', $audit);
    }

    public function test_receiver_requires_exact_real_bearer_ability_and_current_role(): void
    {
        [$actor, $event] = $this->scenario();
        app(CapesterSupportQueue::class)->submit($actor, $event, $this->data());
        $this->postJson('/api/v1/capester/claim')->assertUnauthorized();
        $wildcard = $actor->createToken('fictional', ['*'])->plainTextToken;
        $this->withToken($wildcard)->postJson('/api/v1/capester/claim')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $wrong = $actor->createToken('fictional', ['capester:other'])->plainTextToken;
        $this->withToken($wrong)->postJson('/api/v1/capester/claim')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $expired = $actor->createToken('fictional', ['capester:receive'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->postJson('/api/v1/capester/claim')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $allowed = $actor->createToken('fictional', ['capester:receive']);
        $this->withToken($allowed->plainTextToken)->postJson('/api/v1/capester/claim')->assertOk()->assertJsonPath('request.event_id', $event->id);
        $actor->removeRole('super-user');
        $this->app['auth']->forgetGuards();
        $this->withToken($allowed->plainTextToken)->postJson('/api/v1/capester/claim')->assertForbidden();
    }

    public function test_owner_isolation_lease_expiry_stale_ack_and_terminal_retry(): void
    {
        [$actor, $event] = $this->scenario();
        [$other] = $this->scenario();
        $queue = app(CapesterSupportQueue::class);
        $record = $queue->submit($actor, $event, $this->data());
        $this->assertNull($queue->claim($other, 2));
        $first = $queue->claim($actor, 1);
        $this->assertNull($queue->claim($actor, 1));
        $this->travel(6)->minutes();
        $second = $queue->claim($actor, 2);
        $this->assertNotSame($first['token'], $second['token']);
        try {
            $queue->acknowledge($actor, 1, $record->uuid, $first['token'], 'received');
            $this->fail('Stale lease accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $queue->acknowledge($actor, 2, $record->uuid, $second['token'], 'received');
        $this->travel(6)->minutes();
        $queue->acknowledge($actor, 2, $record->uuid, $second['token'], 'received');
        $this->assertSame('received', $record->fresh()->status);
        $this->assertNull($queue->claim($actor, 2));
        $this->assertStringNotContainsString($second['token'], AuditEvent::get()->toJson());
    }

    public function test_session_token_disabled_flag_production_hello_and_https_are_guarded(): void
    {
        [$actor, $event] = $this->scenario();
        $this->actingAs($actor)->postJson('/api/v1/capester/claim')->assertForbidden();
        config(['capester.enabled' => false]);
        $this->get(route('backend.capester.index', $event))->assertNotFound();
        config(['capester.enabled' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->app->instance('env', 'production');
        $this->post(route('backend.capester.store', $event), $this->data(['kind' => 'hello']))->assertForbidden();
        $this->postJson('/api/v1/capester/claim')->assertStatus(426);
    }

    public function test_token_and_role_revocation_prevent_delivery_and_acknowledgement(): void
    {
        [$actor, $event] = $this->scenario();
        $queue = app(CapesterSupportQueue::class);
        $record = $queue->submit($actor, $event, $this->data());
        $issued = $actor->createToken('fictional', ['capester:receive']);
        $claim = $this->withToken($issued->plainTextToken)->postJson('/api/v1/capester/claim')->assertOk()->json('request');
        $actor->removeRole('super-user');
        $this->app['auth']->forgetGuards();
        $this->withToken($issued->plainTextToken)->postJson('/api/v1/capester/'.$record->uuid.'/ack', ['token' => $claim['token'], 'result' => 'received'])->assertForbidden();
        $issued->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($issued->plainTextToken)->postJson('/api/v1/capester/claim')->assertUnauthorized();
        $this->assertSame('leased', $record->fresh()->status);
    }

    public function test_pending_cap_and_history_pagination_are_bounded_and_owned(): void
    {
        [$actor, $event] = $this->scenario();
        $queue = app(CapesterSupportQueue::class);
        foreach (range(1, 20) as $_) {
            $queue->submit($actor, $event, $this->data());
        }
        $this->actingAs($actor)->post(route('backend.capester.store', $event), $this->data())->assertStatus(429);
        $response = $this->get(route('backend.capester.index', $event))->assertOk();
        $this->assertCount(20, $response->viewData('items'));
        [$other] = $this->scenario();
        $otherResponse = $this->actingAs($other)->get(route('backend.capester.index', $event))->assertOk();
        $this->assertCount(0, $otherResponse->viewData('items'));
    }

    public function test_acknowledgement_rejects_other_actor_other_pat_and_terminal_overwrite(): void
    {
        [$actor, $event] = $this->scenario();
        [$other] = $this->scenario();
        $queue = app(CapesterSupportQueue::class);
        $record = $queue->submit($actor, $event, $this->data(['kind' => 'hello']));
        $issued = $actor->createToken('fictional', ['capester:receive']);
        $claim = $queue->claim($actor, $issued->accessToken->id);
        $otherToken = $other->createToken('fictional', ['capester:receive']);
        $this->withToken($otherToken->plainTextToken)->postJson('/api/v1/capester/'.$record->uuid.'/ack',
            ['token' => $claim['token'], 'result' => 'hello_passed'])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $differentPat = $actor->createToken('fictional', ['capester:receive']);
        $this->withToken($differentPat->plainTextToken)->postJson('/api/v1/capester/'.$record->uuid.'/ack',
            ['token' => $claim['token'], 'result' => 'hello_passed'])->assertStatus(409);
        $this->app['auth']->forgetGuards();
        $this->withToken($issued->plainTextToken)->postJson('/api/v1/capester/'.$record->uuid.'/ack',
            ['token' => $claim['token'], 'result' => 'hello_passed'])->assertOk();
        $this->withToken($issued->plainTextToken)->postJson('/api/v1/capester/'.$record->uuid.'/ack',
            ['token' => $claim['token'], 'result' => 'hello_failed'])->assertStatus(409);
        $this->assertSame('hello_passed', $record->fresh()->status);
    }
}
