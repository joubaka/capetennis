<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Event, Fixture};
use Illuminate\Support\Facades\DB;

/** Append optional Interpro finals without resetting any existing competition state. */
final class InterproChallengeSelectionService
{
    public function revision(Draw $draw): string
    {
        $draw->loadMissing('settings');
        return hash('sha256', json_encode([
            $draw->only(['id','event_id','locked','published','oop_published']),
            $draw->settings?->getAttributes(),
            $draw->drawFixtures()->with('fixtureResults')->orderBy('id')->get()->map(fn ($fixture) => [
                $fixture->getAttributes(),$fixture->fixtureResults->sortBy('id')->map(fn($result)=>$result->getAttributes())->values()->all(),
            ])->all(),
            DB::table('order_of_plays')->where('draw_id',$draw->id)->orderBy('id')->get()->all(),
            DB::table('published_schedule_assignments')->where('draw_id',$draw->id)->orderBy('id')->get()->all(),
        ]));
    }

    public function append(Draw $draw, array $pairs, string $revision): int
    {
        $pairs=array_map('intval',$pairs); sort($pairs);
        abort_unless($pairs !== [] && count(array_unique($pairs)) === count($pairs)
            && !array_diff($pairs,[2,4,6,8,10]),422,'Choose distinct Interpro challenge pairs from 2 v 3 through 10 v 11.');
        return DB::transaction(function () use ($draw,$pairs,$revision): int {
            Event::whereKey($draw->event_id)->lockForUpdate()->firstOrFail();
            $current=Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$current->event_id === (int)$draw->event_id,409,'The draw changed. Reload its settings.');
            $current->settings()->lockForUpdate()->firstOrFail();
            abort_unless(InterproRoundRobinFormat::selected($current) && $current->event?->isInterprovincialTrials()
                && !$current->isTeamDraw(),422,'Choose an individual Interpro feed-in draw.');
            abort_if($current->locked,403,'Unlock the draw before adding challenges.');
            $config=$current->settings->playoff_config;
            abort_if(!empty($config[0]['trials_confirmed']),409,'Add challenge matches before confirming the qualifying groups.');
            $rows=$current->drawFixtures()->lockForUpdate()->get()->where('stage','!=','RR');
            abort_unless($rows->pluck('match_nr')->unique()->count() === $rows->count(),409,'Duplicate Interpro playoff match numbers need review.');
            $fixtures=$rows->keyBy('match_nr');
            $template=InterproRoundRobinFormat::template($current);
            $groups=$current->groups()->get()->keyBy('name');
            abort_unless($groups->keys()->sort()->values()->all() === ['A','B','C','D'],409,'Review the four Interpro qualifying groups before adding challenges.');
            abort_unless($fixtures->count() === count($template)
                && $fixtures->keys()->sort()->values()->all() === collect(array_keys($template))->sort()->values()->all(),409,
                'Existing Interpro playoff fixtures need review before adding challenges.');
            foreach ($template as $number=>$definition) {
                $fixture=$fixtures[$number];
                abort_unless($fixture->stage === $definition[0] && (int)$fixture->round === $definition[1]
                    && (int)$fixture->position === (int)($definition[3] ?? null),409,'The saved playoff topology changed. Review it before adding challenges.');
                foreach ($definition[2] as $slot=>$source) {
                    if (preg_match('/^([A-D])([1-3])$/',$source,$groupMatch)) {
                        abort_unless((int)$fixture->{'registration'.($slot+1).'_source_group_id'} === (int)$groups[$groupMatch[1]]->id
                            && (int)$fixture->{'registration'.($slot+1).'_source_position'} === (int)$groupMatch[2],409,
                            'The saved qualifying routes changed. Review them before adding challenges.');
                    }
                    if (preg_match('/^([WL])(\d+)$/',$source,$match)) {
                        $field=$match[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id';
                        abort_unless((int)$fixtures[(int)$match[2]]->{$field} === (int)$fixture->id,409,
                            'The saved playoff routes changed. Review them before adding challenges.');
                    }
                }
            }
            $existing=InterproRoundRobinFormat::challengePairs($current); sort($existing);
            abort_if(array_diff($existing,$pairs) !== [],409,'Existing challenge matches cannot be removed through this append-only action.');
            abort_unless(strlen($revision) === 64,409,'Reload the settings before adding challenges.');
            if ($existing === $pairs) return 0;
            abort_unless(hash_equals($this->revision($current),$revision),409,'The draw changed. Reload its settings before adding challenges.');
            $config['challenge_playoffs']=true; $config['challenge_pairs']=$pairs;
            $current->settings->playoff_config=$config;
            $newTemplate=InterproRoundRobinFormat::template($current);
            $created=[];
            foreach (array_diff($pairs,$existing) as $upper) {
                $number=5000+$upper; $definition=$newTemplate[$number];
                // Verify both terminal route slots before creating or updating anything.
                foreach ($definition[2] as $source) {
                    preg_match('/^([WL])(\d+)$/',$source,$match);
                    $field=$match[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id';
                    abort_unless(empty($fixtures[(int)$match[2]]->{$field}),409,'A terminal playoff route is already in use.');
                }
                $challenge=Fixture::create(['draw_id'=>$current->id,'match_nr'=>$number,'stage'=>'CHALLENGE',
                    'round'=>$definition[1],'position'=>$upper]);
                foreach ($definition[2] as $source) {
                    preg_match('/^([WL])(\d+)$/',$source,$match);
                    $field=$match[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id';
                    $fixtures[(int)$match[2]]->update([$field=>$challenge->id]);
                }
                $created[]=$challenge->id;
            }
            $current->settings->save();
            DrawAuditLog::record($current->id,'interpro_challenges_appended',null,
                ['previous_pairs'=>$existing,'challenge_pairs'=>$pairs,'fixture_ids'=>$created]);
            return count($created);
        });
    }
}
