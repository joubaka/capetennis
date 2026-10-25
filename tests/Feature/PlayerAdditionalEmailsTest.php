<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerAdditionalEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_forms_render_optional_contacts_for_the_authorized_owner(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['email_2' => 'second@example.test', 'email_3' => 'third@example.test']);
        $owner->players()->attach($player);
        $this->actingAs($owner);
        $create = $this->get(route('player.profile.create'))->assertOk()->assertSee('Email 2 (optional)')->assertSee('Email 3 (optional)');
        $edit = $this->get(route('player.profile.edit', $player))->assertOk()->assertSee('second@example.test')->assertSee('third@example.test');
        $this->actingAs(User::factory()->create(['email' => 'second@example.test']))
            ->get(route('player.edit', $player))->assertForbidden();
        if (getenv('CT_CONTACT_QA') === '1') {
            $directory = storage_path('app/contact-emails-qa');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/create.html', $create->getContent());
            file_put_contents($directory.'/edit.html', $edit->getContent());
        }
    }

    private function details(Player $player): array
    {
        return ['name' => $player->name, 'surname' => $player->surname,
            'dateOfBirth' => '2012-01-01', 'gender' => 'Male', 'cellNr' => '0820000000'];
    }

    public function test_owner_can_save_clear_and_omit_extra_contacts_without_linking_their_accounts(): void
    {
        $owner = User::factory()->create();
        $contact = User::factory()->create();
        $player = Player::factory()->create(['dateOfBirth' => '2012-01-01']);
        $owner->players()->attach($player);
        $this->actingAs($owner)->putJson(route('player.profile.update', $player), $this->details($player) + [
            'email_2' => $contact->email, 'email_3' => 'third@example.com',
        ])->assertOk();
        $this->assertSame($contact->email, $player->fresh()->email_2);
        $this->assertSame('third@example.com', $player->fresh()->email_3);
        $this->assertDatabaseCount('user_players', 1);
        $this->assertFalse($contact->players()->whereKey($player->id)->exists());
        $this->assertArrayNotHasKey('email_2', $player->fresh()->toArray());
        $this->assertArrayNotHasKey('email_3', $player->fresh()->toArray());

        $this->putJson(route('player.profile.update', $player), $this->details($player) + ['email_2' => null])->assertOk();
        $this->assertNull($player->fresh()->email_2);
        $this->assertSame('third@example.com', $player->fresh()->email_3);
    }

    public function test_extra_contact_does_not_authorize_editing_or_expose_profile_contacts(): void
    {
        $contact = User::factory()->create();
        $player = Player::factory()->create(['email_2' => $contact->email, 'email_3' => 'private@example.com']);
        $this->actingAs($contact)->get(route('player.profile.edit', $player))->assertForbidden();
        $this->putJson(route('player.profile.update', $player), $this->details($player) + ['email_3' => 'changed@example.com'])->assertForbidden();
        $this->assertSame('private@example.com', $player->fresh()->email_3);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_extra_contacts_are_validated_and_persisted_when_creating_a_profile(): void
    {
        $user = User::factory()->create();
        $details = ['name' => 'New', 'surname' => 'Contact', 'dateOfBirth' => '2012-01-01', 'gender' => 1];
        $this->actingAs($user)->postJson(route('player.profile.store'), $details + [
            'email_2' => 'invalid', 'email_3' => str_repeat('x', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['email_2', 'email_3']);
        $this->assertDatabaseCount('players', 0);
        $this->post(route('player.profile.store'), $details + [
            'email_2' => 'second@example.com', 'email_3' => 'third@example.com',
        ])->assertRedirect(route('backend.dashboard'));
        $this->assertDatabaseHas('players', ['name' => 'New', 'email_2' => 'second@example.com', 'email_3' => 'third@example.com']);
        $this->assertDatabaseCount('user_players', 1);
    }
}
