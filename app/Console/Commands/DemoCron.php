<?php
  
namespace App\Console\Commands;

use App\Models\Covid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use PHPUnit\Util\Test;

class DemoCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:cron';
  
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
  
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        info("Cron Job running at ". now());
    
        /*------------------------------------------
        --------------------------------------------
        Write Your Logic Here....
        I am getting users and create new users if not exist....
        --------------------------------------------
        --------------------------------------------*/
        $response = Http::get('https://jsonplaceholder.typicode.com/users');
        
        $users = $response->json();
    
        for ($i=0; $i < 5; $i++) { 
           
                $test = new Covid();
                $test->player_name = $i;
                $test->save();
            
        }
           
        
    }
}