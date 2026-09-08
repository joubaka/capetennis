<?php

namespace Tests\Feature\Ranking;

use Tests\TestCase;

class RankingReviewPresentationTest extends TestCase
{
    public function test_ranking_page_contains_the_optional_preview_confirm_and_delivery_workflow(): void
    {
        $view = file_get_contents(resource_path('views/backend/ranking/series/list.blade.php'));

        $this->assertStringContainsString('Share for Review', $view);
        $this->assertStringContainsString('Review exact recipient list', $view);
        $this->assertStringContainsString('ranking-review-cutoff', $view);
        $this->assertStringContainsString('ranking-email-preview', $view);
        $this->assertStringContainsString('ranking-review-confirm', $view);
        $this->assertStringContainsString('Retry failed emails', $view);
        $this->assertStringContainsString('Finalize & Publish', $view);
        $this->assertStringContainsString('How to complete this ranking', $view);
        $this->assertStringContainsString('Check scores and ties', $view);
        $this->assertStringContainsString('Share with participants', $view);
        $this->assertStringContainsString('Public leaderboard visibility remains a separate setting.', $view);
        $this->assertStringContainsString('You cannot mark the ranking reviewed until all are confirmed.', $view);
    }

    public function test_series_surfaces_expose_the_default_window_and_optional_shortcut(): void
    {
        $settings = file_get_contents(resource_path('views/backend/series/series-settings.blade.php'));
        $home = file_get_contents(resource_path('views/backend/series/series-home.blade.php'));

        $this->assertStringContainsString('ranking_review_default_hours', $settings);
        $this->assertStringContainsString('Default Reply Window', $settings);
        $this->assertStringContainsString('Share Rankings for Review', $home);
        $this->assertStringContainsString('Finalize & Publish Rankings', $home);
    }

    public function test_ranking_page_has_a_compact_mobile_admin_workflow(): void
    {
        $view = file_get_contents(resource_path('views/backend/ranking/series/list.blade.php'));

        $this->assertStringContainsString('@media (max-width: 767.98px)', $view);
        $this->assertStringContainsString('ranking-mobile-actions', $view);
        $this->assertStringContainsString('ranking-primary-action', $view);
        $this->assertStringContainsString('Review {{ $pendingTieDecisions }} pending', $view);
        $this->assertStringContainsString('ranking-more-actions', $view);
        $this->assertStringContainsString('ranking-process-step-column', $view);
        $this->assertStringContainsString("'is-current-step'", $view);
        $this->assertStringContainsString('ranking-table-wrap', $view);
        $this->assertStringContainsString('ranking-player-row', $view);
        $this->assertStringContainsString('data-label="Event scores"', $view);
        $this->assertStringContainsString('querySelectorAll(\'.rebuild-ranking\')', $view);
        $this->assertStringNotContainsString('id="rebuild-ranking"', $view);
    }

    public function test_email_contains_the_signed_ranking_link_and_explicit_cutoff(): void
    {
        $email = file_get_contents(resource_path('views/emails/ranking-review.blade.php'));

        $this->assertStringContainsString('View provisional rankings', $email);
        $this->assertStringContainsString('Reply cutoff:', $email);
        $this->assertStringContainsString('$reviewUrl', $email);
        $this->assertStringContainsString('Replies will go to', $email);
        $this->assertStringContainsString('$campaign->reply_to', $email);
        $this->assertStringContainsString('ranking-review-message-paragraph', $email);
        $this->assertStringNotContainsString('white-space:pre-line', $email);
    }
}
