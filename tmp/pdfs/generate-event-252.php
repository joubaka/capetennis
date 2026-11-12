<?php
require 'C:/wamp64/www/ct/vendor/autoload.php';
$app = require 'C:/wamp64/www/ct/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::statement('SET TRANSACTION READ ONLY');
DB::beginTransaction();
try {
$event=App\Models\Event::findOrFail(252);
$actor=App\Models\User::role('admin')->whereIn('id',App\Models\EventAdmin::where('event_id',252)->select('user_id'))->first();
$actor ??= App\Models\User::role('super-user')->firstOrFail();
$service=app(App\Services\CavaliersRosterReport::class);
$report=$service->report($event,$actor);
$report['event_name'] .= ' - Local data snapshot';
$bytes=$service->pdf($report);
file_put_contents('C:/wamp64/www/ct/output/pdf/cavaliers-event-252-nomination-review.pdf',$bytes);
echo json_encode(['source'=>'local database snapshot','event'=>$report['event_name'],'sections'=>collect($report['sections'])->map(fn($s)=>['division'=>$s['division'],...$service->sectionSummary($s)])->all(),'unsaved'=>$report['unsaved_divisions'],'bytes'=>strlen($bytes)],JSON_PRETTY_PRINT);
} finally {DB::rollBack();}

