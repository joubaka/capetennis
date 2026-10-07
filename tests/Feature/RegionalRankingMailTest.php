<?php
namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventRegion, EventRegionRankingSource, EventType, Player, RankingList, Series, SeriesRanking, Team, TeamPlayer, TeamRegion, User};
use App\Services\{EventCommunicationService, RegionalRankingMailAudience};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegionalRankingMailTest extends TestCase
{
    use RefreshDatabase;
    private Event $event;
    private User $admin;
    private RankingList $list;
    private Team $team;
    private EventRegion $region;
    protected function setUp(): void
    {
        parent::setUp(); Bus::fake(); Mail::fake();
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $this->admin=User::factory()->create()->assignRole('admin');
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Team','type'=>EventType::TEAM]);
        $this->event=Event::factory()->create(['eventType'=>$type]);
        DB::table('event_admins')->insert(['event_id'=>$this->event->id,'user_id'=>$this->admin->id]);
        $region=TeamRegion::create(['region_name'=>'Test region']);
        $this->region=EventRegion::findOrFail(DB::table('event_regions')->insertGetId(['event_id'=>$this->event->id,'region_id'=>$region->id]));
        $category=CategoryEvent::factory()->create(['event_id'=>$this->event->id]);
        $this->team=Team::factory()->create(['category_event_id'=>$category->id,'region_id'=>$region->id]);
        $series=Series::factory()->create();
        $this->list=RankingList::create(['series_id'=>$series->id,'category_id'=>$category->category_id,'best_num_of_scores'=>3]);
        EventRegionRankingSource::create(['event_id'=>$this->event->id,'event_region_id'=>$this->region->id,'region_id'=>$region->id,'series_id'=>$series->id,'reserve_count'=>2,'linked_by'=>$this->admin->id]);
        $this->actingAs($this->admin);
    }
    private function ranked(int $rank, ?string $email='parent@example.test'): SeriesRanking
    {
        $player=Player::factory()->create(['email'=>$email,'userId'=>null]);
        return SeriesRanking::create(['series_id'=>$this->list->series_id,'ranking_list_id'=>$this->list->id,'category_id'=>$this->list->category_id,'player_id'=>$player->id,'rank_position'=>$rank,'total_points'=>100,'run_id'=>'current','status'=>'published']);
    }
    private function audienceOptions(array $extra=[]): array { return $extra+['scope'=>'rankings','filter'=>'all','recipients'=>'players']; }
    public function test_original_ranks_include_ties_and_shared_contact_preserves_each_player(): void
    {
        $a=$this->ranked(9); $b=$this->ranked(9); $this->ranked(10,'outside@example.test'); $this->ranked(14,'fourteen@example.test');
        $batch=app(EventCommunicationService::class)->preview($this->event,$this->admin,$this->audienceOptions(['rank_numbers'=>'9, 14-18']),'Update','Hello');
        $this->assertCount(2,$batch->recipients);
        $parent=collect($batch->recipients)->firstWhere('email','parent@example.test');
        $this->assertSame('Hello',$parent['html']);
        $this->assertSame('Update',$parent['subject']);
        $this->assertCount(2,$parent['ranking_review']);
        $this->assertEqualsCanonicalizing([$a->player_id,$b->player_id],array_column($parent['ranking_review'],'player_id'));
        $this->assertDatabaseCount('bulk_email_logs',0);
        $this->assertSame(2,app(EventCommunicationService::class)->approve($batch,$this->admin,false)['queued']);
        $this->assertTrue(app(EventCommunicationService::class)->approve($batch,$this->admin,false)['duplicate']);
        $this->assertDatabaseCount('bulk_email_logs',2); Mail::assertNothingSent();
    }
    public function test_unpaid_team_slots_are_excluded_without_renumbering_and_manual_exclusions_apply(): void
    {
        $a=$this->ranked(9); $b=$this->ranked(10,'ten@example.test');
        TeamPlayer::create(['team_id'=>$this->team->id,'player_id'=>$a->player_id,'rank'=>1,'pay_status'=>0]);
        $service=app(RegionalRankingMailAudience::class);
        $result=$service->resolve($this->event,$this->admin,$this->audienceOptions(['exclude_team_listed'=>1,'rank_numbers'=>'9-10']));
        $this->assertSame(1,$result['counts']['team_listed']); $this->assertSame(1,$result['counts']['included']);
        $result=$service->resolve($this->event,$this->admin,$this->audienceOptions(['excluded_player_ids'=>[$b->player_id]]));
        $this->assertSame(1,$result['counts']['manual']);
    }
    public function test_cross_event_selections_and_invalid_ranges_are_rejected(): void
    {
        $this->ranked(9);
        foreach ([['ranking_region_ids'=>[999999]],['ranking_list_ids'=>[999999]],['rank_numbers'=>'18-9']] as $extra) {
            $this->post(route('backend.event-communications.preview',$this->event),$this->audienceOptions($extra)+['subject'=>'Update','body'=>'Hello'])->assertSessionHasErrors('rank_numbers');
        }
        $this->assertDatabaseCount('event_communication_batches',0);
    }
    public function test_published_run_change_and_roster_change_require_fresh_preview(): void
    {
        $ranking=$this->ranked(9);
        $service=app(EventCommunicationService::class);
        $batch=$service->preview($this->event,$this->admin,$this->audienceOptions(),'Update','Hello');
        $ranking->update(['run_id'=>'replacement']);
        $this->post(route('backend.event-communications.send',$this->event),['token'=>$batch->token,'confirm_send'=>1])->assertSessionHasErrors('preview');
        $batch=$service->preview($this->event,$this->admin,$this->audienceOptions(),'Update','Hello');
        TeamPlayer::create(['team_id'=>$this->team->id,'player_id'=>$ranking->player_id,'rank'=>1,'pay_status'=>0]);
        $this->post(route('backend.event-communications.send',$this->event),['token'=>$batch->token,'confirm_send'=>1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs',0);
    }
    public function test_region_manager_cannot_use_rankings_and_role_loss_blocks_approval(): void
    {
        $this->ranked(9); $manager=User::factory()->create();
        \App\Models\EventRegionManager::create(['event_id'=>$this->event->id,'event_region_id'=>$this->region->id,'region_id'=>$this->region->region_id,'user_id'=>$manager->id]);
        $this->actingAs($manager)->get(route('backend.event-communications.index',$this->event))->assertOk()->assertDontSee('value="rankings"',false);
        $this->post(route('backend.event-communications.preview',$this->event),$this->audienceOptions()+['subject'=>'Update','body'=>'Hello'])->assertForbidden();
        $convenor=User::factory()->create();
        \App\Models\EventConvenor::create(['event_id'=>$this->event->id,'user_id'=>$convenor->id,'role'=>'convenor']);
        $this->actingAs($convenor)->post(route('backend.event-communications.preview',$this->event),$this->audienceOptions()+['subject'=>'Update','body'=>'Hello'])->assertForbidden();
        $batch=app(EventCommunicationService::class)->preview($this->event,$this->admin,$this->audienceOptions(),'Update','Hello');
        DB::table('event_admins')->where('event_id',$this->event->id)->delete();
        $this->actingAs($this->admin)->post(route('backend.event-communications.send',$this->event),['token'=>$batch->token,'confirm_send'=>1])->assertForbidden();
        $this->assertDatabaseCount('bulk_email_logs',0);
    }
    public function test_declined_reserve_and_withdrawn_are_independent_and_only_current_import_counts(): void
    {
        $source=$this->region->rankingSource;
        $old=\App\Models\TeamSelectionImport::create(['source_id'=>$source->id,'event_id'=>$this->event->id,'region_id'=>$this->region->region_id,'series_id'=>$this->list->series_id,'ranking_run_id'=>'old','status'=>'sent']);
        $current=\App\Models\TeamSelectionImport::create(['source_id'=>$source->id,'event_id'=>$this->event->id,'region_id'=>$this->region->region_id,'series_id'=>$this->list->series_id,'ranking_run_id'=>'current','status'=>'draft']);
        foreach (['declined','reserve','withdrawn','invited'] as $index=>$status) {
            $ranking=$this->ranked($index+1);
            \App\Models\TeamSelectionInvitation::create(['import_id'=>$current->id,'event_id'=>$this->event->id,'region_id'=>$this->region->region_id,'team_id'=>$this->team->id,'player_id'=>$ranking->player_id,'ranking_list_id'=>$this->list->id,'ranking_position'=>$index+1,'queue_position'=>$index+1,'status'=>$status]);
            if($status==='invited') \App\Models\TeamSelectionInvitation::create(['import_id'=>$old->id,'event_id'=>$this->event->id,'region_id'=>$this->region->region_id,'team_id'=>$this->team->id,'player_id'=>$ranking->player_id,'ranking_list_id'=>$this->list->id,'ranking_position'=>$index+1,'queue_position'=>$index+1,'status'=>'declined']);
        }
        $service=app(RegionalRankingMailAudience::class);
        $result=$service->resolve($this->event,$this->admin,$this->audienceOptions(['exclude_declined'=>1]));
        $this->assertSame(3,$result['counts']['included']); $this->assertSame(1,$result['counts']['declined']);
        $result=$service->resolve($this->event,$this->admin,$this->audienceOptions(['exclude_declined'=>1,'exclude_reserves'=>1,'exclude_withdrawn'=>1]));
        $this->assertSame(1,$result['counts']['included']);
    }
    public function test_missing_contacts_manual_review_and_super_admin_access(): void
    {
        $this->ranked(9); $this->ranked(10,null);
        Role::firstOrCreate(['name'=>'super-user','guard_name'=>'web']);
        $super=User::factory()->create()->assignRole('super-user');
        $this->assertTrue(app(RegionalRankingMailAudience::class)->canManage($this->event,$super));
        $this->post(route('backend.event-communications.preview',$this->event),$this->audienceOptions()+['subject'=>'Update','body'=>'Hello'])->assertOk()->assertSee('Review ranked players')->assertSee('Update selection and review fresh emails');
        $batch=\App\Models\EventCommunicationBatch::latest('id')->first();
        $this->assertCount(1,$batch->issues);
        $this->post(route('backend.event-communications.send',$this->event),['token'=>$batch->token,'confirm_send'=>1])->assertSessionHasErrors('acknowledge_missing');
    }
    public function test_retry_generations_require_admin_and_unchanged_ranking_source(): void
    {
        $ranking=$this->ranked(9); $service=app(EventCommunicationService::class);
        $batch=$service->preview($this->event,$this->admin,$this->audienceOptions(),'Protected ranking retry','Hello');
        $service->approve($batch,$this->admin,false);
        $log=\App\Models\BulkEmailLog::sole(); $log->update(['status'=>'failed']);
        $retry=$service->previewRetry($this->event,$log,$this->admin); $service->approve($retry,$this->admin,false);
        $log->refresh()->update(['status'=>'failed']);
        $retry2=$service->previewRetry($this->event,$log,$this->admin);
        $ranking->update(['run_id'=>'new-current']);
        $this->post(route('backend.event-communications.send',$this->event),['token'=>$retry2->token,'confirm_send'=>1])->assertSessionHasErrors('preview');
        DB::table('event_admins')->where('event_id',$this->event->id)->delete();
        \App\Models\EventRegionManager::create(['event_id'=>$this->event->id,'event_region_id'=>$this->region->id,'region_id'=>$this->region->region_id,'user_id'=>$this->admin->id]);
        $this->post(route('backend.event-communications.send',$this->event),['token'=>$retry2->token,'confirm_send'=>1])->assertForbidden();
        $this->get(route('backend.event-communications.index',$this->event))->assertOk()->assertDontSee('Protected ranking retry');
         $this->get(route('backend.event-communications.index',['event'=>$this->event,'batch'=>$retry2->id]))->assertForbidden();
        $this->post(route('backend.event-communications.retry-preview',[$this->event,$log]))->assertForbidden();
        $this->assertDatabaseCount('bulk_email_logs',1);
    }

    public function test_linked_imported_roster_and_ambiguous_publication_are_protected(): void
    {
        $ranking=$this->ranked(9);
        \App\Models\NoProfileTeamPlayer::create(['team_id'=>$this->team->id,'player_profile'=>$ranking->player_id,'name'=>'Imported','surname'=>'Player','rank'=>1,'pay_status'=>0]);
        $result=app(RegionalRankingMailAudience::class)->resolve($this->event,$this->admin,$this->audienceOptions(['exclude_team_listed'=>1]));
        $this->assertSame(1,$result['counts']['team_listed']);
        $other=$this->ranked(10); $other->update(['run_id'=>'another-published']);
        $this->post(route('backend.event-communications.preview',$this->event),$this->audienceOptions()+['subject'=>'Update','body'=>'Hello'])->assertSessionHasErrors('rank_numbers');
        $this->assertDatabaseCount('event_communication_batches',0);
    }

}
