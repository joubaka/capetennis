<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Models\User;
use App\Services\TeamSelection\TeamSelectionContactService;
use Tests\TestCase;

class PlayerUpdateContactTest extends TestCase
{
    public function test_update_contacts_are_valid_normalized_and_deduplicated_without_changing_invitation_contact(): void
    {
        $player = new Player;
        $player->forceFill(['email' => 'PRIMARY@example.test', 'email_2' => ' SECOND@example.test ', 'email_3' => 'third@example.test']);
        $owner = (new User)->forceFill(['email' => 'primary@example.test']);
        $parent = (new User)->forceFill(['email' => 'second@example.test']);
        $invalid = (new User)->forceFill(['email' => 'invalid']);
        $player->setRelation('user', $owner)->setRelation('users', collect([$parent, $invalid]));
        $contacts = new TeamSelectionContactService;

        $this->assertSame(['primary@example.test', 'second@example.test', 'third@example.test'], $contacts->emails($player)->all());
        $this->assertSame('primary@example.test', $contacts->primaryEmail($player));
        $this->assertSame(2, $player->users->count());
    }

    public function test_update_only_addresses_never_become_actionable_invitation_fallbacks(): void
    {
        $player = (new Player)->forceFill(['email' => null, 'email_2' => 'updates@example.test', 'email_3' => 'invalid']);
        $player->setRelation('user', null)->setRelation('users', collect());
        $contacts = new TeamSelectionContactService;

        $this->assertSame(['updates@example.test'], $contacts->emails($player)->all());
        $this->assertNull($contacts->primaryEmail($player));
        $this->assertSame([], $contacts->emails(null)->all());
    }
}
