<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventType;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproTemplateParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_links_remain_public_and_management_controls_are_event_scoped(): void
    {
        $event = Event::factory()->create(['eventType' => 13]);
        $file = File::forceCreate(['event_id' => $event->id, 'name' => 'Programme.pdf', 'path' => 'public/files/parity-missing.pdf']);
        $event->load('files');
        $publicLink = route('events.documents.show', [$event, $file]);

        $this->assertDocumentControls($event, false, $publicLink);
        $unrelated = User::factory()->create();
        $unrelated->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => Event::factory()->create()->id, 'user_id' => $unrelated->id]);
        $this->actingAs($unrelated);
        $this->assertDocumentControls($event, false, $publicLink);
        $this->deleteJson('/file/'.$file->id)->assertForbidden();
        $this->assertDatabaseHas('files', ['id' => $file->id, 'event_id' => $event->id]);

        $scorer = User::factory()->create();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $this->actingAs($scorer);
        $this->assertDocumentControls($event, false, $publicLink);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $this->assertDocumentControls($event, true, $publicLink);

        $convenor = User::factory()->create();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $convenor->id, 'role' => 'convenor']);
        $this->actingAs($convenor);
        $this->assertDocumentControls($event, false, $publicLink);
        $convenor->assignRole(Role::findOrCreate('admin', 'web'));
        $this->assertDocumentControls($event, true, $publicLink);

        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate('super-user', 'web'));
        $this->actingAs($super);
        $this->assertDocumentControls($event, true, $publicLink);

        foreach (['default', 'masters'] as $layout) {
            $html = view('frontend.event.eventTypes.'.$layout, [
                'event' => $event, 'mastersInvitations' => collect(), 'invitations' => collect(),
                'mastersRegistrationOpen' => false,
                'sDate' => null, 'eDate' => null,
                'formatEntryLine' => null, 'formatWithdrawalLine' => null,
            ])->render();
            $this->assertStringContainsString('Programme.pdf', $html);
            $this->assertStringNotContainsString('name="myFile"', $html);
            $this->assertStringNotContainsString('deleteFileButton', $html);
        }

        $foreign = Event::factory()->create();
        $this->get(route('events.documents.show', [$foreign, $file]))->assertNotFound();
        $this->assertDatabaseCount('files', 1);
    }

    public function test_event_manager_upload_and_delete_keep_routes_and_record_counts(): void
    {
        $event = Event::factory()->create(['eventType' => 13]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        Storage::fake('local');
        $this->post(route('file.store'), [
            'event_id' => $event->id,
            'myFile' => UploadedFile::fake()->create('interpro-template-parity.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $file = File::sole();
        $this->assertSame($event->id, (int) $file->event_id);
        Storage::disk('local')->assertExists($file->path);

        // Destruction resolves canonical paths, so use a file unique to this
        // test rather than making it operate on any ordinary event document.
        $physicalPath = storage_path('framework/testing/interpro-delete-'.uniqid().'.pdf');
        if (!is_dir(dirname($physicalPath))) mkdir(dirname($physicalPath), 0777, true);
        file_put_contents($physicalPath, 'Synthetic parity test document');
        try {
            $file->forceFill(['path' => $physicalPath])->save();
            $this->deleteJson(route('file.destroy', $file))->assertOk()->assertJson(['success' => true]);
            $this->assertDatabaseCount('files', 0);
            $this->assertFileDoesNotExist($physicalPath);
            $this->deleteJson(route('file.destroy', $file))->assertNotFound();
        } finally {
            if (is_file($physicalPath)) unlink($physicalPath);
        }
    }

    public function test_team_family_with_new_id_prepares_public_venue_shortcuts_and_excludes_other_events(): void
    {
        $type = EventType::forceCreate(['name' => 'Regional team parity', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $draft = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => false]);
        $visible = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1]);
        \App\Models\TeamFixture::create(['draw_id' => $draft->id, 'match_nr' => 1]);
        $foreignDraw = \App\Models\Draw::factory()->create(['published' => true]);
        \App\Models\TeamFixture::create(['draw_id' => $foreignDraw->id, 'match_nr' => 1]);

        $response = $this->get('/events/'.$event->id)->assertOk();
        $this->assertSame('team', $response->viewData('event')->frontend_type_view);
        $this->assertSame([$visible->id], $response->viewData('fixturesPerVenueGrouped')->flatten()->pluck('id')->all());
    }

    private function assertDocumentControls(Event $event, bool $allowed, string $publicLink): void
    {
        $html = view('frontend.event.partials.event-documents', compact('event'))->render();
        $this->assertStringContainsString(e($publicLink), $html);
        $this->assertStringContainsString('Programme.pdf', $html);
        if ($allowed) {
            $this->assertStringContainsString('name="myFile"', $html);
            $this->assertStringContainsString('deleteFileButton', $html);
            $this->assertStringContainsString('accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"', $html);
        } else {
            $this->assertStringNotContainsString('name="myFile"', $html);
            $this->assertStringNotContainsString('deleteFileButton', $html);
        }
    }
}
