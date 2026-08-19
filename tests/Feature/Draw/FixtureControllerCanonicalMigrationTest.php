<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FixtureControllerCanonicalMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(["name" => "super-user", "guard_name" => "web"]);
        Role::firstOrCreate(["name" => "admin",      "guard_name" => "web"]);
    }

    private function adminUser(Draw $draw): User
    {
        $user = User::factory()->create()->assignRole("admin");
        DB::table('event_admins')->insert([
            'event_id' => $draw->event_id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            "event_id" => Event::factory()->create()->id,
            "locked" => false,
            "published" => false,
        ], $attrs));
    }

    private function makeParent(Draw $draw, array $attrs = []): Fixture
    {
        return Fixture::factory()->create(array_merge(["draw_id" => $draw->id, "stage" => "MAIN", "round" => 2, "match_nr" => 200, "match_status" => 0], $attrs));
    }

    private function makeChild(Draw $draw, Fixture $parent, array $attrs = []): Fixture
    {
        return Fixture::factory()->create(array_merge(["draw_id" => $draw->id, "stage" => "MAIN", "round" => 1, "match_nr" => 1, "parent_fixture_id" => $parent->id, "registration1_id" => 10, "registration2_id" => 20, "match_status" => 0], $attrs));
    }

    private function insertResult(array $p): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/backend/fixture/insertResult', $p);
    }

    private function deleteIndResult(int $id): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/backend/fixture/deleteIndResult/{$id}");
    }
    public function test_individualNew_advances_winner_to_parent(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = $this->makeChild($draw, $parent);
        $this->actingAs($this->adminUser($draw));
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id);
    }
    public function test_individualNew_RR_stage_does_not_advance_bracket(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = Fixture::factory()->create(["draw_id" => $draw->id, "stage" => "RR", "round" => 1, "match_nr" => 1, "parent_fixture_id" => $parent->id, "registration1_id" => 10, "registration2_id" => 20, "match_status" => 0]);
        $this->actingAs($this->adminUser($draw));
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $parent->refresh();
        $this->assertNull($parent->registration1_id);
    }
    public function test_locked_draw_blocks_individualNew(): void
    {
        $draw = $this->makeDraw(["locked" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser($draw));
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertStatus(403);
    }
    public function test_published_draw_blocks_individualNew(): void
    {
        $draw = $this->makeDraw(["published" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser($draw));
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertStatus(403);
    }
    public function test_deleteIndResult_clears_child_winner(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw, ["registration1_id" => 10, "winner_registration" => 10]);
        $child = $this->makeChild($draw, $parent, ["winner_registration" => 10, "match_status" => 3]);
        FixtureResult::factory()->create(["fixture_id" => $child->id, "set_nr" => 1, "registration1_score" => 6, "registration2_score" => 3, "winner_registration" => 10, "loser_registration" => 20]);
        $this->actingAs($this->adminUser($draw));
        $this->deleteIndResult($child->id)->assertSuccessful();
        $child->refresh();
        $this->assertNull($child->winner_registration);
    }
    public function test_locked_draw_blocks_deleteIndResult(): void
    {
        $draw = $this->makeDraw(["locked" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw), ["winner_registration" => 10, "match_status" => 3]);
        $this->actingAs($this->adminUser($draw));
        $this->deleteIndResult($child->id)->assertStatus(403);
    }
    public function test_published_draw_blocks_deleteIndResult(): void
    {
        $draw = $this->makeDraw(["published" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw), ["winner_registration" => 10, "match_status" => 3]);
        $this->actingAs($this->adminUser($draw));
        $this->deleteIndResult($child->id)->assertStatus(403);
    }
    public function test_mutable_draw_allows_deleteIndResult(): void
    {
        $draw   = $this->makeDraw(["locked" => false, "published" => false]);
        $parent = $this->makeParent($draw, ["registration1_id" => 10, "winner_registration" => 10]);
        $child  = $this->makeChild($draw, $parent, ["winner_registration" => 10, "match_status" => 3]);
        FixtureResult::factory()->create(["fixture_id" => $child->id, "set_nr" => 1, "registration1_score" => 6, "registration2_score" => 3, "winner_registration" => 10, "loser_registration" => 20]);
        $this->actingAs($this->adminUser($draw));
        $this->deleteIndResult($child->id)->assertSuccessful();
    }
    public function test_duplicate_save_does_not_duplicate_winner_in_parent(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = $this->makeChild($draw, $parent);
        $this->actingAs($this->adminUser($draw));
        $params = ["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]];
        $this->insertResult($params)->assertSuccessful();
        $this->insertResult($params)->assertSuccessful();
        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id);
    }
    public function test_individualNew_response_contains_fixture_key(): void
    {
        $draw = $this->makeDraw();
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser($draw));
        $response = $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $this->assertArrayHasKey("fixture", $response->json());
    }
}
