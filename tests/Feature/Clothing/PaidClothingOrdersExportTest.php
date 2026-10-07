<?php

namespace Tests\Feature\Clothing;

use App\Exports\ClothingOrdersExport;
use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidClothingOrdersExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_excel_download_exports_one_row_per_paid_item(): void
    {
        [$admin, $event, $region] = $this->managedRegion();
        $order = $this->order($event, $region, true, 'Paid shirt');
        $catalogueItem = $order->items()->firstOrFail();
        $order->items()->create([
            'clothing_order_item_id' => $catalogueItem->clothing_order_item_id,
            'clothing_item_size' => $catalogueItem->clothing_item_size,
            'qty' => 2, 'price' => 50, 'line_total' => 100,
            'item_name' => 'Paid cap', 'size_name' => 'One size',
        ]);
        $this->order($event, $region, false, 'Unpaid hoodie');

        Excel::fake();

        $this->actingAs($admin)->get(route('export.excel.clothing', [
            'id' => $region->id,
            'event_id' => $event->id,
        ]))->assertOk();

        Excel::assertDownloaded('clothing_orders.xlsx', function (ClothingOrdersExport $export): bool {
            $rows = $export->collection();

            return $rows->count() === 2
                && $rows->every(fn (array $row) => (int) $row['order']->pay_status === 1)
                && $rows->pluck('item.item_name')->sort()->values()->all() === ['Paid cap', 'Paid shirt'];
        });
    }

    public function test_paid_orders_page_and_export_are_isolated_by_region_and_event(): void
    {
        [$admin, $event, $region] = $this->managedRegion();
        $secondEvent = Event::factory()->create();
        DB::table('event_regions')->insert([
            'event_id' => $secondEvent->id, 'region_id' => $region->id, 'ordering' => 2,
        ]);
        DB::table('event_admins')->insert(['event_id' => $secondEvent->id, 'user_id' => $admin->id]);
        $otherRegion = TeamRegion::create(['region_name' => 'Other export region']);
        DB::table('event_regions')->insert([
            'event_id' => $event->id, 'region_id' => $otherRegion->id, 'ordering' => 2,
        ]);

        $this->order($event, $region, true, 'Included shirt');
        $this->order($event, $region, false, 'Unpaid shirt');
        $this->order($secondEvent, $region, true, 'Other event shirt');
        $this->order($event, $otherRegion, true, 'Other region shirt');

        $this->actingAs($admin)->get(route('backend.region.clothing.orders', [
            'region' => $region->id,
            'event_id' => $event->id,
        ]))->assertOk()
            ->assertSee('Included shirt')
            ->assertDontSee('Unpaid shirt')
            ->assertDontSee('Other event shirt')
            ->assertDontSee('Other region shirt')
            ->assertSee(route('export.excel.clothing', ['id' => $region->id, 'event_id' => $event->id]));

        Excel::fake();
        $this->actingAs($admin)->get(route('export.excel.clothing', [
            'id' => $region->id,
            'event_id' => $event->id,
        ]))->assertOk();
        Excel::assertDownloaded('clothing_orders.xlsx', fn (ClothingOrdersExport $export) =>
            $export->collection()->count() === 1
            && $export->collection()->first()['item']->item_name === 'Included shirt'
        );
    }

    public function test_event_filter_requires_event_specific_management_access(): void
    {
        [$admin, $managedEvent, $region] = $this->managedRegion();
        $otherEvent = Event::factory()->create();
        DB::table('event_regions')->insert([
            'event_id' => $otherEvent->id, 'region_id' => $region->id, 'ordering' => 2,
        ]);
        $this->order($otherEvent, $region, true, 'Restricted shirt');

        $this->actingAs($admin)->get(route('export.excel.clothing', [
            'id' => $region->id,
            'event_id' => $otherEvent->id,
        ]))->assertForbidden();
        $this->actingAs($admin)->get(route('export.pdf.clothing.order', [
            'id' => $region->id,
            'event_id' => $otherEvent->id,
        ]))->assertForbidden();

        $this->actingAs($admin)->get(route('backend.region.clothing.orders', [
            'region' => $region->id,
            'event_id' => $managedEvent->id,
        ]))->assertOk()->assertSee('No clothing orders found for this region');
    }

    public function test_non_super_user_cannot_use_region_wide_listing_or_exports(): void
    {
        [$admin, , $region] = $this->managedRegion();

        $this->actingAs($admin)->get(route('backend.region.clothing.orders', $region))->assertForbidden();
        $this->actingAs($admin)->get(route('export.excel.clothing', $region->id))->assertForbidden();
        $this->actingAs($admin)->get(route('export.pdf.clothing.order', $region->id))->assertForbidden();
    }

    public function test_pdf_download_contains_only_paid_orders_for_the_authorized_event(): void
    {
        [$admin, $event, $region] = $this->managedRegion();
        $otherEvent = Event::factory()->create();
        DB::table('event_regions')->insert([
            'event_id' => $otherEvent->id, 'region_id' => $region->id, 'ordering' => 2,
        ]);
        DB::table('event_admins')->insert(['event_id' => $otherEvent->id, 'user_id' => $admin->id]);
        $included = $this->order($event, $region, true, 'PDF included');
        $this->order($event, $region, false, 'PDF unpaid');
        $this->order($otherEvent, $region, true, 'PDF other event');

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('download')->once()->with('clothing_orders.pdf')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()
            ->with('.backend.clothing.clothing-order-pdf', \Mockery::on(function (array $data) use ($included): bool {
                return $data['clothings']->pluck('id')->all() === [$included->id];
            }))
            ->andReturn($document);

        $this->actingAs($admin)->get(route('export.pdf.clothing.order', [
            'id' => $region->id,
            'event_id' => $event->id,
        ]))->assertOk();
    }

    public function test_excel_text_fields_are_neutralized_when_they_begin_with_formula_markers(): void
    {
        [, $event, $region] = $this->managedRegion();
        $order = $this->order($event, $region, true, '=malicious item');
        $order->update(['pf_id' => '-malicious payment']);
        $order->player->update(['name' => '+malicious', 'surname' => 'player']);
        $order->team->update(['name' => '@malicious team']);
        $order->items()->update(['size_name' => '=malicious size']);

        $export = new ClothingOrdersExport(ClothingOrder::query()->whereKey($order->id)->get());
        $mapped = $export->map($export->collection()->first());

        $this->assertSame("'+malicious player", $mapped[2]);
        $this->assertSame("'=malicious item", $mapped[3]);
        $this->assertSame("'=malicious size", $mapped[4]);
        $this->assertSame("'@malicious team", $mapped[5]);
        $this->assertSame("'-malicious payment", $mapped[9]);
    }

    public function test_user_without_region_access_cannot_list_or_export_orders(): void
    {
        [, $event, $region] = $this->managedRegion();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('backend.region.clothing.orders', $region))->assertForbidden();
        $this->actingAs($stranger)->get(route('export.excel.clothing', [
            'id' => $region->id,
            'event_id' => $event->id,
        ]))->assertForbidden();
    }

    public function test_excel_and_pdf_group_teams_and_order_players_by_their_rank_in_that_team(): void
    {
        [$admin, $event, $region] = $this->managedRegion();
        $laterTeam = $this->order($event, $region, true, 'Zulu item');
        $laterTeam->team->update(['name' => 'Zulu team']);
        $rankTwo = $this->order($event, $region, true, 'Rank two');
        $rankTwo->team->update(['name' => 'Alpha team']);
        $rankOne = $this->order($event, $region, true, 'Rank one');
        $unrankedZulu = $this->order($event, $region, true, 'Unranked Zulu');
        $unrankedAlpha = $this->order($event, $region, true, 'Unranked Alpha');
        foreach ([$rankOne, $unrankedZulu, $unrankedAlpha] as $order) {
            $order->update(['team_id' => $rankTwo->team_id]);
        }
        $unrankedZulu->player->update(['surname' => 'Zulu']);
        $unrankedAlpha->player->update(['surname' => 'Alpha']);
        \App\Models\TeamPlayer::create(['team_id' => $rankTwo->team_id, 'player_id' => $rankTwo->player_id, 'rank' => 2]);
        \App\Models\TeamPlayer::create(['team_id' => $rankTwo->team_id, 'player_id' => $rankOne->player_id, 'rank' => 1]);
        // A rank in a different team must not affect this order's position.
        \App\Models\TeamPlayer::create(['team_id' => $laterTeam->team_id, 'player_id' => $unrankedZulu->player_id, 'rank' => 1]);
        $item = $rankOne->items()->firstOrFail();
        $rankOne->items()->create([
            'clothing_order_item_id' => $item->clothing_order_item_id,
            'clothing_item_size' => $item->clothing_item_size,
            'qty' => 1, 'price' => 50, 'line_total' => 50,
            'item_name' => 'Second rank one item', 'size_name' => 'Medium',
        ]);
        $expected = [$rankOne->id, $rankTwo->id, $unrankedAlpha->id, $unrankedZulu->id, $laterTeam->id];

        Excel::fake();
        $this->actingAs($admin)->get(route('export.excel.clothing', ['id' => $region->id, 'event_id' => $event->id]))->assertOk();
        Excel::assertDownloaded('clothing_orders.xlsx', function (ClothingOrdersExport $export) use ($expected, $rankOne): bool {
            $rows = $export->collection();
            $this->assertSame([$rankOne->id, ...$expected], $rows->pluck('order.id')->all());
            $this->assertSame(['Rank one', 'Second rank one item'], $rows->take(2)->pluck('item.item_name')->all());

            return true;
        });

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('download')->once()->with('clothing_orders.pdf')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()
            ->with('.backend.clothing.clothing-order-pdf', \Mockery::on(function (array $data) use ($expected): bool {
                $this->assertSame($expected, $data['clothings']->pluck('id')->all());

                return true;
            }))->andReturn($document);
        $this->actingAs($admin)->get(route('export.pdf.clothing.order', ['id' => $region->id, 'event_id' => $event->id]))->assertOk();
    }

    private function managedRegion(): array
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Paid orders region']);
        DB::table('event_regions')->insert([
            'event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1,
        ]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        return [$admin, $event, $region];
    }

    private function order(Event $event, TeamRegion $region, bool $paid, string $itemName): ClothingOrder
    {
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
        $player = Player::factory()->create();
        $catalogueItem = ClothingItemType::create([
            'region_id' => $region->id,
            'item_type_name' => $itemName,
            'price' => 100,
        ]);
        $size = ClothingSize::create(['item_type' => $catalogueItem->id, 'size' => 'Medium']);
        $order = ClothingOrder::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'user_id' => User::factory()->create()->id,
            'pay_status' => $paid ? 1 : 0,
            'status' => $paid ? 'completed' : 'pending',
            'subtotal' => 100,
            'payfast_fee' => 0,
            'total' => 100,
            'request_token' => (string) str()->uuid(),
        ]);
        $order->items()->create([
            'clothing_order_item_id' => $catalogueItem->id,
            'clothing_item_size' => $size->id,
            'qty' => 1, 'price' => 100, 'line_total' => 100,
            'item_name' => $itemName, 'size_name' => 'Medium',
        ]);

        return $order;
    }
}
