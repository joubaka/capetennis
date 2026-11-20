<?php

namespace Tests\Feature;

use App\Models\{Announcement, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlusAnnouncementReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Public event');
            $table->boolean('published');
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
            $table->text('message');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2026_11_20_120000_create_plus_morning_digests.php'))->up();
        DB::table('events')->insert([['id' => 1, 'published' => true], ['id' => 2, 'published' => false]]);
        foreach ([[1, 1, null], [2, 2, null], [3, 1, now()]] as [$id, $eventId, $deleted]) {
            DB::table('announcements')->insert(['id' => $id, 'event_id' => $eventId, 'title' => 'Notice',
                'message' => 'Public information', 'deleted_at' => $deleted]);
        }
    }

    public function test_reads_require_authentication_and_record_only_the_actor_and_exact_revision_idempotently(): void
    {
        $entry = ['id' => 1, 'revision' => Announcement::findOrFail(1)->readRevision()];
        $this->postJson(route('announcement-reads'), ['entries' => [$entry]])->assertUnauthorized();
        $user = new User;
        $user->id = 7;
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson(route('announcement-reads'), ['entries' => [$entry], 'user_id' => 99])->assertNoContent();
        }
        $this->assertDatabaseCount('plus_digest_seen', 1);
        $this->assertDatabaseHas('plus_digest_seen', ['user_id' => 7, 'key' => 'announcement:1', 'revision' => $entry['revision']]);
        $this->assertDatabaseMissing('plus_digest_seen', ['user_id' => 99]);
        $this->getJson(route('announcement-inbox'))->assertOk()->assertJsonPath('0.read', true);
        $other = new User;
        $other->id = 8;
        $this->actingAs($other)->getJson(route('announcement-inbox'))->assertOk()->assertJsonPath('0.read', false);
    }

    public function test_private_deleted_and_stale_announcements_cannot_be_marked_and_batches_validate_before_writing(): void
    {
        $user = new User;
        $user->id = 7;
        $this->actingAs($user);
        $valid = ['id' => 1, 'revision' => Announcement::findOrFail(1)->readRevision()];
        foreach ([2, 3, 999] as $id) {
            $this->postJson(route('announcement-reads'), ['entries' => [$valid, ['id' => $id, 'revision' => 'stale']]])->assertNotFound();
            $this->assertDatabaseCount('plus_digest_seen', 0);
        }
        $this->postJson(route('announcement-reads'), ['entries' => [['id' => 1, 'revision' => 'stale']]])->assertStatus(409);
        $this->postJson(route('announcement-reads'), ['entries' => array_fill(0, 101, $valid)])->assertUnprocessable();
        $this->assertDatabaseCount('plus_digest_seen', 0);
    }
}
