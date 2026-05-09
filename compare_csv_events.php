<?php
/**
 * Compare PayFast CSV credits against transactions_pf for multiple events.
 * Excludes: Funds Sent, Payouts, Reversals, non-event rows (TeamSheet, Hostking).
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// All CREDIT rows from CSV (Funds Received only, excluding reversals/payouts/hostking/teamsheet)
// Format: [pf_payment_id, event_id from custom_int3, registration_id from custom_int5, gross]
$csvCredits = [
    // event 222 - Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials Leg 2
    ['pf'=>'299881295','event'=>222,'reg'=>9497,'gross'=>285.00],
    ['pf'=>'299736399','event'=>222,'reg'=>9494,'gross'=>285.00],
    ['pf'=>'299725251','event'=>222,'reg'=>9493,'gross'=>285.00],
    ['pf'=>'299714155','event'=>222,'reg'=>9490,'gross'=>285.00],
    ['pf'=>'299672049','event'=>222,'reg'=>9489,'gross'=>285.00],
    ['pf'=>'299670781','event'=>222,'reg'=>9488,'gross'=>285.00],
    ['pf'=>'299621133','event'=>222,'reg'=>9484,'gross'=>285.00],
    ['pf'=>'299591917','event'=>222,'reg'=>9483,'gross'=>570.00],
    ['pf'=>'299584855','event'=>222,'reg'=>9481,'gross'=>285.00],
    ['pf'=>'299571877','event'=>222,'reg'=>9479,'gross'=>570.00],
    ['pf'=>'299558993','event'=>222,'reg'=>9478,'gross'=>285.00],
    ['pf'=>'299547723','event'=>222,'reg'=>9477,'gross'=>285.00],
    ['pf'=>'299525919','event'=>222,'reg'=>9475,'gross'=>285.00],
    ['pf'=>'299483441','event'=>222,'reg'=>9473,'gross'=>285.00],
    ['pf'=>'299477759','event'=>222,'reg'=>9472,'gross'=>570.00],
    ['pf'=>'299474261','event'=>222,'reg'=>9471,'gross'=>285.00],
    ['pf'=>'299414901','event'=>222,'reg'=>9468,'gross'=>285.00],
    ['pf'=>'299413621','event'=>222,'reg'=>9467,'gross'=>285.00],
    ['pf'=>'299344941','event'=>222,'reg'=>9459,'gross'=>570.00],
    ['pf'=>'299287511','event'=>222,'reg'=>9456,'gross'=>285.00],
    ['pf'=>'299239277','event'=>222,'reg'=>9453,'gross'=>285.00],
    ['pf'=>'299194751','event'=>222,'reg'=>9452,'gross'=>285.00],
    ['pf'=>'299193517','event'=>222,'reg'=>9450,'gross'=>570.00],
    ['pf'=>'299142521','event'=>222,'reg'=>9448,'gross'=>285.00],
    ['pf'=>'299138105','event'=>222,'reg'=>9447,'gross'=>285.00],
    ['pf'=>'299128537','event'=>222,'reg'=>9446,'gross'=>285.00],
    ['pf'=>'299009851','event'=>222,'reg'=>9441,'gross'=>285.00],
    ['pf'=>'298926979','event'=>222,'reg'=>9438,'gross'=>285.00],
    ['pf'=>'298926163','event'=>222,'reg'=>9437,'gross'=>285.00],
    ['pf'=>'298922405','event'=>222,'reg'=>9436,'gross'=>285.00],
    ['pf'=>'298848927','event'=>222,'reg'=>9433,'gross'=>285.00],
    ['pf'=>'298848321','event'=>222,'reg'=>9432,'gross'=>285.00],
    ['pf'=>'298772383','event'=>222,'reg'=>9430,'gross'=>285.00],
    ['pf'=>'298749409','event'=>222,'reg'=>9429,'gross'=>285.00],
    ['pf'=>'298713469','event'=>222,'reg'=>9424,'gross'=>285.00],
    ['pf'=>'298671569','event'=>222,'reg'=>9425,'gross'=>285.00],
    ['pf'=>'298482439','event'=>222,'reg'=>9421,'gross'=>285.00],
    ['pf'=>'298478105','event'=>222,'reg'=>9420,'gross'=>285.00],
    ['pf'=>'298153311','event'=>222,'reg'=>9416,'gross'=>285.00],
    ['pf'=>'298138437','event'=>222,'reg'=>9415,'gross'=>285.00],
    ['pf'=>'297460943','event'=>222,'reg'=>9414,'gross'=>285.00],
    ['pf'=>'297346121','event'=>222,'reg'=>9413,'gross'=>285.00],
    ['pf'=>'296870241','event'=>222,'reg'=>9411,'gross'=>285.00],
    ['pf'=>'296696351','event'=>222,'reg'=>9410,'gross'=>285.00],
    ['pf'=>'296634377','event'=>222,'reg'=>9408,'gross'=>285.00],
    ['pf'=>'295949805','event'=>222,'reg'=>9406,'gross'=>570.00],
    ['pf'=>'295851349','event'=>222,'reg'=>9405,'gross'=>285.00],
    ['pf'=>'295680755','event'=>222,'reg'=>9404,'gross'=>285.00],
    ['pf'=>'295585917','event'=>222,'reg'=>9396,'gross'=>570.00],
    ['pf'=>'295540343','event'=>222,'reg'=>9379,'gross'=>285.00],
    ['pf'=>'295529061','event'=>222,'reg'=>9375,'gross'=>285.00],
    ['pf'=>'295300147','event'=>222,'reg'=>9332,'gross'=>285.00],
    ['pf'=>'295265297','event'=>222,'reg'=>9324,'gross'=>285.00],
    ['pf'=>'295156829','event'=>222,'reg'=>9310,'gross'=>285.00],
    ['pf'=>'295477177','event'=>222,'reg'=>9370,'gross'=>285.00],
    ['pf'=>'294963003','event'=>222,'reg'=>9273,'gross'=>285.00],
    ['pf'=>'294689419','event'=>222,'reg'=>9261,'gross'=>285.00],
    ['pf'=>'294453961','event'=>222,'reg'=>9252,'gross'=>285.00],
    ['pf'=>'294435667','event'=>222,'reg'=>9250,'gross'=>285.00],
    ['pf'=>'294435113','event'=>222,'reg'=>9249,'gross'=>285.00],
    ['pf'=>'294149061','event'=>222,'reg'=>9234,'gross'=>285.00],
    ['pf'=>'293915551','event'=>222,'reg'=>9222,'gross'=>285.00],
    ['pf'=>'293886289','event'=>222,'reg'=>9217,'gross'=>285.00],
    ['pf'=>'293183905','event'=>222,'reg'=>9178,'gross'=>285.00],
    ['pf'=>'292633389','event'=>222,'reg'=>9168,'gross'=>285.00],
    ['pf'=>'291669519','event'=>222,'reg'=>9161,'gross'=>28.50],
    ['pf'=>'291667889','event'=>222,'reg'=>9160,'gross'=>28.50],

    // event 232 - Overberg Tennis Trials Leg 2
    ['pf'=>'299838551','event'=>232,'reg'=>9496,'gross'=>285.00],
    ['pf'=>'299791033','event'=>232,'reg'=>9495,'gross'=>285.00],
    ['pf'=>'299579839','event'=>232,'reg'=>9480,'gross'=>285.00],
    ['pf'=>'299330009','event'=>232,'reg'=>9457,'gross'=>285.00],
    ['pf'=>'299099955','event'=>232,'reg'=>9443,'gross'=>285.00],
    ['pf'=>'298872035','event'=>232,'reg'=>9434,'gross'=>285.00],
    ['pf'=>'298491029','event'=>232,'reg'=>9422,'gross'=>570.00],
    ['pf'=>'298456985','event'=>232,'reg'=>9419,'gross'=>285.00],
    ['pf'=>'298247171','event'=>232,'reg'=>9417,'gross'=>570.00],
    ['pf'=>'295547941','event'=>232,'reg'=>9380,'gross'=>285.00],
    ['pf'=>'295388521','event'=>232,'reg'=>9356,'gross'=>570.00],
    ['pf'=>'294688193','event'=>232,'reg'=>9260,'gross'=>285.00],
    ['pf'=>'294275763','event'=>232,'reg'=>9236,'gross'=>285.00],
    ['pf'=>'292238663','event'=>232,'reg'=>9165,'gross'=>285.00],

    // event 239 - West Coast Primary Schools Trials 2
    ['pf'=>'299720067','event'=>239,'reg'=>9492,'gross'=>285.00],
    ['pf'=>'299638591','event'=>239,'reg'=>9486,'gross'=>285.00],
    ['pf'=>'298496813','event'=>239,'reg'=>9423,'gross'=>285.00],
    ['pf'=>'296692023','event'=>239,'reg'=>9409,'gross'=>285.00],
    ['pf'=>'293036075','event'=>239,'reg'=>9175,'gross'=>285.00],
    ['pf'=>'291647877','event'=>239,'reg'=>9158,'gross'=>285.00],

    // event 236 - Cavaliers Junior Worcester Tournament 2026
    ['pf'=>'299193707','event'=>236,'reg'=>9451,'gross'=>285.00],
    ['pf'=>'298946049','event'=>236,'reg'=>9439,'gross'=>285.00],
    ['pf'=>'298249251','event'=>236,'reg'=>9418,'gross'=>570.00],

    // event 244 - High Schools Witzenberg/Breede Vallei/Langeberg 2026 Leg 2
    ['pf'=>'295252841','event'=>244,'reg'=>9318,'gross'=>285.00],
    ['pf'=>'295149565','event'=>244,'reg'=>9309,'gross'=>285.00],
    ['pf'=>'295146107','event'=>244,'reg'=>9305,'gross'=>285.00],
    ['pf'=>'295138937','event'=>244,'reg'=>9300,'gross'=>570.00],
    ['pf'=>'294962521','event'=>244,'reg'=>9272,'gross'=>285.00],
    ['pf'=>'294434551','event'=>244,'reg'=>9248,'gross'=>285.00],
    ['pf'=>'294558557','event'=>244,'reg'=>9254,'gross'=>285.00],
    ['pf'=>'294074181','event'=>244,'reg'=>9231,'gross'=>285.00],
    ['pf'=>'294012879','event'=>244,'reg'=>9229,'gross'=>285.00],
    ['pf'=>'293947021','event'=>244,'reg'=>9225,'gross'=>285.00],
    ['pf'=>'293891997','event'=>244,'reg'=>9221,'gross'=>285.00],
    ['pf'=>'293386787','event'=>244,'reg'=>9186,'gross'=>285.00],
    ['pf'=>'293275113','event'=>244,'reg'=>9185,'gross'=>285.00],
    ['pf'=>'293070921','event'=>244,'reg'=>9177,'gross'=>285.00],
    ['pf'=>'293051747','event'=>244,'reg'=>9176,'gross'=>285.00],
    ['pf'=>'292991161','event'=>244,'reg'=>9172,'gross'=>285.00], // wait - this is Primary schools... let me recheck
    ['pf'=>'294086503','event'=>244,'reg'=>9232,'gross'=>285.00],

    // event 235 - Cavaliers Junior Ceres Tournament 2026
    ['pf'=>'299193707','event'=>236,'reg'=>9451,'gross'=>285.00], // already above - skip dupe
    ['pf'=>'295594571','event'=>235,'reg'=>9403,'gross'=>570.00],
    ['pf'=>'295592155','event'=>235,'reg'=>9402,'gross'=>285.00],
    ['pf'=>'295591831','event'=>235,'reg'=>9401,'gross'=>285.00],
    ['pf'=>'295589997','event'=>235,'reg'=>9400,'gross'=>285.00],
    ['pf'=>'295589391','event'=>235,'reg'=>9399,'gross'=>285.00],
    ['pf'=>'295587915','event'=>235,'reg'=>9397,'gross'=>285.00],
    ['pf'=>'295584327','event'=>235,'reg'=>9395,'gross'=>570.00],
    ['pf'=>'295583687','event'=>235,'reg'=>9394,'gross'=>285.00],
    ['pf'=>'295583209','event'=>235,'reg'=>9393,'gross'=>285.00],
    ['pf'=>'295578121','event'=>235,'reg'=>9392,'gross'=>285.00],
    ['pf'=>'295576933','event'=>235,'reg'=>9390,'gross'=>285.00],
    ['pf'=>'295577065','event'=>235,'reg'=>9391,'gross'=>285.00],
    ['pf'=>'295576077','event'=>235,'reg'=>9389,'gross'=>570.00],
    ['pf'=>'295575627','event'=>235,'reg'=>9388,'gross'=>285.00],
    ['pf'=>'295570215','event'=>235,'reg'=>9387,'gross'=>570.00],
    ['pf'=>'295569125','event'=>235,'reg'=>9386,'gross'=>285.00],
    ['pf'=>'295568583','event'=>235,'reg'=>9385,'gross'=>285.00],
    ['pf'=>'295566593','event'=>235,'reg'=>9382,'gross'=>285.00],
    ['pf'=>'295567851','event'=>235,'reg'=>9384,'gross'=>285.00],
    ['pf'=>'295567495','event'=>235,'reg'=>9383,'gross'=>285.00],
    ['pf'=>'295554983','event'=>235,'reg'=>9381,'gross'=>285.00],
    ['pf'=>'295540893','event'=>235,'reg'=>9377,'gross'=>285.00],
    ['pf'=>'295540019','event'=>235,'reg'=>9378,'gross'=>285.00],
    ['pf'=>'295530667','event'=>235,'reg'=>9376,'gross'=>285.00],
    ['pf'=>'295528297','event'=>235,'reg'=>9374,'gross'=>285.00],
    ['pf'=>'295520101','event'=>235,'reg'=>9373,'gross'=>285.00],
    ['pf'=>'295506383','event'=>235,'reg'=>9372,'gross'=>285.00],
    ['pf'=>'295500837','event'=>235,'reg'=>9371,'gross'=>570.00],
    ['pf'=>'295475859','event'=>235,'reg'=>9369,'gross'=>285.00],
    ['pf'=>'295475745','event'=>235,'reg'=>9368,'gross'=>285.00],
    ['pf'=>'295460427','event'=>235,'reg'=>9366,'gross'=>285.00],
    ['pf'=>'295459081','event'=>235,'reg'=>9365,'gross'=>570.00],
    ['pf'=>'295442359','event'=>235,'reg'=>9364,'gross'=>285.00],
    ['pf'=>'295437411','event'=>235,'reg'=>9363,'gross'=>570.00],
    ['pf'=>'295436033','event'=>235,'reg'=>9362,'gross'=>285.00],
    ['pf'=>'295435735','event'=>235,'reg'=>9361,'gross'=>285.00],
    ['pf'=>'295435175','event'=>235,'reg'=>9360,'gross'=>285.00],
    ['pf'=>'295433051','event'=>235,'reg'=>9359,'gross'=>570.00],
    ['pf'=>'295428039','event'=>235,'reg'=>9358,'gross'=>285.00],
    ['pf'=>'295427013','event'=>235,'reg'=>9357,'gross'=>285.00],
    ['pf'=>'295388089','event'=>235,'reg'=>9355,'gross'=>570.00],
    ['pf'=>'295384215','event'=>235,'reg'=>9353,'gross'=>570.00],
    ['pf'=>'295379159','event'=>235,'reg'=>9352,'gross'=>285.00],
    ['pf'=>'295378797','event'=>235,'reg'=>9351,'gross'=>285.00],
    ['pf'=>'295378413','event'=>235,'reg'=>9349,'gross'=>285.00],
    ['pf'=>'295377005','event'=>235,'reg'=>9348,'gross'=>285.00],
    ['pf'=>'295374871','event'=>235,'reg'=>9346,'gross'=>285.00],
    ['pf'=>'295374759','event'=>235,'reg'=>9345,'gross'=>285.00],
    ['pf'=>'295360923','event'=>235,'reg'=>9344,'gross'=>570.00],
    ['pf'=>'295360541','event'=>235,'reg'=>9343,'gross'=>285.00],
    ['pf'=>'295353117','event'=>235,'reg'=>9341,'gross'=>285.00],
    ['pf'=>'295344477','event'=>235,'reg'=>9340,'gross'=>285.00],
    ['pf'=>'295325381','event'=>235,'reg'=>9339,'gross'=>285.00],
    ['pf'=>'295323591','event'=>235,'reg'=>9338,'gross'=>285.00],
    ['pf'=>'295321815','event'=>235,'reg'=>9337,'gross'=>285.00],
    ['pf'=>'295318463','event'=>235,'reg'=>9336,'gross'=>285.00],
    ['pf'=>'295305899','event'=>235,'reg'=>9335,'gross'=>285.00],
    ['pf'=>'295301571','event'=>235,'reg'=>9333,'gross'=>285.00],
    ['pf'=>'295286603','event'=>235,'reg'=>9331,'gross'=>285.00],
    ['pf'=>'295282079','event'=>235,'reg'=>9330,'gross'=>285.00],
    ['pf'=>'295280947','event'=>235,'reg'=>9329,'gross'=>285.00],
    ['pf'=>'295279715','event'=>235,'reg'=>9328,'gross'=>570.00],
    ['pf'=>'295272123','event'=>235,'reg'=>9325,'gross'=>285.00],
    ['pf'=>'295272341','event'=>235,'reg'=>9326,'gross'=>285.00],
    ['pf'=>'295261073','event'=>235,'reg'=>9323,'gross'=>285.00],
    ['pf'=>'295260461','event'=>235,'reg'=>9322,'gross'=>285.00],
    ['pf'=>'295255907','event'=>235,'reg'=>9319,'gross'=>285.00],
    ['pf'=>'295256025','event'=>235,'reg'=>9320,'gross'=>570.00],
    ['pf'=>'295249345','event'=>235,'reg'=>9317,'gross'=>570.00],
    ['pf'=>'295208865','event'=>235,'reg'=>9313,'gross'=>285.00],
    ['pf'=>'295171175','event'=>235,'reg'=>9312,'gross'=>285.00],
    ['pf'=>'295160471','event'=>235,'reg'=>9311,'gross'=>285.00],
    ['pf'=>'295147289','event'=>235,'reg'=>9308,'gross'=>285.00],
    ['pf'=>'295146617','event'=>235,'reg'=>9307,'gross'=>285.00],
    ['pf'=>'295144263','event'=>235,'reg'=>9304,'gross'=>285.00],
    ['pf'=>'295139893','event'=>235,'reg'=>9302,'gross'=>285.00],
    ['pf'=>'295140157','event'=>235,'reg'=>9303,'gross'=>285.00],
    ['pf'=>'295139261','event'=>235,'reg'=>9301,'gross'=>285.00],
    ['pf'=>'295107833','event'=>235,'reg'=>9298,'gross'=>570.00],
    ['pf'=>'295089821','event'=>235,'reg'=>9297,'gross'=>285.00],
    ['pf'=>'295083599','event'=>235,'reg'=>9296,'gross'=>285.00],
    ['pf'=>'295081779','event'=>235,'reg'=>9295,'gross'=>285.00],
    ['pf'=>'295077159','event'=>235,'reg'=>9294,'gross'=>285.00],
    ['pf'=>'295072569','event'=>235,'reg'=>9293,'gross'=>285.00],
    ['pf'=>'295041695','event'=>235,'reg'=>9276,'gross'=>285.00],
    ['pf'=>'295018345','event'=>235,'reg'=>9275,'gross'=>570.00],
    ['pf'=>'295016301','event'=>235,'reg'=>9274,'gross'=>285.00],
    ['pf'=>'294961817','event'=>235,'reg'=>9271,'gross'=>285.00],
    ['pf'=>'294947951','event'=>235,'reg'=>9270,'gross'=>285.00],
    ['pf'=>'294905881','event'=>235,'reg'=>9268,'gross'=>285.00],
    ['pf'=>'294838639','event'=>235,'reg'=>9267,'gross'=>570.00],
    ['pf'=>'294828221','event'=>235,'reg'=>9266,'gross'=>570.00],
    ['pf'=>'294783495','event'=>235,'reg'=>9265,'gross'=>285.00],
    ['pf'=>'294690219','event'=>235,'reg'=>9262,'gross'=>285.00],
    ['pf'=>'294643427','event'=>235,'reg'=>9259,'gross'=>285.00], // original credit (reversal is the debit row - skip that)
    ['pf'=>'294600099','event'=>235,'reg'=>9258,'gross'=>285.00],
    ['pf'=>'294598459','event'=>235,'reg'=>9257,'gross'=>285.00],
    ['pf'=>'294589665','event'=>235,'reg'=>9256,'gross'=>285.00],
    ['pf'=>'294580765','event'=>235,'reg'=>9255,'gross'=>285.00],
    ['pf'=>'294452931','event'=>235,'reg'=>9251,'gross'=>570.00],
    ['pf'=>'294431763','event'=>235,'reg'=>9246,'gross'=>570.00],
    ['pf'=>'294430589','event'=>235,'reg'=>9245,'gross'=>285.00],
    ['pf'=>'294386305','event'=>235,'reg'=>9243,'gross'=>285.00],
    ['pf'=>'294315023','event'=>235,'reg'=>9241,'gross'=>570.00],
    ['pf'=>'294307477','event'=>235,'reg'=>9239,'gross'=>285.00],
    ['pf'=>'294294505','event'=>235,'reg'=>9238,'gross'=>285.00],
    ['pf'=>'294210599','event'=>235,'reg'=>9235,'gross'=>570.00],
    ['pf'=>'294012581','event'=>235,'reg'=>9228,'gross'=>285.00],
    ['pf'=>'293953267','event'=>235,'reg'=>9226,'gross'=>285.00],
    ['pf'=>'293935039','event'=>235,'reg'=>9224,'gross'=>570.00],
    ['pf'=>'293890767','event'=>235,'reg'=>9220,'gross'=>855.00],
    ['pf'=>'293888703','event'=>235,'reg'=>9218,'gross'=>285.00],
    ['pf'=>'293441897','event'=>235,'reg'=>9192,'gross'=>285.00],
    ['pf'=>'293248469','event'=>235,'reg'=>9183,'gross'=>855.00],
    ['pf'=>'293237051','event'=>235,'reg'=>9182,'gross'=>285.00],
    ['pf'=>'293203283','event'=>235,'reg'=>9180,'gross'=>285.00],
    ['pf'=>'293184379','event'=>235,'reg'=>9179,'gross'=>285.00],
    ['pf'=>'293032413','event'=>235,'reg'=>9173,'gross'=>285.00],
    ['pf'=>'292839601','event'=>235,'reg'=>9171,'gross'=>570.00],
    ['pf'=>'292689515','event'=>235,'reg'=>9169,'gross'=>285.00],
    ['pf'=>'292633241','event'=>235,'reg'=>9167,'gross'=>285.00],
    ['pf'=>'292449317','event'=>235,'reg'=>9166,'gross'=>285.00],
    ['pf'=>'292209607','event'=>235,'reg'=>9164,'gross'=>285.00],
    ['pf'=>'292146561','event'=>235,'reg'=>9163,'gross'=>285.00],
    ['pf'=>'291932769','event'=>235,'reg'=>9162,'gross'=>285.00],
];

// Deduplicate (in case any pf_id appears twice due to copy-paste above)
$seen = [];
$unique = [];
foreach ($csvCredits as $row) {
    if (!isset($seen[$row['pf']])) {
        $seen[$row['pf']] = true;
        $unique[] = $row;
    }
}
$csvCredits = $unique;

$totalCsv = count($csvCredits);
echo "Total CSV credit rows (deduped): {$totalCsv}\n\n";

// Check each pf_id against transactions_pf
$missing   = [];
$present   = [];

foreach ($csvCredits as $row) {
    $exists = DB::table('transactions_pf')
        ->where('pf_payment_id', $row['pf'])
        ->exists();
    if ($exists) {
        $present[] = $row;
    } else {
        $missing[] = $row;
    }
}

echo "In transactions_pf : " . count($present) . "\n";
echo "MISSING            : " . count($missing) . "\n\n";

if (empty($missing)) {
    echo "✅ All CSV credits are present in transactions_pf.\n";
} else {
    // Group missing by event
    $byEvent = [];
    foreach ($missing as $m) {
        $byEvent[$m['event']][] = $m;
    }
    ksort($byEvent);

    foreach ($byEvent as $eventId => $rows) {
        $eventName = DB::table('events')->where('id', $eventId)->value('name') ?? "Event {$eventId}";
        echo "--- Event {$eventId}: {$eventName} (" . count($rows) . " missing) ---\n";
        foreach ($rows as $r) {
            // Try to get registration info
            $reg = DB::table('category_event_registrations')->where('id', $r['reg'])->first();
            $player = $reg ? DB::table('players')->where('id', $reg->player_id ?? 0)->first() : null;
            $playerName = $player ? trim(($player->name ?? '') . ' ' . ($player->surname ?? '')) : '?';
            echo "  pf_id={$r['pf']} reg={$r['reg']} player={$playerName} gross=R{$r['gross']}\n";
        }
        echo "\n";
    }
}

// Also show DB counts per event for these events
echo "--- DB counts per event ---\n";
$eventIds = array_unique(array_column($csvCredits, 'event'));
sort($eventIds);
foreach ($eventIds as $eid) {
    $count = DB::table('transactions_pf')
        ->where('event_id', $eid)
        ->where('transaction_type', 'Registration')
        ->where('is_test', false)
        ->count();
    $csvCount = count(array_filter($csvCredits, fn($r) => $r['event'] == $eid));
    echo "  Event {$eid}: DB={$count}, CSV credits={$csvCount}\n";
}
