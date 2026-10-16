<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Player;
use App\Models\TeamRegion;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Tests\TestCase;

class TeamSelectionEmailPreviewLayoutTest extends TestCase
{
    private function preview(array $overrides = []): array
    {
        $event = new Event(['name' => 'Synthetic mobile preview event', 'published' => false]);
        $event->id = 901;
        $import = new TeamSelectionImport;
        $import->setRelation('event', $event);
        $invitation = new TeamSelectionInvitation(['event_id' => 901, 'team_id' => 902, 'player_id' => 903]);
        $invitation->id = 904;
        $invitation->setRelation('selectionImport', $import);
        $invitation->setRelation('player', new Player(['name' => 'Synthetic', 'surname' => 'Preview']));
        $invitation->setRelation('region', new TeamRegion(['region_name' => 'Synthetic region']));
        $data = array_replace([
            'invitation' => $invitation,
            'subject' => 'Invitation <review> & mobile layout',
            'campaign' => ['hash' => str_repeat('a', 64), 'from_name' => 'Cape Tennis', 'reply_to' => 'preview@example.test', 'message' => '<script>alert("preview")</script>'],
            'kind' => 'invitation',
            'previewOnly' => false,
        ], $overrides);
        $html = view('backend.team-selection.email-preview', $data)->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        if (getenv('EMAIL_PREVIEW_QA_EXPORT')) {
            $directory = storage_path('framework/testing/email-preview-qa');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            $name = isset($data['customSend']) ? 'custom' : (isset($data['campaignSend']) ? 'campaign' : 'snapshot');
            file_put_contents($directory.'/'.$name.'.html', $html);
        }

        return [$html, $xpath, $data];
    }

    public function test_saved_preview_is_read_only_and_keeps_exact_sandboxed_email(): void
    {
        [$html, $xpath, $data] = $this->preview();
        $this->assertStringContainsString('read-only campaign snapshot', $html);
        $this->assertStringNotContainsString('Sending submits', $html);
        $this->assertSame(0, $xpath->query('//form')->length);
        $frame = $xpath->query('//iframe')->item(0);
        $this->assertTrue($frame->hasAttribute('sandbox'));
        $this->assertSame('', $frame->getAttribute('sandbox'));
        $this->assertSame(view('emails.team-selection.invitation', $data)->render(), $frame->getAttribute('srcdoc'));
        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertSame(1, $xpath->query('//main//iframe')->length);
    }

    public function test_custom_preview_preserves_review_tokens_sender_recipients_and_confirmation(): void
    {
        $send = ['route' => '/synthetic/custom-send', 'sender' => ['from_name' => 'Reviewed sender'], 'invitation_ids' => [904, 905], 'email_subject' => 'Reviewed <subject>', 'email_message' => "Exact message\n<reviewed>", 'preview_hash' => 'exact-hash', 'preview_token' => 'exact-token'];
        [$html, $xpath] = $this->preview(['previewOnly' => true, 'customSend' => $send, 'recipients' => [['name' => 'Synthetic recipient', 'email' => 'recipient@example.test']]]);
        $this->assertStringContainsString('Sending submits', $html);
        $this->assertStringContainsString('Review all 1 recipients', $html);
        $this->assertStringContainsString('Approve and send 2 emails', $html);
        $this->assertSame('/synthetic/custom-send', $xpath->query('//form')->item(0)->getAttribute('action'));
        foreach (['from_name' => 'Reviewed sender', 'email_subject' => $send['email_subject'], 'preview_hash' => 'exact-hash', 'preview_token' => 'exact-token'] as $name => $value) {
            $this->assertSame($value, $xpath->query('//form/input[@name="'.$name.'"]')->item(0)->getAttribute('value'));
        }
        $this->assertSame(2, $xpath->query('//form/input[@name="invitation_ids[]"]')->length);
        $this->assertSame($send['email_message'], $xpath->query('//textarea[@name="email_message"]')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//form/input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="confirm_recipients" and @value="1" and @required]')->length);
    }

    public function test_campaign_review_preserves_scalar_payload_and_variant_email(): void
    {
        [, , $base] = $this->preview();
        $variant = clone $base['invitation'];
        $variant->setRelation('player', new Player(['name' => 'Variant', 'surname' => 'Example']));
        [$html, $xpath] = $this->preview(['previewOnly' => true, 'campaignSend' => ['route' => '/synthetic/campaign-send', 'data' => ['preview_hash' => 'campaign-hash', 'preview_token' => 'campaign-token', 'include_clothing' => 1, 'ignored_array' => [1]]], 'previewVariants' => [['invitation' => $variant, 'kind' => 'custom_status_update']]]);
        $this->assertStringContainsString('Sending submits', $html);
        $this->assertSame('/synthetic/campaign-send', $xpath->query('//form')->item(0)->getAttribute('action'));
        $this->assertSame('campaign-token', $xpath->query('//input[@name="preview_token"]')->item(0)->getAttribute('value'));
        $this->assertSame('campaign-hash', $xpath->query('//input[@name="preview_hash"]')->item(0)->getAttribute('value'));
        $this->assertSame('1', $xpath->query('//input[@name="include_clothing"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@name="ignored_array"]')->length);
        $this->assertSame(1, $xpath->query('//form/input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//form//input[@type="checkbox" and @required]')->length);
        $frame = $xpath->query('//iframe')->item(0);
        $this->assertStringContainsString('Hello Variant Example', $frame->getAttribute('srcdoc'));
        $this->assertStringContainsString('Team player update', $frame->getAttribute('srcdoc'));
    }
}
