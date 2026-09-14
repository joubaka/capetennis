<?php

namespace Tests\Feature;

use Tests\TestCase;

class EventWithdrawalButtonClarityTest extends TestCase
{
    public function test_event_withdrawal_control_pairs_its_cross_with_an_explicit_label(): void
    {
        $template = file_get_contents(resource_path('views/frontend/event/show.blade.php'));

        $this->assertStringContainsString('aria-label="Withdraw entry for {{ $registration->display_name }}"', $template);
        $this->assertStringContainsString('ti ti-x me-1', $template);
        $this->assertStringContainsString('<span class="small">Withdraw</span>', $template);
    }

    public function test_team_withdrawal_control_pairs_its_cross_with_an_explicit_label(): void
    {
        $template = file_get_contents(resource_path('views/frontend/event/partials/profile-team.blade.php'));

        $this->assertStringContainsString('aria-label="Withdraw {{ $playerName }} from this team"', $template);
        $this->assertStringContainsString('ti ti-x me-1', $template);
        $this->assertStringContainsString('</i>Withdraw', $template);
    }
}
