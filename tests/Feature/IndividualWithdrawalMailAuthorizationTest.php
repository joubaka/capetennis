<?php

namespace Tests\Feature;

use App\Mail\{DailyWithdrawalSummaryMail, MastersAdminUpdateMail, MastersInvitationMail, WithdrawalAdminMail, WithdrawalPlayerMail};
use App\Models\{CategoryEventRegistration, MastersInvitation, MastersInvitationBatch, Player, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualWithdrawalMailAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): CategoryEventRegistration
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array'], 'queue.default' => 'sync']);
        $actor = User::factory()->create(['name' => 'Parent Account']);
        $entry = CategoryEventRegistration::factory()->withdrawn()->create([
            'withdrawn_by' => $actor->id, 'withdrawn_at' => '2026-11-20 10:15:30',
        ]);
        $player = Player::factory()->create(['name' => 'Junior', 'surname' => 'Player', 'email' => 'junior@example.test']);
        DB::table('player_registrations')->insert(['registration_id' => $entry->registration_id, 'player_id' => $player->id]);
        return $entry->fresh();
    }

    public function test_individual_admin_and_custom_player_notices_reach_transport_with_actual_facts(): void
    {
        $entry = $this->entry();
        $admin = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $entry->categoryEvent->event_id, 'user_id' => $admin->id]);
        DB::table('site_settings')->updateOrInsert(['key' => 'player_email_body_withdrawal'], ['value' => 'Custom confirmation.']);
        Mail::mailer('array')->to($admin->email)->send(new WithdrawalAdminMail($entry));
        Mail::mailer('array')->to('junior@example.test')->send(new WithdrawalPlayerMail($entry));
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $body = $message->getOriginalMessage()->getHtmlBody();
            $this->assertStringContainsString('Junior Player', $body);
            $this->assertStringContainsString('Parent Account', $body);
            $this->assertStringContainsString('20 Nov 2026 10:15:30', $body);
            $this->assertStringContainsString(config('app.timezone'), $body);
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
    }

    public function test_unrelated_event_admin_cannot_receive_automatic_individual_notice(): void
    {
        $entry = $this->entry();
        $unrelated = User::factory()->create();
        $other = CategoryEventRegistration::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $other->categoryEvent->event_id, 'user_id' => $unrelated->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Mail::mailer('array')->to($unrelated->email)->send(new WithdrawalAdminMail($entry));
    }

    public function test_daily_summary_reaches_transport_with_actual_actor_and_time(): void
    {
        $entry = $this->entry();
        $admin = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $entry->categoryEvent->event_id, 'user_id' => $admin->id]);
        $from = $entry->withdrawn_at->copy()->startOfDay();
        Mail::mailer('array')->to($admin->email)->send(new DailyWithdrawalSummaryMail(
            $entry->categoryEvent->event, collect([$entry]), $from, $from->copy()->addDay()));
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $body = $messages->first()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('Parent Account', $body);
        $this->assertStringContainsString('20 Nov 2026 10:15:30', $body);
        $this->assertDatabaseCount('event_communication_batches', 0);
    }

    public function test_modified_plain_text_alternative_is_rejected_before_transport(): void
    {
        $entry = $this->entry();
        $mail = (new WithdrawalPlayerMail($entry))->withSymfonyMessage(fn ($message) => $message->text('Unapproved alternate message.'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Mail::mailer('array')->to('junior@example.test')->sendNow($mail);
    }

    public function test_disabled_player_notices_cannot_use_automatic_transport_exception(): void
    {
        $entry = $this->entry();
        \App\Models\SiteSetting::set('player_email_on_withdrawal', '0', \App\Models\SiteSetting::GROUP_EMAIL);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Mail::mailer('array')->to('junior@example.test')->send(new WithdrawalPlayerMail($entry));
    }

    public function test_masters_withdrawal_variants_identify_the_actual_actor_and_time(): void
    {
        $entry = $this->entry();
        $invitation = new MastersInvitation(['id' => 987, 'registration_id' => $entry->registration_id,
            'category_event_id' => $entry->category_event_id, 'player_id' => $entry->players->first()->id,
            'status' => MastersInvitation::WITHDRAWN, 'withdrawn_at' => $entry->withdrawn_at]);
        $batch = new MastersInvitationBatch(['event_id' => $entry->categoryEvent->event_id]);
        $batch->setRelation('event', $entry->categoryEvent->event);
        $invitation->setRelation('batch', $batch)->setRelation('categoryEvent', $entry->categoryEvent)->setRelation('player', $entry->players->first());
        foreach ([new MastersAdminUpdateMail($invitation, 'withdrawn'), new MastersInvitationMail($invitation, 'withdrawn'),
            new MastersInvitationMail($invitation, 'withdrawn', ['notification_only' => true])] as $mail) {
            $body = $mail->render();
            $this->assertStringContainsString('Junior Player', $body);
            $this->assertStringContainsString('Parent Account', $body);
            $this->assertStringContainsString('20 Nov 2026 10:15:30', $body);
            $this->assertStringNotContainsString('You are invited to take part', $body);
        }
    }
}
