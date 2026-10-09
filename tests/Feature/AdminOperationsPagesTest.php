<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\Photo;
use App\Models\PhotoFolder;
use App\Models\Player;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOperationsPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['name' => 'Example administrator', 'email' => 'admin@example.test'])->assignRole('admin');
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag());

        return $admin;
    }

    public function test_director_dates_are_truthful_and_edit_uses_canonical_settings(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['name' => 'Example tournament']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $future = EventConvenor::create(['event_id' => $event->id, 'user_id' => $admin->id, 'role' => 'hoof', 'starts_at' => now()->addDay(), 'expires_at' => now()->addDays(2)]);
        $activeUser = User::factory()->create(['name' => 'Example supporting director', 'email' => 'director@example.test']);
        $active = EventConvenor::create(['event_id' => $event->id, 'user_id' => $activeUser->id, 'role' => 'hulp']);
        $html = view('backend.convenor.index-convenor', ['event' => $event, 'convenors' => collect([$future, $active])])->render();
        $this->assertStringContainsString('Starts later', $html);
        $this->assertStringContainsString('Within access dates', $html);
        $this->assertStringContainsString(route('admin.events.settings', $event).'#event-directors', $html);
        $this->assertStringNotContainsString('action="'.route('convenor.store').'"', $html);
        $this->assertDatabaseCount('event_convenors', 2);
        $this->fixture('directors', $html);
    }

    public function test_photo_navigation_and_actions_retain_exact_folder_and_event_fields(): void
    {
        $this->admin();
        $event = Event::factory()->create(['name' => 'Example tournament']);
        $folder = (new PhotoFolder())->forceFill(['id' => 1, 'event_id' => $event->id, 'name' => 'Example prize giving folder']);
        $otherFolder = (new PhotoFolder())->forceFill(['id' => 2, 'event_id' => $event->id, 'name' => 'Example court photographs']);
        $photo = (new Photo())->forceFill(['id' => 1, 'event_id' => $event->id, 'folder_id' => $folder->id, 'name' => 'example-photo.png', 'path' => 'example-photo.png', 'size' => 100, 'type' => 'image/png']);
        $folder->setRelation('photos', collect([$photo]))->setRelation('event', $event);
        $otherFolder->setRelation('photos', collect())->setRelation('event', $event);
        $photo->setRelation('folder', $folder);
        $event->setRelation('photoFolders', collect([$folder, $otherFolder]));
        $html = view('backend.photo.eventPhotos', ['event' => $event])->render();
        $this->assertStringContainsString('Rename folder', $html);
        $this->assertStringContainsString(route('photoFolder.show', $folder->id), $html);
        $this->fixture('photos', $html);
        $gallery = view('backend.photo.show', ['event' => $event, 'folder' => $folder, 'photos' => collect([$photo])])->render();
        $this->assertStringContainsString('name="folder_id" id="folder_id" value="'.$folder->id.'"', $gallery);
        $this->assertStringContainsString('name="event_id" id="event_id" value="'.$event->id.'"', $gallery);
        $this->assertStringContainsString('Move selected photos', $gallery);
        $this->assertStringNotContainsString('w-px-500', $gallery);
        $this->assertSame(1, $folder->photos->count());
        $this->fixture('photos-folder', $gallery);
    }

    public function test_directory_remains_server_paged_and_encodes_action_labels(): void
    {
        $this->admin();
        Player::factory()->create(['name' => 'Example " onmouseover="alert(1)', 'surname' => 'O\'Example', 'email' => 'player@example.test', 'cellNr' => '0000000000']);
        Player::factory()->create(['name' => 'Example second player', 'surname' => 'Testing', 'email' => 'second@example.test', 'cellNr' => '0000000001']);
        $page = $this->get(route('player.index'))->assertOk();
        $page->assertSee('serverSide: true', false)->assertSee('lengthMenu: [10, 25, 50, 100]', false)->assertSee('replaceAll', false);
        $response = $this->getJson(route('player.data', ['draw' => 1, 'start' => 0, 'length' => 25]))->assertOk()->assertJsonCount(2, 'data');
        $this->fixture('players', $page->getContent());
        if (getenv('CT_BATCHES3236_QA') === '1') {
            file_put_contents(storage_path('app/batches3236-qa/players-data.json'), $response->getContent());
        }
    }

    public function test_player_edit_retains_validation_inputs_legacy_gender_and_permission(): void
    {
        $admin = $this->admin();
        $player = Player::factory()->create(['name' => 'Example player', 'surname' => 'Testing', 'email' => 'player@example.test', 'cellNr' => '0000000000', 'gender' => 'male', 'coach' => 'Example coach']);
        $this->from(route('player.edit', $player))->patch(route('player.update', $player), ['player_name' => 'Corrected example', 'player_surname' => 'Testing', 'email' => 'bad-email', 'is_player_of_colour' => '0'])->assertSessionHasErrors('email');
        $html = $this->get(route('player.edit', $player))->assertOk()->getContent();
        $this->assertStringContainsString('value="Corrected example"', $html);
        $this->assertStringContainsString('value="male" selected', $html);
        $this->assertStringContainsString('value="bad-email"', $html);
        $this->assertStringContainsString('name="is_player_of_colour"', $html);
        $this->fixture('player-edit', $html);
        $ordinary = User::factory()->create(['name' => 'Example owner', 'email' => 'owner@example.test']);
        $this->actingAs($ordinary);
        $limited = view('backend.player.edit-player', ['player' => $player])->render();
        $this->assertStringNotContainsString('name="is_player_of_colour"', $limited);
        $this->assertSame('male', $player->fresh()->gender);
    }

    public function test_series_common_settings_precede_advanced_rules_and_publication_stays_gated(): void
    {
        $this->admin();
        $series = Series::create(['name' => 'Example series', 'year' => 2026, 'best_num_of_scores' => 2]);
        $html = view('backend.series.series-settings', ['series' => $series, 'positions' => range(1, 10), 'rankTypes' => collect(), 'rankingRulePresets' => collect(), 'activeRankingStatus' => 'calculated', 'hasPublishedRanking' => false, 'reviewCampaign' => null])->render();
        $this->assertLessThan(strpos($html, 'Advanced ranking rules and presets'), strpos($html, 'Ranking Publication'));
        $this->assertStringContainsString('Mark Reviewed', $html);
        $this->assertStringNotContainsString('Publish Rankings', $html);
        $this->assertMatchesRegularExpression('/id="leaderboard_published"[^>]*disabled/s', $html);
        $this->assertStringContainsString('Saving settings does not calculate or publish a new ranking run', $html);
        $this->assertDatabaseCount('series', 1);
        $this->fixture('series-settings', $html);
    }

    private function fixture(string $name, string $html): void
    {
        if (getenv('CT_BATCHES3236_QA') === '1') {
            file_put_contents(storage_path('app/batches3236-qa/'.$name.'.html'), $html);
        }
    }
}
