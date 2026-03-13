<?php
require __DIR__.'/vendor/autoload.php';
$a=require_once __DIR__.'/bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$total=App\Models\User::count();
$withW=App\Models\User::whereHas('wallet')->count();
echo 'Total users: '.$total.PHP_EOL;
echo 'With wallet: '.$withW.PHP_EOL;
echo 'Without wallet: '.($total-$withW).PHP_EOL;
$noWallet=App\Models\User::whereDoesntHave('wallet')->select('id','name','email')->limit(20)->get();
echo PHP_EOL.'First 20 users WITHOUT wallet:'.PHP_EOL;
foreach($noWallet as $u){echo '  ID:'.$u->id.' | '.$u->name.' | '.$u->email.PHP_EOL;}
echo PHP_EOL.'Wallet table count: '.App\Models\Wallet::count().PHP_EOL;