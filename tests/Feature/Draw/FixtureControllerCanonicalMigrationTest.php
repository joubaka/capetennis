<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function adminUser(): User
    {
        return User::factory()->create()->assignRole("admin");
    }

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge(["locked" => false, "published" => false], $attrs));
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
        return $this->getJson("/backend/fixture/insertResult?" . http_build_query($p));
    }

    private function deleteIndResult(int $id): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/backend/fixture/deleteIndResult/{$id}");
    }

    /** @test */
    public function individualNew_advances_winner_to_parent(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = $this->makeChild($draw, $parent);
        $this->actingAs($this->adminUser());
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id);
    }

    /** @test */
    public function individualNew_RR_stage_does_not_advance_bracket(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = Fixture::factory()->create(["draw_id" => $draw->id, "stage" => "RR", "round" => 1, "match_nr" => 1, "parent_fixture_id" => $parent->id, "registration1_id" => 10, "registration2_id" => 20, "match_status" => 0]);
        $this->actingAs($this->adminUser());
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $parent->refresh();
        $this->assertNull($parent->registration1_id);
    }

    /** @test */
    public function locked_draw_blocks_individualNew(): void
    {
        $draw = $this->makeDraw(["locked" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser());
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertStatus(403);
    }

    /** @test */
    public function published_draw_blocks_individualNew(): void
    {
        $draw = $this->makeDraw(["published" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser());
        $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertStatus(403);
    }

    /** @test */
    public function deleteIndResult_clears_child_winner(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw, ["registration1_id" => 10, "winner_registration" => 10]);
        $child = $this->makeChild($draw, $parent, ["winner_registration" => 10, "match_status" => 3]);
        FixtureResult::factory()->create(["fixture_id" => $child->id, "set_nr" => 1, "registration1_score" => 6, "registration2_score" => 3, "winner_registration" => 10, "loser_registration" => 20]);
        $this->actingAs($this->adminUser());
        $this->deleteIndResult($child->id)->assertSuccessful();
        $child->refresh();
        $this->assertNull($child->winner_registration);
    }

    /** @test */
    public function locked_draw_blocks_deleteIndResult(): void
    {
        $draw = $this->makeDraw(["locked" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw), ["winner_registration" => 10, "match_status" => 3]);
        $this->actingAs($this->adminUser());
        $this->deleteIndResult($child->id)->assertStatus(403);
    }

    /** @test */
    public function published_draw_blocks_deleteIndResult(): void
    {
        $draw = $this->makeDraw(["published" => true]);
        $child = $this->makeChild($draw, $this->makeParent($draw), ["winner_registration" => 10, "match_status" => 3]);
        $this->actingAs($this->adminUser());
        $this->deleteIndResult($child->id)->assertStatus(403);
    }

    /** @test */
    public function mutable_draw_allows_deleteIndResult(): void
    {
        $draw   = $this->makeDraw(["locked" => false, "published" => false]);
        $parent = $this->makeParent($draw, ["registration1_id" => 10, "winner_registration" => 10]);
        $child  = $this->makeChild($draw, $parent, ["winner_registration" => 10, "match_status" => 3]);
        FixtureResult::factory()->create(["fixture_id" => $child->id, "set_nr" => 1, "registration1_score" => 6, "registration2_score" => 3, "winner_registration" => 10, "loser_registration" => 20]);
        $this->actingAs($this->adminUser());
        $this->deleteIndResult($child->id)->assertSuccessful();
    }

    /** @test */
    public function duplicate_save_does_not_duplicate_winner_in_parent(): void
    {
        $draw = $this->makeDraw();
        $parent = $this->makeParent($draw);
        $child = $this->makeChild($draw, $parent);
        $this->actingAs($this->adminUser());
        $params = ["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]];
        $this->insertResult($params)->assertSuccessful();
        $this->insertResult($params)->assertSuccessful();
        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id);
    }

    /** @test */
    public function individualNew_response_contains_fixture_key(): void
    {
        $draw = $this->makeDraw();
        $child = $this->makeChild($draw, $this->makeParent($draw));
        $this->actingAs($this->adminUser());
        $response = $this->insertResult(["type" => "individualNew", "fixture_id" => $child->id, "sets" => [1 => ["player1" => 6, "player2" => 3]]])->assertSuccessful();
        $this->assertArrayHasKey("fixture", $response->json());
    }
}
