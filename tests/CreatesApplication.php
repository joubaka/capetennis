<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (getenv('CT_TEST_ARRAY_TRANSPORTS') === '1') {
            // Managed dispatchers choose named mailers and can bypass mail.default.
            foreach (array_keys(config('mail.mailers', [])) as $name) {
                config(["mail.mailers.{$name}" => ['transport' => 'array']]);
            }
        }
        if (getenv('CT_TEST_SQLITE_FAST') === '1') {
            config(['database.connections.sqlite.synchronous' => 'OFF']);
        }

        return $app;
    }
}
