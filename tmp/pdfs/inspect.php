<?php
require 'C:/wamp64/www/ct/vendor/autoload.php';
$app = require 'C:/wamp64/www/ct/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::statement('SET TRANSACTION READ ONLY');
DB::beginTransaction();
try {
$e=App\Models\Event::find(252);
echo json_encode(['event'=>$e?->only(['id','name','updated_at']),'drafts'=>DB::table('cavaliers_trial_roster_drafts')->where('event_id',252)->get()->map(fn($r)=>['division'=>$r->category_event_id,'selected'=>count(json_decode($r->selected,true)),'updated_at'=>$r->updated_at??null,'keys'=>array_keys((array)$r)])->all()],JSON_PRETTY_PRINT);
} finally {DB::rollBack();}
