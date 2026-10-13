<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, Event, SelectionPreviewGrant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectionPreviewShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_is_idempotent_account_scoped_read_only_and_has_exact_canonical_email_links(): void
    {
        [$event, $actor] = $this->scenario();
        $recipients = User::factory()->count(2)->create();
        $url = route('backend.selection-preview-share.store', $event);
        $input = ['request_key' => (string) Str::uuid(), 'recipient_ids' => $recipients->pluck('id')->all(), 'preview_url' => 'https://attacker.invalid', 'body' => 'forged'];
        $this->postJson($url, $input)->assertOk()->assertJsonPath('recipients', 2)->assertJsonPath('queued', 2)->assertJsonPath('accepted', 0);
        $this->postJson($url, $input)->assertOk();
        $this->assertDatabaseCount('selection_preview_grants', 2);
        $this->assertDatabaseCount('selection_preview_share_batches', 1);
        $this->assertDatabaseCount('selection_preview_access_events', 2);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        foreach (BulkEmailLog::all() as $log) {
            $this->assertContains($log->recipient_email, $recipients->pluck('email')->all());
            $this->assertSame(rtrim(config('app.url'), '/').route('backend.selection-preview.show', $event, false), $log->payload['preview_url']);
            $this->assertStringNotContainsString('attacker.invalid', $log->payload['body']);
            $this->assertSame('selection_preview_share', $log->mail_type);
        }
        $this->postJson($url, array_replace($input, ['recipient_ids' => [$recipients[0]->id]]))->assertConflict();
        $viewer = $recipients->first();
        $this->actingAs($viewer)->get(route('backend.selection-preview.show', $event))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('id="preview-team-setup"', false)->assertDontSee('Share preview')->assertDontSee('Back to Result Ranks');
        $this->putJson(route('backend.team-selection-size.store', $event), ['team_size' => 10, 'reserve_count' => 2])->assertForbidden();
        $this->putJson(route('backend.team-result-selection.store', $event), [])->assertForbidden();
        $this->postJson($url, $input)->assertForbidden();
        $this->get(route('backend.selection-preview-share.index', $event))->assertForbidden();
        $other = Event::factory()->create();
        $this->get(route('backend.selection-preview.show', $other))->assertForbidden();
        $this->assertFalse($viewer->can('event.manage', $event));
        $this->actingAs($actor)->deleteJson(route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $viewer]))->assertOk();
        $this->actingAs($viewer)->get(route('backend.selection-preview.show', $event))->assertForbidden();
        $this->assertDatabaseCount('selection_preview_access_events', 3);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        Mail::assertNothingSent();
    }

    public function test_sender_authorization_recipient_validation_pagination_and_regrant_are_bounded(): void
    {
        [$event, $actor] = $this->scenario();
        $users = User::factory()->count(17)->create(['name' => 'Convener']);
        $url = route('backend.selection-preview-share.store', $event);
        $this->get(route('backend.selection-preview-share.index', ['event' => $event, 'q' => 'Convener']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee($users[0]->email)->assertSee('Send preview link');
        foreach ([[], [999999], [$users[0]->id, $users[0]->id], $users->pluck('id')->concat(range(9000, 9014))->all()] as $ids) {
            $this->postJson($url, ['request_key' => (string) Str::uuid(), 'recipient_ids' => $ids])->assertUnprocessable();
        }
        $input = ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$users[0]->id]];
        $this->postJson($url, $input)->assertOk();
        $revoke = route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $users[0]]);
        $this->deleteJson($revoke)->assertOk();
        $this->deleteJson($revoke)->assertOk();
        $this->postJson($url, $input)->assertOk(); // Replaying an old request does not regrant revoked access.
        $this->assertNotNull(SelectionPreviewGrant::first()->revoked_at);
        $input['request_key'] = (string) Str::uuid();
        $this->postJson($url, $input)->assertOk();
        $this->assertNull(SelectionPreviewGrant::first()->revoked_at);
        $this->assertDatabaseCount('selection_preview_access_events', 3);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $other = Event::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $other), $input)->assertForbidden();
        $this->deleteJson(route('backend.selection-preview-share.destroy', ['event' => $other, 'user' => $users[0]]))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_mail_builder_checks_revoked_grant_and_current_recipient_email_before_send(): void
    {
        [$event] = $this->scenario();
        $user = User::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $event), ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$user->id]])->assertOk();
        $log = BulkEmailLog::first();
        $job = new class($log->id) extends SendBulkEmailJob {
            public function mailFor(BulkEmailLog $log) { return $this->buildMailable($log, 'test@example.com'); }
        };
        $this->assertInstanceOf(\App\Mail\BulkEventMail::class, $job->mailFor($log));
        $user->update(['email' => 'changed@example.com']);
        $this->assertNull($job->mailFor($log));
        $user->update(['email' => $log->recipient_email]);
        SelectionPreviewGrant::first()->update(['revoked_at' => now()]);
        $this->assertNull($job->mailFor($log));
        Mail::assertNothingSent();
    }

    public function test_canonical_share_passes_event_email_review_and_tampered_content_is_rejected(): void
    {
        [$event] = $this->scenario();
        $event->update(['eventType' => 3]);
        $user = User::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $event), ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$user->id]])->assertOk();
        $log = BulkEmailLog::first();
        $log->update(['status' => 'sending']);
        $mailable = app(\App\Services\SelectionPreviewShareService::class)->authorizedMailable($log);
        $message = (new \Symfony\Component\Mime\Email)->to($user->email)->subject($mailable->subjectLine)->html($mailable->body);
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        $this->assertNull($listener->handle(new \Illuminate\Mail\Events\MessageSending($message, ['selection_preview_log_id' => $log->id, 'review_event' => $event])));
        $message->html('<p>Unapproved content</p>');
        try {
            $listener->handle(new \Illuminate\Mail\Events\MessageSending($message, ['selection_preview_log_id' => $log->id]));
            $this->fail('Changed preview content must not be sent.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
        Mail::assertNothingSent();
    }

    public function test_account_search_matches_partial_name_surname_email_and_login_case_insensitively_with_literal_wildcards(): void
    {
        [$event] = $this->scenario();
        $person = User::factory()->create(['name' => 'Aldred', 'userSurname' => 'Burden', 'userName' => 'AceLogin', 'email' => 'aldred.tennis@example.test']);
        $wild = User::factory()->create(['name' => 'Wild%Card', 'email' => 'wild@example.test']);
        $invalid = User::factory()->create(['name' => 'Invalid email person', 'email' => 'not-an-email']);
        $url = route('backend.selection-preview-share.index', $event);
        foreach (['aLD', 'BuR', 'Ald  burden', 'tennis@exam', 'ACELOG', 'A'] as $query) {
            $response = $this->getJson($url.'?q='.urlencode($query))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertContains($person->id, array_column($response->json('users'), 'id'));
            $this->assertContains($person->email, array_column($response->json('users'), 'email'));
            $this->assertArrayNotHasKey('masked_email', $response->json('users.0'));
            $this->get($url.'?q='.urlencode($query))->assertOk()->assertSee($person->email);
        }
        $this->getJson($url.'?q=%25')->assertOk()->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $wild->id);
        $this->getJson($url.'?q=Invalid')->assertOk()->assertJsonPath('users.0.selectable', false);
        User::factory()->count(17)->create(['name' => 'Pagination Example']);
        $this->getJson($url.'?q=Pagination')->assertOk()->assertJsonCount(15, 'users')->assertJsonPath('pagination.total', 17);
        $this->getJson($url.'?q=Pagination&users_page=2')->assertOk()->assertJsonCount(2, 'users');
        $html = $this->get($url.'?q=Pagination')->assertOk()->assertSee('data-share-search', false)->assertSee('Next')->getContent();
        $start = strpos($html, '<div class="preview-share-page');
        $end = strpos($html, '</script>', strpos($html, '<script src=', $start)) + strlen('</script>');
        $body = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('<svg', $body);
        if ($output = getenv('CT_SHARE_QA_OUTPUT')) {
            $body = str_replace($url, '/index.html', $body);
            $users = User::where('name', 'Pagination Example')->orderBy('id')->get()->map(fn ($user, $index) => ['id' => $user->id, 'name' => ['Aldred Burden', 'Leonie Adams', 'Casey Jacobs'][$index % 3].' '.($index + 1), 'email' => 'person@example.test', 'selectable' => true, 'shared' => false])->all();
            $script = file_get_contents(public_path('js/selection-preview-share.js'));
            $mock = 'const qaUsers = '.json_encode($users).'; window.fetch = async url => { const p = new URL(url, location.href).searchParams; const q=(p.get("q")||"").toLowerCase(); const found=qaUsers.filter(u=>!q||u.name.toLowerCase().includes(q)||u.email.toLowerCase().includes(q)); const page=Number(p.get("users_page")||1); const link=n=>"?q="+encodeURIComponent(q)+"&users_page="+n; return {ok:true,json:async()=>({users:found.slice((page-1)*15,page*15),pagination:{current_page:page,last_page:Math.max(1,Math.ceil(found.length/15)),total:found.length,previous_page_url:page>1?link(page-1):null,next_page_url:page*15<found.length?link(page+1):null}})}; }; document.querySelector("[data-share-search] input").value=""; document.querySelector("[data-share-send]").addEventListener("submit",e=>{e.preventDefault(); alert("QA fixture: no email is sent.");});';
            $body = preg_replace('/<script src="[^"]*selection-preview-share\.js[^>]*><\/script>/', '<script>'.$mock.$script.'document.querySelector("[data-share-search]").dispatchEvent(new Event("submit", {bubbles:true,cancelable:true}));</script>', $body);
            $css = file_get_contents(public_path('assets/vendor/css/core.css')).file_get_contents(public_path('css/selection-preview-share.css'));
            if (! is_dir(dirname($output))) mkdir(dirname($output), 0777, true);
            file_put_contents($output, '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'body{background:#f3f6fa;font-family:Arial,sans-serif}</style></head><body>'.$body.'</body></html>');
        }
        $this->actingAs(User::factory()->create())->getJson($url.'?q=aldred')->assertForbidden();
    }

    public function test_whatsapp_prepares_selected_account_access_without_email_and_cannot_authorize_mail(): void
    {
        [$event] = $this->scenario();
        $viewer = User::factory()->create();
        $input = ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$viewer->id], 'channel' => 'whatsapp', 'preview_url' => 'https://attacker.invalid'];
        $url = route('backend.selection-preview-share.store', $event);
        $response = $this->postJson($url, $input)->assertOk()->assertJsonPath('channel', 'whatsapp')->assertJsonPath('queued', 0)->assertJsonPath('accepted', 0);
        $canonical = rtrim(config('app.url'), '/').route('backend.selection-preview.show', $event, false);
        $this->assertSame($canonical, $response->json('preview_url'));
        $this->assertStringStartsWith('https://wa.me/?text=', $response->json('whatsapp_url'));
        $this->assertStringContainsString($canonical, rawurldecode($response->json('whatsapp_url')));
        $this->postJson($url, $input)->assertOk();
        $this->postJson($url, array_replace($input, ['channel' => 'email']))->assertConflict();
        $this->assertDatabaseCount('selection_preview_grants', 1);
        $this->assertDatabaseCount('selection_preview_access_events', 1);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNotDispatched(SendBulkEmailJob::class);
        $this->assertSame('shared_link', DB::table('selection_preview_access_events')->value('action'));
        $forged = BulkEmailLog::create(['mail_type' => 'selection_preview_share', 'related_type' => Event::class, 'related_id' => $event->id,
            'recipient_email' => $viewer->email, 'status' => 'queued', 'payload' => ['preview_share_batch_id' => $response->json('batch_id'), 'recipient_user_id' => $viewer->id, 'event_id' => $event->id]]);
        $this->assertNull(app(\App\Services\SelectionPreviewShareService::class)->authorizedMailable($forged));
        $this->actingAs($viewer)->get(route('backend.selection-preview.show', $event))->assertOk();
        $this->postJson($url, $input)->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_player_search_resolves_only_linked_accounts_and_grant_pages_stay_event_scoped(): void
    {
        [$event, $actor] = $this->scenario();
        $linked = User::factory()->create();
        $direct = User::factory()->create();
        $player = \App\Models\Player::factory()->create(['name' => 'UniqueChild', 'surname' => 'Lookup']);
        $linked->players()->attach($player);
        \App\Models\Player::factory()->create(['name' => 'UniqueChild', 'surname' => 'Lookup', 'userId' => $direct->id]);
        \App\Models\Player::factory()->create(['name' => 'UniqueChild', 'surname' => 'Lookup']);
        $url = route('backend.selection-preview-share.index', $event);
        $response = $this->getJson($url.'?q=uniquechild%20lookup')->assertOk()->assertJsonCount(2, 'users');
        $this->assertEqualsCanonicalizing([$linked->id, $direct->id], array_column($response->json('users'), 'id'));
        $this->assertDatabaseCount('selection_preview_grants', 0);
        $this->assertDatabaseCount('user_players', 1);
        $users = User::factory()->count(17)->create();
        $this->postJson(route('backend.selection-preview-share.store', $event), ['request_key' => (string) Str::uuid(), 'recipient_ids' => $users->pluck('id')->all(), 'channel' => 'whatsapp'])->assertOk();
        $this->getJson($url.'?section=grants')->assertOk()->assertJsonCount(15, 'grants')->assertJsonPath('pagination.total', 17)->assertJsonPath('grants.0.email', $users->last()->email);
        $this->getJson($url.'?section=grants&grants_page=2')->assertOk()->assertJsonCount(2, 'grants');
        $foreign = Event::factory()->create();
        $this->getJson(route('backend.selection-preview-share.index', $foreign).'?section=grants')->assertForbidden();
        $this->actingAs($linked)->getJson($url.'?section=grants')->assertForbidden();
        Bus::assertNotDispatched(SendBulkEmailJob::class);
    }

    private function scenario(): array
    {
        Bus::fake(); Mail::fake(); $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $actor->adminEvents()->attach($event);
        $this->actingAs($actor);
        return [$event, $actor];
    }
}
