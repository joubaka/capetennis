<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$scope = [17863 => [1819,216,8905,7939,375],18044 => [1846,228,9050,8068,760],19813 => [1909,232,10739,9554,285],20804 => [1919,233,11669,10330,285]];
$regs = array_keys($scope); $cats = array_column($scope,0);
$check = function ($ok,$why) { if (!$ok) throw new RuntimeException($why); };
$contains = function ($value) use (&$contains): bool {
    if (is_array($value) || is_object($value)) { foreach ((array)$value as $k=>$v) { if ((string)$k === '615' || $contains($v)) return true; } return false; }
    if (is_numeric($value)) return (float)$value === 615.0;
    if (is_string($value)) { $decoded=json_decode($value,true); if (is_array($decoded)) return $contains($decoded); }
    return false;
};
$backup = null;
try {
DB::transaction(function () use ($scope,$regs,$cats,$check,$contains,&$backup) {
    $players=DB::table('players')->whereIn('id',[615,5344])->orderBy('id')->lockForUpdate()->get();
    foreach ([615=>['Karli','2012-11-19'],5344=>['Karli(Cal)','2016-10-04']] as $id=>[$name,$dob]) {
        $p=$players->firstWhere('id',$id);
        $check($p && $p->name===$name && $p->surname==='Fourie' && substr($p->dateOfBirth,0,10)===$dob,"Identity mismatch #$id");
    }
    $orders=DB::table('registration_orders')->whereIn('id',array_column($scope,3))->orderBy('id')->lockForUpdate()->get();
    $check($orders->count()===4,'Missing orders');
    $items=DB::table('registration_order_items')->whereIn('order_id',$orders->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    $check(DB::table('registrations')->whereIn('id',$regs)->orderBy('id')->lockForUpdate()->get()->count()===4,'Missing registrations');
    $pivots=DB::table('player_registrations')->whereIn('registration_id',$regs)->lockForUpdate()->get();
    $entries=DB::table('category_event_registrations')->whereIn('registration_id',$regs)->lockForUpdate()->get();
    $check($pivots->count()===4 && $entries->count()===4,'Expected four exclusive singles entries');
    $owner=$pivots->where('player_id',615)->count()===4 ? 615 : 5344;
    $check($pivots->where('player_id',$owner)->count()===4,'Mixed registration owners');
    $invitations=DB::table('team_selection_invitations')->whereIn('player_id',[615,5344])->lockForUpdate()->get();
    $check($invitations->count()===1,'Additional team-selection invitations');
    $invitation=$invitations->first();
    $check((int)$invitation->id===19 && (int)$invitation->player_id===$owner && (int)$invitation->event_id===241 && (int)$invitation->import_id===1 && (int)$invitation->team_id===780 && (int)$invitation->ranking_list_id===950 && (int)$invitation->ranking_position===9 && (float)$invitation->total_points===780.0 && $invitation->status==='reserve' && !$invitation->order_id && !$invitation->roster_rank && !$invitation->accepted_at && !$invitation->paid_at && !$invitation->invited_at,'Reserve invitation changed');
    foreach (['payment_started_at','vacated_roster_rank','promoted_from_id'] as $field) $check(empty($invitation->{$field}),"Reserve has lifecycle history: $field");
    $import=DB::table('team_selection_imports')->where('id',1)->lockForUpdate()->first();
    $team=DB::table('teams')->where('id',780)->lockForUpdate()->first();
    $teamCategory=$team ? DB::table('category_events')->where('id',$team->category_event_id)->first() : null;
    $check($import && (int)$import->event_id===241 && (int)$import->series_id===17 && (int)$import->region_id===(int)$invitation->region_id && $team && (int)$team->region_id===(int)$invitation->region_id && $teamCategory && (int)$teamCategory->event_id===241,'Invitation relationships changed');
    $check(!DB::table('team_selection_invitations')->where('import_id',1)->where('player_id',5344)->where('id','!=',19)->exists(),'Target invitation collision');
    $invitationSnapshot=json_decode($invitation->snapshot_json ?? '{}',true,512,JSON_THROW_ON_ERROR);
    $check(is_array($invitationSnapshot) && ($invitationSnapshot['player_name']??null)===($owner===615?'Karli Fourie':'Karli(Cal) Fourie'),'Invitation snapshot identity mismatch');
    foreach ($scope as $rid=>[$cid,$eid,$iid,$oid,$price]) {
        $links=$pivots->where('registration_id',$rid);
        $check($links->count()===1 && (int)$links->first()->player_id===$owner,"Player association mismatch $rid");
        $item=$items->firstWhere('id',$iid); $entry=$entries->firstWhere('registration_id',$rid);
        $ce=DB::table('category_events')->where('id',$cid)->lockForUpdate()->first();
        $event=DB::table('events')->where('id',$eid)->lockForUpdate()->first();
        $check($item && (int)$item->registration_id===$rid && (int)$item->category_event_id===$cid && (int)$item->order_id===$oid && (int)$item->player_id===$owner && (float)$item->item_price===$price*1.0,"Order item mismatch $iid");
        $check($entry && (int)$entry->category_event_id===$cid && $entry->status==='active' && (int)$entry->payment_status_id===1 && !$entry->withdrawn_at && in_array($entry->refund_status,['','not_refunded',null],true),"Entry state changed $rid");
        $check($ce && (int)$ce->event_id===$eid && $event && $event->start_date>='2025-01-01',"Event scope mismatch $rid");
        $check(DB::table('registration_order_items')->where('registration_id',$rid)->count()===1,"Additional order item $rid");
        $order=$orders->firstWhere('id',$oid);
        $check((float)$order->wallet_reserved===0.0 && !(int)$order->wallet_debited && (!(int)$order->pay_status ? (!(int)$order->payfast_paid && !$order->payfast_handed_off_at) : (int)$order->payfast_paid===1),"Unexpected payment state $oid");
    }
    $recent=DB::table('player_registrations as pr')->join('category_event_registrations as cer','cer.registration_id','=','pr.registration_id')->join('category_events as ce','ce.id','=','cer.category_event_id')->join('events as e','e.id','=','ce.event_id')->where('pr.player_id',615)->where('e.start_date','>=','2025-01-01')->distinct()->pluck('pr.registration_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
    $expected=$regs; sort($expected);
    $check($recent===($owner===615?$expected:[]),'Additional recent source entries');
    $check(!DB::table('player_registrations as pr')->join('category_event_registrations as cer','cer.registration_id','=','pr.registration_id')->join('category_events as ce','ce.id','=','cer.category_event_id')->where('pr.player_id',5344)->whereIn('ce.event_id',array_column($scope,1))->whereNotIn('pr.registration_id',$regs)->exists(),'Target event collision');
    foreach (['team_players'=>['player_id'],'team_payment_orders'=>['player_id','beneficiary_player_id','effective_player_id'],'event_nominations'=>['player_id'],'invatations'=>['player_id'],'masters_invitations'=>['player_id'],'interprovincial_trial_invitations'=>['player_id'],'fixture_players'=>['team1_id','team2_id'],'team_fixture_players'=>['team1_id','team2_id'],'team_fixture_results'=>['match_winner_id','match_loser_id'],'ranking_scores'=>['player_id'],'ranking_score_legs'=>['player_id']] as $table=>$columns) {
        if (!Schema::hasTable($table)) continue;
        foreach ($columns as $column) if (Schema::hasColumn($table,$column)) $check(!DB::table($table)->whereIn($column,[615,5344])->exists(),"Unexpected reference $table.$column");
    }
    $check(!DB::table('positions')->whereIn('category_event_id',$cats)->whereIn('player_id',[615,5344])->exists(),'Unexpected recent positions');
    $series=DB::table('series')->where('id',17)->lockForUpdate()->first();
    $check($series && (int)$series->year===2026,'Series mismatch');
    $ranks=DB::table('series_rankings')->whereIn('id',[7230,13643])->orderBy('id')->lockForUpdate()->get();
    $check($ranks->count()===2,'Missing ranking snapshots');
    $check($import->ranking_run_id===$ranks->firstWhere('id',13643)->run_id && ($invitationSnapshot['ranking_run_id']??null)===$import->ranking_run_id,'Invitation ranking run mismatch');
    foreach ($ranks as $r) {
        $check((int)$r->player_id===$owner && (int)$r->series_id===17 && (int)$r->ranking_list_id===950 && (int)$r->category_id===40 && (int)$r->rank_position===9 && (int)$r->total_points===((int)$r->id===7230?400:780) && $r->status===((int)$r->id===7230?'archived':'published'),'Ranking snapshot changed');
        $meta=json_decode($r->meta_json,true,512,JSON_THROW_ON_ERROR);
        $legs=array_merge($meta['counting_legs']??[],$meta['dropped_legs']??[]);
        $check(count($legs)>0 && empty($meta['tie_decision']) && empty($meta['head_to_head_decision']),'Ranking decision requires review');
        foreach ($legs as $leg) $check(empty($leg['synthetic']) && in_array((int)($leg['category_event_id']??0),[1909,1919],true),'Ranking leg outside correction');
    }
    $check(!DB::table('series_rankings')->where('series_id',17)->whereIn('player_id',[615,5344])->whereNotIn('id',[7230,13643])->exists(),'Additional ranking rows');
    foreach (DB::table('series_rankings')->where('series_id',17)->get(['meta_json']) as $row) $check(!$contains($row->meta_json),'Ranking metadata references source player');
    foreach (['ranking_tie_decisions','ranking_head_to_head_confirmations'] as $table) {
        if (Schema::hasTable($table)) foreach (DB::table($table)->where('series_id',17)->lockForUpdate()->get() as $row) $check(!$contains($row),"Source identity in $table requires review");
    }
    if ($owner===5344) { echo "Already corrected. No changes.\n"; return; }
    $dir=storage_path('app/private/history-corrections');
    $oldMask=umask(0077);
    try {
        if (!is_dir($dir)) $check(mkdir($dir,0700,true),'Cannot create backup directory');
        $backup=$dir.'/karli-615-to-5344-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $data=['reason'=>'User requested 2025 onward event attribution correction, 615 to 5344','scope'=>$scope,'player_registrations'=>$pivots,'registration_order_items'=>$items,'registration_orders'=>$orders,'entries'=>$entries,'series_rankings'=>$ranks,'team_selection_invitations'=>$invitations];
        $json=json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $fh=fopen($backup,'x'); $check($fh!==false,'Cannot create backup');
        $check(chmod($backup,0600),'Cannot protect backup');
        $check(fwrite($fh,$json)===strlen($json),'Incomplete backup'); $check(fclose($fh),'Backup close failed');
    } finally { umask($oldMask); }
    $correctedAt=now()->format('Y-m-d H:i:s');
    $check(DB::table('player_registrations')->whereIn('registration_id',$regs)->where('player_id',615)->update(['player_id'=>5344,'updated_at'=>$correctedAt])===4,'Registration update count');
    $check(DB::table('registration_order_items')->whereIn('id',array_column($scope,2))->where('player_id',615)->update(['player_id'=>5344,'updated_at'=>$correctedAt])===4,'Order item update count');
    $check(DB::table('series_rankings')->whereIn('id',[7230,13643])->where('player_id',615)->update(['player_id'=>5344,'updated_at'=>$correctedAt])===2,'Ranking update count');
    $invitationSnapshot['player_name']='Karli(Cal) Fourie';
    $invitationJson=json_encode($invitationSnapshot,JSON_THROW_ON_ERROR);
    $check(DB::table('team_selection_invitations')->where('id',19)->where('player_id',615)->where('status','reserve')->whereNull('order_id')->update(['player_id'=>5344,'snapshot_json'=>$invitationJson,'updated_at'=>$correctedAt])===1,'Invitation update count');
    foreach ($items as $before) {
        $after=DB::table('registration_order_items')->where('id',$before->id)->first();
        $expected=(array)$before;
        if (in_array((int)$before->id,array_column($scope,2),true)) { $expected['player_id']=5344; $expected['updated_at']=$correctedAt; }
        $check((array)$after==$expected,'Order item post-check failed');
    }
    $check(DB::table('registration_orders')->whereIn('id',$orders->pluck('id'))->orderBy('id')->get()->toJson()===$orders->toJson(),'Order state changed');
    $check(DB::table('category_event_registrations')->whereIn('registration_id',$regs)->get()->toJson()===$entries->toJson(),'Entry state changed');
    foreach ($ranks as $before) {
        $expected=(array)$before; $expected['player_id']=5344; $expected['updated_at']=$correctedAt;
        $check((array)DB::table('series_rankings')->where('id',$before->id)->first()==$expected,'Ranking post-check failed');
    }
    $check(DB::table('player_registrations')->whereIn('registration_id',$regs)->where('player_id',5344)->count()===4,'Registration post-check failed');
    foreach ($regs as $rid) $check(DB::table('player_registrations')->where('registration_id',$rid)->count()===1 && DB::table('player_registrations')->where('registration_id',$rid)->where('player_id',5344)->count()===1,"Association post-check $rid");
    $afterInvitation=(array)DB::table('team_selection_invitations')->where('id',19)->first();
    $check(json_decode($afterInvitation['snapshot_json'],true,512,JSON_THROW_ON_ERROR)===$invitationSnapshot,'Invitation snapshot post-check');
    $expectedInvitation=(array)$invitation;
    $expectedInvitation['player_id']=5344; $expectedInvitation['updated_at']=$correctedAt;
    unset($afterInvitation['snapshot_json'],$expectedInvitation['snapshot_json']);
    $check($afterInvitation==$expectedInvitation,'Invitation state changed');
    activity('player-history-correction')->withProperties(['source_player_id'=>615,'target_player_id'=>5344,'registration_ids'=>$regs,'order_item_ids'=>array_column($scope,2),'ranking_ids'=>[7230,13643],'invitation_id'=>19,'backup'=>$backup,'original_payfast_transactions_preserved'=>true])->log('2025 onward event attribution corrected at user request');
});
echo $backup ? "COMMITTED: 4 registrations, 4 order-item player links, 2 ranking identities, 1 reserve invitation.\nBackup: $backup\n" : '';
} catch (Throwable $e) {
    fwrite(STDERR,"STOP: transaction rolled back — {$e->getMessage()}\n");
    if ($backup) fwrite(STDERR,"Pre-change backup: $backup\n");
    exit(1);
}
