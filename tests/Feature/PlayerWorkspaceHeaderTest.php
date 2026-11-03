<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PlayerWorkspaceHeaderTest extends TestCase
{
    public function test_workspace_header_reuses_accessible_slots_and_escapes_identity_text(): void
    {
        $html = Blade::render('<x-player-workspace-header :title="$name" subtitle="Recorded results" heading="h2" heading-id="synthetic-heading" data-synthetic-header><x-slot:summary><p>Saved summary</p></x-slot:summary><x-slot:actions><button>Choose player</button></x-slot:actions><x-slot:navigation><nav aria-label="Synthetic player views"><a href="/record">Playing Record</a></nav></x-slot:navigation></x-player-workspace-header>', ['name' => '<script>Example</script>']);
        $this->assertStringContainsString('player-workspace.css', $html);
        $this->assertStringContainsString('data-synthetic-header', $html);
        $this->assertStringContainsString('id="synthetic-heading"', $html);
        $this->assertStringContainsString('&lt;script&gt;Example&lt;/script&gt;', $html);
        foreach (['ct-workspace-header-summary', 'ct-workspace-header-actions', 'ct-workspace-header-navigation', 'Saved summary', 'Choose player', 'Playing Record'] as $value) $this->assertStringContainsString($value, $html);
        $this->assertStringNotContainsString('<script>Example</script>', $html);
    }

    public function test_all_player_plus_journey_templates_compile_with_shared_header(): void
    {
        foreach (['frontend/my-tennis/rating', 'frontend/my-tennis/pathway-page', 'frontend/my-tennis/event-rating-history',
            'frontend/player-plus/index', 'frontend/player-plus/player', 'frontend/player-plus/orders', 'frontend/player-plus/order', 'frontend/player-plus/terms'] as $view) {
            $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertStringContainsString('<x-player-workspace-header', $source, $view);
            $this->assertNotEmpty(token_get_all(app('blade.compiler')->compileString($source), TOKEN_PARSE));
        }
    }
}
