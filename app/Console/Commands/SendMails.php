<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendMails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendMail:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled emails';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        info('Cron Job running at ' . now());

        // TODO: implement scheduled email sending logic here

        return Command::SUCCESS;
    }
}
