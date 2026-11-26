<?php
require __DIR__.'/bootstrap.php';
$app = require 'D:/wamp64/www/ct/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$kernel->call('route:list', ['--path' => 'frontend/fixtures/draw/index', '--except-vendor' => true]);
echo $kernel->output();
$kernel->call('view:cache');
echo $kernel->output();
