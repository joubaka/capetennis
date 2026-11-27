<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\{ScoreValidationService,TennisScoreFormat};
use App\Models\{Draw,DrawAuditLog,Event,User};
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Support\Facades\{DB,Gate};

/** Reviewed rule correction; only explicitly supplied deciding points may change. */
final class InterproScoringCorrectionService
{
    public function review(Draw $draw,array $thirdSetPoints=[]): array
    {
        $draw->load(['settings','drawFixtures.fixtureResults','drawFixtures.orderOfPlay','drawFixtures.registration1.players','drawFixtures.registration2.players']);
        abort_unless(InterproRoundRobinFormat::selected($draw) && $draw->event?->isInterprovincialTrials() && !$draw->isTeamDraw(),409,'Use an individual Interpro feed-in draw.');
        abort_if($draw->locked,409,'Unlock the draw before reviewing its scoring rules.');
        $config=$draw->settings->playoff_config[0] ?? [];
        abort_if(!empty($config['trials_confirmed']) || !empty($config['trials_carries']) || !empty($config['trials_advances'])
            || !empty($config['trials_skipped_challenges']),409,'Qualifying groups or dependent results are already confirmed. Review draw recovery before changing scoring rules.');
        abort_if($draw->drawFixtures->contains(fn($fixture)=>$fixture->stage!=='RR' && ($fixture->fixtureResults->isNotEmpty()
            || $fixture->winner_registration || (int)$fixture->match_status!==0)),409,'A playoff already has an outcome. Scoring-rule correction requires a separate dependent-draw review.');
        $proposed=clone $draw; $settings=clone $draw->settings;
        $settings->score_format=TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK;
        $settings->playoff_score_format=TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK;
        $settings->num_sets=3; $proposed->setRelation('settings',$settings);
        $validator=app(ScoreValidationService::class); $outcomes=app(IndividualMatchOutcomeService::class);
        $incompatible=[]; $compatible=0; $corrections=[]; $winnerChanges=[];
        $fixtureIds=$draw->drawFixtures->pluck('id')->map(fn($id)=>(int)$id)->all();
        abort_if(\App\Models\Fixture::where(fn($query)=>$query->where('draw_id','!=',$draw->id)->orWhereNull('draw_id'))->where(fn($query)=>$query->whereIn('parent_fixture_id',$fixtureIds)
            ->orWhereIn('loser_parent_fixture_id',$fixtureIds))->exists(),409,'Another draw depends on this draw. Review its dependencies separately.');
        abort_if($draw->drawFixtures->contains(fn($fixture)=>($fixture->parent_fixture_id && !in_array((int)$fixture->parent_fixture_id,$fixtureIds,true))
            || ($fixture->loser_parent_fixture_id && !in_array((int)$fixture->loser_parent_fixture_id,$fixtureIds,true))),409,'This draw feeds a different draw. Review its dependent results separately.');
        abort_if(array_diff(array_map('intval',array_keys($thirdSetPoints)),$fixtureIds)!==[],422,'Choose a match belonging to this draw.');
        foreach ($draw->drawFixtures as $fixture) {
            $preview=clone $fixture; $preview->setRelation('draw',$proposed);
            $sets=$fixture->fixtureResults->sortBy('set_nr')->map(fn($set)=>[(int)$set->registration1_score,(int)$set->registration2_score])->values()->all();
            if (!$sets && !$fixture->winner_registration && ((int)$fixture->match_status===0
                || ($fixture->stage==='RR' && (int)$fixture->match_status===2))) {
                abort_if(isset($thirdSetPoints[$fixture->id]),422,'Only an existing incompatible third set can be corrected.');
                continue;
            }
            $originalSets=$sets;
            $setNumbers=$fixture->fixtureResults->sortBy('set_nr')->pluck('set_nr')->map(fn($number)=>(int)$number)->values()->all();
            if (isset($thirdSetPoints[$fixture->id])) {
                abort_unless($fixture->stage==='RR' && $setNumbers===[1,2,3] && !$fixture->isWalkover() && !$fixture->isRetired()
                    && !$validator->validate($preview,$sets)['valid'],422,'Third-point correction is limited to an incompatible recorded deciding set in this draw.');
                $points=$thirdSetPoints[$fixture->id];
                abort_unless(is_array($points) && array_is_list($points) && count($points)===2 && is_int($points[0]) && is_int($points[1]),422,'Supply both actual whole-number deciding-tiebreak scores.');
                $sets[2]=$points;
            }
            $reason=!$sets && !$fixture->isWalkover()
                ? 'This match has a recorded outcome or status but no set scores. Review it separately.'
                : ($sets && $setNumbers!==range(1,count($sets)) ? 'Recorded set numbers are duplicated or incomplete; review this match separately.' : null);
            if ($reason) {
                // Preserve malformed historical rows for explicit review, never reinterpret their order.
            } elseif ($fixture->isWalkover()) {
                if ($sets || !in_array((int)$fixture->winner_registration,[(int)$fixture->registration1_id,(int)$fixture->registration2_id],true)
                    || !$fixture->winner_registration) $reason='The walkover outcome needs review.';
            } else {
                $validation=$fixture->isRetired() ? $validator->validateRetirement($preview,$sets) : $validator->validate($preview,$sets);
                if (!$validation['valid']) $reason=$validation['message'];
                elseif (!$fixture->isRetired() && !isset($thirdSetPoints[$fixture->id]) && (int)$outcomes->winnerFromSets($preview,$sets)!==(int)$fixture->winner_registration)
                    $reason='The recorded winner does not match the proposed scoring rules.';
                elseif ($fixture->isRetired() && (!in_array($fixture->retiredRegistrationId(),[(int)$fixture->registration1_id,(int)$fixture->registration2_id],true)
                    || (int)$fixture->winner_registration===(int)$fixture->retiredRegistrationId() || !$fixture->winner_registration))
                    $reason='The retirement participants and outcome need review.';
            }
            $home=$fixture->registration1?->display_name ?: 'Registration '.$fixture->registration1_id;
            $away=$fixture->registration2?->display_name ?: 'Registration '.$fixture->registration2_id;
            if ($reason) $incompatible[]=['fixture_id'=>$fixture->id,'match_nr'=>$fixture->match_nr,'stage'=>$fixture->stage,'reason'=>$reason,'home'=>$home,'away'=>$away,
                'can_correct_third'=>$fixture->stage==='RR' && $setNumbers===[1,2,3] && !$fixture->isWalkover() && !$fixture->isRetired()];
            else {
                $compatible++;
                if (isset($thirdSetPoints[$fixture->id])) {
                    $winner=$outcomes->winnerFromSets($preview,$sets);
                    $corrections[$fixture->id]=['fixture_id'=>$fixture->id,'match_nr'=>$fixture->match_nr,'before'=>$originalSets[2],
                        'after'=>$sets[2],'previous_winner'=>$fixture->winner_registration,'winner'=>$winner,'home'=>$home,'away'=>$away,
                        'previous_winner_name'=>(int)$fixture->winner_registration===(int)$fixture->registration1_id ? $home : $away,
                        'winner_name'=>(int)$winner===(int)$fixture->registration1_id ? $home : $away];
                    if ((int)$winner!==(int)$fixture->winner_registration) $winnerChanges[]=$fixture->id;
                }
            }
        }
        $revision=hash('sha256',serialize([$draw->getAttributes(),$draw->settings->getAttributes(),
            $draw->drawFixtures->sortBy('id')->map(fn($fixture)=>[$fixture->getAttributes(),$fixture->fixtureResults->sortBy('id')->map->getAttributes()->all(),$fixture->orderOfPlay?->getAttributes()])->all(),
            DB::table('published_schedule_assignments')->where('draw_id',$draw->id)->orderBy('id')->get()->all(),$thirdSetPoints]));
        return ['draw_id'=>$draw->id,'revision'=>$revision,'compatible'=>$compatible,'incompatible'=>$incompatible,
            'format'=>TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK,
            'third_set_points'=>$thirdSetPoints,'corrections'=>$corrections,'winner_changes'=>$winnerChanges,
            'already_applied'=>$draw->settings->score_format===$settings->score_format && $draw->settings->playoff_score_format===$settings->playoff_score_format && (int)$draw->settings->num_sets===3];
    }

    public function apply(Draw $draw,string $revision,User $actor,array $thirdSetPoints=[],bool $acknowledgeWinnerChange=false): array
    {
        return DB::transaction(function() use($draw,$revision,$actor,$thirdSetPoints,$acknowledgeWinnerChange){
            Event::whereKey($draw->event_id)->lockForUpdate()->firstOrFail();
            $current=Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$current->event_id===(int)$draw->event_id,409,'The event changed. Reload this review.');
            Gate::forUser($actor)->authorize('correctRoundRobinScoring',$current);
            $current->settings()->lockForUpdate()->firstOrFail();
            $fixtures=$current->drawFixtures()->lockForUpdate()->get();
            \App\Models\FixtureResult::whereIn('fixture_id',$fixtures->pluck('id'))->lockForUpdate()->get();
            $review=$this->review($current,$thirdSetPoints);
            abort_unless(hash_equals($review['revision'],$revision),409,'The draw changed. Review its recorded scores again.');
            abort_if($review['incompatible']!==[],409,'Some recorded scores are incompatible. Review them individually; no scores or rules were changed.');
            abort_if($review['winner_changes']!==[] && !$acknowledgeWinnerChange,422,'Explicitly acknowledge the displayed winner change before applying this correction.');
            if ($review['already_applied'] && !$review['corrections']) return $review;
            $before=$current->settings->only(['score_format','playoff_score_format','num_sets']);
            $current->settings->update(['score_format'=>$review['format'],'playoff_score_format'=>$review['format'],'num_sets'=>3]);
            foreach ($review['corrections'] as $correction) {
                $fixture=$current->drawFixtures()->lockForUpdate()->findOrFail($correction['fixture_id']);
                $fixture->setRelation('draw',$current->fresh()->load('settings'));
                \App\Domain\Draws\Guards\DrawGuard::requireScoreable($fixture);
                $third=$fixture->fixtureResults()->where('set_nr',3)->lockForUpdate()->sole();
                $winner=$correction['winner'];
                $third->update(['registration1_score'=>$correction['after'][0],'registration2_score'=>$correction['after'][1],
                    'winner_registration'=>$winner,'loser_registration'=>(int)$winner===(int)$fixture->registration1_id ? $fixture->registration2_id : $fixture->registration1_id]);
                $fixture->update(['winner_registration'=>$winner,'match_status'=>1]);
                app(\App\Services\MatchResultNotificationService::class)->invalidate($fixture);
                DrawAuditLog::record($current->id,'interpro_deciding_tiebreak_corrected',$fixture->id,$correction+['review_revision'=>$revision]);
            }
            DrawAuditLog::record($current->id,'interpro_scoring_rules_corrected',null,['previous_rules'=>$before,
                'score_format'=>$review['format'],'playoff_score_format'=>$review['format'],'validated_results'=>$review['compatible'],'review_revision'=>$revision]);
            return $review;
        });
    }
}
