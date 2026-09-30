<?php

namespace Tests\Unit;

use App\Mail\InterprovincialTrialInvitationMail;
use App\Mail\MastersInvitationMail;
use App\Models\InterprovincialTrialInvitation;
use App\Models\MastersInvitation;
use App\Services\InvitationMailSecurity;
use Tests\TestCase;

class InvitationMailCompositionTest extends TestCase
{
    public function test_review_proof_is_bound_to_actor_scope_token_and_expiry(): void
    {
        $security = new InvitationMailSecurity;
        $proof = $security->reviewProof(10, 'masters:4:8', 'token', 'recipients', 'composition', 12345);
        $this->assertNotSame($proof, $security->reviewProof(11, 'masters:4:8', 'token', 'recipients', 'composition', 12345));
        $this->assertNotSame($proof, $security->reviewProof(10, 'masters:5:8', 'token', 'recipients', 'composition', 12345));
        $this->assertNotSame($proof, $security->reviewProof(10, 'masters:4:8', 'other', 'recipients', 'composition', 12345));
    }

    public function test_interprovincial_mail_uses_snapshotted_headers(): void
    {
        $mail = new InterprovincialTrialInvitationMail(new InterprovincialTrialInvitation, [
            'subject' => 'Custom trials invitation',
            'from_address' => 'trials@example.test',
            'from_name' => 'Cape Tennis Trials',
            'reply_to' => 'reply@example.test',
        ]);

        $envelope = $mail->envelope();
        $this->assertSame('Custom trials invitation', $envelope->subject);
        $this->assertSame('trials@example.test', $envelope->from->address);
        $this->assertSame('Cape Tennis Trials', $envelope->from->name);
        $this->assertSame('reply@example.test', $envelope->replyTo[0]->address);
    }

    public function test_only_initial_masters_invitation_uses_custom_composition(): void
    {
        $payload = ['subject' => 'Custom Masters invitation', 'from_address' => 'masters@example.test', 'from_name' => 'Masters Desk', 'reply_to' => 'reply@example.test'];
        $initial = new MastersInvitationMail(new MastersInvitation, 'invitation', $payload);
        $replacement = new MastersInvitationMail(new MastersInvitation, 'replacement', $payload);

        $this->assertSame('Custom Masters invitation', $initial->envelope()->subject);
        $this->assertSame('masters@example.test', $initial->envelope()->from->address);
        $this->assertSame('reply@example.test', $initial->envelope()->replyTo[0]->address);
        $this->assertSame('Cape Tennis Masters replacement invitation', $replacement->envelope()->subject);
    }
}
