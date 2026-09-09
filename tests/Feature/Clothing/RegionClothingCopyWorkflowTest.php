<?php

namespace Tests\Feature\Clothing;

use App\Models\ClothingItemType;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\Clothing\RegionClothingCopyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegionClothingCopyWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reviewed_items_prices_and_sizes_are_copied_without_changing_the_source(): void
    {
        $source = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2025']);
        $target = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2026']);
        $shirt = ClothingItemType::create([
            'item_type_name' => 'Overberg Shirt Boys', 'price' => 260,
            'region_id' => $source->id, 'ordering' => 1,
        ]);
        ClothingSize::create(['size' => '9-10', 'item_type' => $shirt->id, 'ordering' => 1]);
        ClothingSize::create(['size' => '11-12', 'item_type' => $shirt->id, 'ordering' => 2]);
        $hoodie = ClothingItemType::create([
            'item_type_name' => 'Overberg Hoodie', 'price' => 460,
            'region_id' => $source->id, 'ordering' => 2,
        ]);

        $rows = [
            ['selected' => true, 'source_item_id' => $shirt->id, 'item_type_name' => 'Overberg Shirt Boys', 'price' => 395, 'ordering' => 1],
            ['selected' => false, 'source_item_id' => $hoodie->id, 'item_type_name' => 'Overberg Hoodie', 'price' => 500, 'ordering' => 2],
        ];
        $service = app(RegionClothingCopyService::class);
        $result = $service->copy($target, $source, $rows, User::factory()->create(), true);

        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $copy = ClothingItemType::where('region_id', $target->id)->firstOrFail();
        $this->assertSame('Overberg Shirt Boys', $copy->item_type_name);
        $this->assertSame(395, (int) $copy->price);
        $this->assertSame(['9-10', '11-12'], $copy->sizes()->pluck('size')->all());
        $this->assertSame(260, (int) $shirt->fresh()->price);
        $this->assertTrue((bool) $target->fresh()->clothing_admin);
        $this->assertFalse((bool) $target->fresh()->clothing_order);

        $second = $service->copy($target, $source, $rows, User::factory()->create(), true);
        $this->assertSame(['created' => 0, 'skipped' => 1], $second);
        $this->assertSame(1, ClothingItemType::where('region_id', $target->id)->count());
    }

    public function test_prices_must_be_explicitly_confirmed(): void
    {
        $source = TeamRegion::create(['region_name' => 'West Coast Primary Schools 2025']);
        $target = TeamRegion::create(['region_name' => 'West Coast Primary Schools 2026']);
        $item = ClothingItemType::create([
            'item_type_name' => 'West Coast Shirt', 'price' => 370, 'region_id' => $source->id,
        ]);

        $this->expectException(ValidationException::class);
        app(RegionClothingCopyService::class)->copy($target, $source, [[
            'selected' => true, 'source_item_id' => $item->id,
            'item_type_name' => $item->item_type_name, 'price' => 370,
        ]], User::factory()->create(), false);
    }

    public function test_empty_current_region_previews_the_matching_previous_year_setup(): void
    {
        $source = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2025']);
        $target = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2026']);
        ClothingItemType::create([
            'item_type_name' => 'Overberg Hoodie', 'price' => 460,
            'region_id' => $source->id, 'ordering' => 1,
        ]);

        $admin = $this->authorizedAdminForRegion($target, 2026);
        $this->actingAs($admin)
            ->get(route('backend.region.clothing.edit', $target))
            ->assertOk()
            ->assertSee('Copy and review last year’s clothing')
            ->assertSee('Overberg Primary Schools 2025')
            ->assertSee('Overberg Hoodie')
            ->assertSee('I reviewed and approve these 2026 selling prices.');
    }

    public function test_event_clothing_hub_orders_regions_and_recommends_matching_previous_catalogues(): void
    {
        Role::findOrCreate('admin', 'web');
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Clothing workflow team event', 'type' => 2, 'code' => 'clothing-workflow-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create([
            'name' => 'Platteland Primary Schools 2027',
            'eventType' => $teamEventType,
            'start_date' => '2027-10-09',
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $target = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2027']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $target->id, 'ordering' => 1]);
        $sourceEvent = Event::factory()->create(['eventType' => $teamEventType, 'start_date' => '2026-10-09']);
        $source = TeamRegion::create(['region_name' => 'Overberg Primary Schools 2026']);
        DB::table('event_regions')->insert(['event_id' => $sourceEvent->id, 'region_id' => $source->id, 'ordering' => 1]);
        ClothingItemType::create([
            'item_type_name' => 'Overberg Hoodie', 'price' => 460,
            'region_id' => $source->id, 'ordering' => 1,
        ]);

        $this->actingAs($admin)->get(route('backend.event.clothing.index', $event))
            ->assertOk()
            ->assertSee('Event Clothing Setup')
            ->assertSee('Overberg Primary Schools 2027')
            ->assertSee('Recommended previous setup:')
            ->assertSee('Overberg Primary Schools 2026')
            ->assertSee(route('backend.region.clothing.edit', [
                'region' => $target->id,
                'source_region' => $source->id,
            ]), false);

        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get(route('backend.event.clothing.index', $event))->assertForbidden();
    }

    public function test_admin_copy_route_requires_price_confirmation_and_keeps_ordering_closed(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create()->assignRole('admin');
        $source = TeamRegion::create(['region_name' => 'West Coast Primary Schools 2025']);
        $target = TeamRegion::create(['region_name' => 'West Coast Primary Schools 2026']);
        $this->attachAdminToRegion($admin, $target, 2026);
        $item = ClothingItemType::create([
            'item_type_name' => 'West Coast Shirt Girls', 'price' => 370,
            'region_id' => $source->id, 'ordering' => 3,
        ]);
        $payload = [
            'source_region_id' => $source->id,
            'items' => [[
                'selected' => 1, 'source_item_id' => $item->id,
                'item_type_name' => $item->item_type_name, 'price' => 400, 'ordering' => 3,
            ]],
        ];

        $this->actingAs($admin)->from(route('backend.region.clothing.edit', $target))
            ->post(route('backend.region.clothing.copy', $target), $payload)
            ->assertSessionHasErrors('confirm_prices');
        $this->assertDatabaseMissing('clothing_item_types', ['region_id' => $target->id]);

        $this->actingAs($admin)->post(route('backend.region.clothing.copy', $target), $payload + ['confirm_prices' => 1])
            ->assertRedirect(route('backend.region.clothing.edit', $target));
        $this->assertDatabaseHas('clothing_item_types', [
            'region_id' => $target->id, 'item_type_name' => $item->item_type_name, 'price' => 400,
        ]);
        $this->assertFalse((bool) $target->fresh()->clothing_order);
    }

    public function test_unassigned_authenticated_user_cannot_manage_or_open_region_clothing(): void
    {
        $region = TeamRegion::create(['region_name' => 'Protected Region 2026']);
        $this->authorizedAdminForRegion($region, 2026);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('backend.region.clothing.edit', $region))->assertForbidden();
        $this->actingAs($user)->post(route('backend.region.clothing.items.store', $region), [
            'item_type_name' => 'Unauthorized shirt', 'price' => 100,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('backend.region.clothing.toggle', $region))->assertForbidden();
        $this->actingAs($user)->get(route('backend.region.clothing.orders', $region))->assertForbidden();
    }

    public function test_ordering_cannot_open_until_every_item_has_a_price_and_size(): void
    {
        $region = TeamRegion::create(['region_name' => 'Readiness Region 2026', 'clothing_order' => false]);
        $admin = $this->authorizedAdminForRegion($region, 2026);
        $item = ClothingItemType::create([
            'item_type_name' => 'Incomplete shirt', 'price' => 0, 'region_id' => $region->id,
        ]);

        $this->actingAs($admin)->patch(route('backend.region.clothing.toggle', $region))->assertStatus(422);
        $this->assertFalse((bool) $region->fresh()->clothing_order);

        $item->update(['price' => 300]);
        ClothingSize::create(['size' => 'M', 'item_type' => $item->id]);
        $this->actingAs($admin)->patch(route('backend.region.clothing.toggle', $region))
            ->assertRedirect();
        $this->assertTrue((bool) $region->fresh()->clothing_order);
    }

    private function authorizedAdminForRegion(TeamRegion $region, int $year): User
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create()->assignRole('admin');
        $this->attachAdminToRegion($admin, $region, $year);

        return $admin;
    }

    private function attachAdminToRegion(User $admin, TeamRegion $region, int $year): void
    {
        $typeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Clothing authorization team event', 'type' => 2,
            'code' => 'cloth-auth-'.substr((string) str()->uuid(), 0, 8), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create([
            'eventType' => $typeId,
            'start_date' => "{$year}-10-09",
        ]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
    }
}
