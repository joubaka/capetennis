<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw,Event,EventType,Fixture,OrderOfPlay,User,Venue};
use App\Services\Draw\InterproRoundRobinFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus,DB,Http,Mail,Notification,Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproSchedulePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function context(): Draw
    {
        Mail::fake(); Notification::fake(); Queue::fake(); Bus::fake(); Http::preventStrayRequests();
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Fictional schedule trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event=Event::factory()->create(['eventType'=>$type,'published'=>true]);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'published'=>true,'oop_published'=>true,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','preset_key'=>InterproRoundRobinFormat::PRESET,
            'playoff_config'=>InterproRoundRobinFormat::configuration(),'schedule_visibility'=>'full_schedule']);
        $venue=Venue::forceCreate(['name'=>'Published courts']);
        $private=Venue::forceCreate(['name'=>'PRIVATE PREVIEW COURTS']);
        foreach ([2001,2002,2003] as $number) {
            $fixture=Fixture::factory()->create(['draw_id'=>$draw->id,'match_nr'=>$number,'stage'=>'MAIN',
                'registration1_id'=>null,'registration2_id'=>null,'winner_registration'=>null,'match_status'=>0]);
            OrderOfPlay::create(['fixture_id'=>$fixture->id,'draw_id'=>$draw->id,
                'venue_id'=>$number===2003 ? $venue->id : $private->id,'court'=>$number===2003 ? '3' : 'PRIVATE COURT',
                'time'=>$number===2003 ? '2026-11-29 10:00:00' : '2026-11-28 08:00:00']);
            if ($number !== 2002) DB::table('published_schedule_assignments')->insert(['event_id'=>$event->id,'draw_id'=>$draw->id,
                'fixture_kind'=>'individual','fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'court'=>(string)($number===2003 ? 3 : 1),
                'scheduled_at'=>'2026-11-29 10:00:00','published_at'=>now()]);
        }
        return $draw;
    }

    private function payload(string $html): array
    {
        preg_match('/data-interpro-payload>(.*?)<\/script>/s',$html,$match);
        $this->assertNotEmpty($match);
        return json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
    }

    private function user(string $role,Draw $draw): User
    {
        Role::firstOrCreate(['name'=>$role,'guard_name'=>'web']);
        $user=User::factory()->create()->assignRole($role);
        if ($role==='admin') DB::table('event_admins')->insert(['event_id'=>$draw->event_id,'user_id'=>$user->id]);
        return $user;
    }

    public function test_only_super_user_receives_working_preview_and_public_snapshots_remain_unchanged(): void
    {
        $draw=$this->context(); $url=route('public.roundrobin.main-bracket',$draw);
        $snapshot=DB::table('published_schedule_assignments')->orderBy('id')->get()->toJson();
        $this->actingAs($this->user('super-user',$draw));
        $response=$this->get($url)->assertOk()->assertSee('PRIVATE PREVIEW COURTS');
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Cookie',$response->headers->get('Vary'));
        $matches=$this->payload($response->getContent())['matches'];
        $this->assertTrue($matches[2001]['schedule']['preview']);
        $this->assertTrue($matches[2002]['schedule']['preview']);
        $this->assertArrayNotHasKey('preview',$matches[2003]['schedule']);
        foreach ([null,$this->user('admin',$draw),User::factory()->create()] as $user) {
            if ($user) $this->actingAs($user); else auth()->logout();
            $public=$this->get($url.'?preview=1')->assertOk()->assertDontSee('PRIVATE PREVIEW COURTS')->assertDontSee('PRIVATE COURT');
            $matches=$this->payload($public->getContent())['matches'];
            $this->assertSame('2026-11-29 10:00:00',$matches[2001]['schedule']['time']);
            $this->assertNull($matches[2002]['schedule']);
            $this->assertArrayNotHasKey('preview',$matches[2001]['schedule']);
            $this->get(route('frontend.fixtures.index',$draw))->assertOk()->assertDontSee('PRIVATE PREVIEW COURTS')->assertDontSee('PRIVATE COURT');
        }
        $this->assertSame($snapshot,DB::table('published_schedule_assignments')->orderBy('id')->get()->toJson());
    }

    public function test_empty_bracket_and_foreign_working_bookings_do_not_leak_and_unpublished_times_stay_private(): void
    {
        $draw=$this->context();
        $foreign=Fixture::factory()->create(['stage'=>'MAIN']);
        $venue=Venue::forceCreate(['name'=>'FOREIGN PRIVATE VENUE']);
        OrderOfPlay::create(['fixture_id'=>$foreign->id,'draw_id'=>$foreign->draw_id,'venue_id'=>$venue->id,
            'court'=>'FOREIGN PRIVATE COURT','time'=>'2026-12-02 17:00:00']);
        $this->actingAs($this->user('super-user',$draw));
        $url=route('public.roundrobin.main-bracket',$draw);
        $this->get($url)->assertOk()->assertDontSee('FOREIGN PRIVATE VENUE')->assertDontSee('FOREIGN PRIVATE COURT');
        $this->get($url.'?empty=1')->assertOk()->assertDontSee('PRIVATE PREVIEW COURTS')->assertDontSee('Published courts');
        $draw->update(['oop_published'=>false]);
        auth()->logout();
        $response=$this->get($url)->assertOk()->assertDontSee('PRIVATE PREVIEW COURTS')->assertDontSee('Published courts');
        $this->assertNull($this->payload($response->getContent())['matches'][2001]['schedule']);
    }

    public function test_preview_compares_venue_identity_and_rejects_mismatched_booking_draw(): void
    {
        $draw=$this->context();
        $sameNameVenue=Venue::forceCreate(['name'=>'Published courts']);
        $fixture=$draw->drawFixtures()->where('match_nr',2003)->firstOrFail();
        $fixture->orderOfPlay()->update(['venue_id'=>$sameNameVenue->id]);
        $other=Draw::factory()->create();
        $draw->drawFixtures()->where('match_nr',2002)->firstOrFail()->orderOfPlay()->update(['draw_id'=>$other->id]);
        $this->actingAs($this->user('super-user',$draw));
        $response=$this->get(route('public.roundrobin.main-bracket',$draw))->assertOk();
        $matches=$this->payload($response->getContent())['matches'];
        $this->assertTrue($matches[2003]['schedule']['preview']);
        $this->assertNull($matches[2002]['schedule']);
        $backend=$this->get(route('backend.draw.main-bracket',$draw))->assertOk();
        $this->assertStringContainsString('private',$backend->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store',$backend->headers->get('Cache-Control'));
    }
}
