<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrawRegistrationOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_reads_preserve_seed_order_and_pivot_metadata(): void
    {
        $draw = Draw::factory()->create();
        $registrations = Registration::factory()->count(4)->create();
        foreach ([3, 1, null, 2] as $index => $seed) {
            $draw->registrations()->attach($registrations[$index], ['seed' => $seed, 'box_number' => $seed === null ? 14 : $seed + 10]);
        }
        $expected = [$registrations[2]->id, $registrations[1]->id, $registrations[3]->id, $registrations[0]->id];
        $snapshot = DB::table('draw_registrations')->orderBy('registration_id')->get()->toArray();

        foreach ([$draw->registrations()->get(), $draw->fresh()->registrations, Draw::with('registrations')->findOrFail($draw->id)->registrations] as $loaded) {
            $this->assertSame($expected, $loaded->modelKeys());
            $this->assertSame([null, 1, 2, 3], $loaded->map(fn ($registration) => $registration->pivot->seed)->all());
            $this->assertSame([14, 11, 12, 13], $loaded->map(fn ($registration) => $registration->pivot->box_number)->all());
        }
        $this->assertSame($expected, $draw->registrations()->pluck('registrations.id')->all());
        $this->assertSame(4, $draw->registrations()->count());
        $this->assertEquals($snapshot, DB::table('draw_registrations')->orderBy('registration_id')->get()->toArray());
        $this->assertDatabaseCount('registrations', 4);
        $this->assertDatabaseCount('draw_registrations', 4);
    }

    public function test_registration_membership_queries_remain_scoped_to_the_draw(): void
    {
        $draw = Draw::factory()->create();
        $otherDraw = Draw::factory()->create();
        [$member, $otherMember] = Registration::factory()->count(2)->create()->all();
        $draw->registrations()->attach($member, ['seed' => 2, 'box_number' => 1]);
        $otherDraw->registrations()->attach($otherMember, ['seed' => 1, 'box_number' => 2]);

        $this->assertTrue($draw->registrations()->where('registrations.id', $member->id)->exists());
        $this->assertFalse($draw->registrations()->where('registrations.id', $otherMember->id)->exists());
        $this->assertFalse($otherDraw->registrations()->where('registrations.id', $member->id)->exists());
        $this->assertTrue($otherDraw->registrations()->where('registrations.id', $otherMember->id)->exists());
        $this->assertSame(1, $draw->registrations()->count());
        $this->assertSame([$member->id], $draw->registrations()->pluck('registrations.id')->all());
        $this->assertSame(0, $draw->registrations()->where('registrations.id', $otherMember->id)->count());
        $this->assertDatabaseCount('registrations', 2);
        $this->assertDatabaseCount('draw_registrations', 2);
    }
}
