<?php
$loader = require 'C:/wamp64/www/ct/vendor/autoload.php';
$loader->addPsr4('App\\', 'D:/wamp64/www/ct/app', true);
$loader->addPsr4('Tests\\', 'D:/wamp64/www/ct/tests', true);
$loader->addPsr4('Database\\Factories\\', 'D:/wamp64/www/ct/database/factories', true);
$loader->addPsr4('Database\\Seeders\\', 'D:/wamp64/www/ct/database/seeders', true);
$_ENV['APP_BASE_PATH'] = 'D:/wamp64/www/ct';
