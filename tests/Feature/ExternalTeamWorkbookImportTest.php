<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\Category;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExternalTeamWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_previews_then_imports_selected_complete_squads_as_unpublished_no_profile_teams(): void
    {
        [$event, $region, $admin] = $this->eventRegionAndAdmin();
        $existingBoysCategory = Category::create(['name' => 'U10 Boys']);

        $preview = $this->actingAs($admin)->postJson(
            route('backend.region.teams.import.no.profile', [$event, $region]),
            [
                'file' => $this->workbook(),
                'expected_players' => 8,
                'team_prefix' => 'ZFM',
            ]
        );

        $preview->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('selected_sheet', 'Selected squads')
            ->assertJsonPath('complete_team_count', 2)
            ->assertJsonPath('complete_player_count', 16)
            ->assertJsonCount(3, 'teams')
            ->assertJsonPath('teams.0.category', 'Boys U10')
            ->assertJsonPath('teams.0.selectable', true)
            ->assertJsonPath('teams.2.category', 'Boys U11')
            ->assertJsonPath('teams.2.selectable', false);

        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);

        $confirmed = $this->actingAs($admin)->postJson(
            route('backend.region.teams.import.no.profile', [$event, $region]),
            [
                'file' => $this->workbook(),
                'expected_players' => 8,
                'team_prefix' => 'ZFM',
                'sheet_name' => 'Selected squads',
                'confirmed' => 1,
                'selected_team_keys' => ['boys-u10', 'girls-u10'],
            ]
        );

        $confirmed->assertOk()
            ->assertJsonPath('requires_confirmation', false)
            ->assertJsonPath('team_count', 2)
            ->assertJsonPath('player_count', 16);

        $this->assertDatabaseHas('teams', [
            'name' => 'ZFM Boys U10',
            'region_id' => $region->id,
            'num_team_members' => 8,
            'noProfile' => 1,
            'published' => 0,
        ]);
        $this->assertDatabaseHas('teams', [
            'name' => 'ZFM Girls U10',
            'region_id' => $region->id,
            'num_team_members' => 8,
            'noProfile' => 1,
            'published' => 0,
        ]);
        $this->assertDatabaseCount('category_events', 2);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseMissing('categories', ['name' => 'Boys U10']);
        $this->assertDatabaseHas('category_events', [
            'event_id' => $event->id,
            'category_id' => $existingBoysCategory->id,
        ]);
        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseCount('no_profile_team_players', 16);
        $this->assertDatabaseCount('team_players', 16);
        $this->assertDatabaseHas('no_profile_team_players', [
            'rank' => 8,
            'name' => 'Gracie',
            'surname' => 'Verhamme',
            'pay_status' => 0,
        ]);
    }

    public function test_import_is_authorized_and_scoped_to_a_region_attached_to_the_event(): void
    {
        [$event, $region] = $this->eventRegionAndAdmin();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson(
            route('backend.region.teams.import.no.profile', [$event, $region]),
            ['file' => $this->workbook(), 'expected_players' => 8]
        )->assertForbidden();

        $otherEvent = Event::factory()->create(['eventType' => 3]);
        $otherAdmin = User::factory()->create();
        EventAdmin::create(['user_id' => $otherAdmin->id, 'event_id' => $otherEvent->id]);

        $this->actingAs($otherAdmin)->postJson(
            route('backend.region.teams.import.no.profile', [$otherEvent, $region]),
            ['file' => $this->workbook(), 'expected_players' => 8]
        )->assertNotFound();

        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
    }

    private function eventRegionAndAdmin(): array
    {
        $event = Event::factory()->create([
            'eventType' => 3,
            'published' => false,
            'signUp' => false,
            'status' => 'draft',
            'start_date' => '2026-10-09',
        ]);
        $region = TeamRegion::create([
            'region_name' => 'ZF Mcawu Primary Schools 2026',
            'short_name' => 'ZFM',
        ]);
        $event->regions()->attach($region->id);
        $admin = User::factory()->create();
        EventAdmin::create(['user_id' => $admin->id, 'event_id' => $event->id]);

        return [$event, $region, $admin];
    }

    private function workbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $guestList = $spreadsheet->getActiveSheet();
        $guestList->setTitle('Guest list');
        $guestList->setCellValue('B3', 'Seuns o10');
        $guestList->fromArray([
            [1, 'Only', 'One'],
            [2, 'Only', 'Two'],
        ], null, 'A4');

        $selected = $spreadsheet->createSheet();
        $selected->setTitle('Selected squads');
        $selected->setCellValue('B3', 'Seuns o10');
        $selected->setCellValue('G3', 'Dogters 010');
        $selected->fromArray([
            [1, 'Burger', 'Steenkamp', null, null, 1, 'Chrisna', 'De Jager'],
            [2, 'Petri', 'de Kock', null, null, 2, 'Zioné', 'Van Zyl'],
            [3, 'Jano', 'de Kock', null, null, 3, 'Hazel', 'Woolls'],
            [4, 'Reinhardt', 'Huyser', null, null, 4, 'Emke', 'Peens'],
            [5, 'Emile', 'Van der Walt', null, null, 5, 'Milah', 'Liebenberg'],
            [6, 'Mihan', 'Loubser', null, null, 6, 'Caro', 'Louw'],
            [7, 'Hugo', 'Niemand', null, null, 7, 'Lehakwe', 'Mapane'],
            [8, 'André', 'Greyling', null, null, 8, 'Gracie', 'Verhamme'],
        ], null, 'A4');
        $selected->setCellValue('B15', 'Seuns 011');
        $selected->fromArray([
            [1, 'Player', 'One'],
            [2, 'Player', 'Two'],
            [3, 'Player', 'Three'],
            [4, 'Player', 'Four'],
            [5, 'Player', 'Five'],
            [6, 'Player', 'Six'],
            [7, 'Player', 'Seven'],
            [8, '', ''],
        ], null, 'A16');

        $path = tempnam(sys_get_temp_dir(), 'external-team-workbook-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('external-teams.xlsx', $contents);
    }
}
