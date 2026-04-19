<?php
  
namespace App\Jobs;

use App\Mail\jobError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendEmailTest;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
    protected $details;
  
    /**
     * Create a new job instance.
     */
    public function __construct($details)
    {
        $this->details = $details;
    }
  
    /**
     * Execute the job.
     */
  public function handle()
  {
    Log::info('[SendEmailJob] ▶️ START', [
      'to' => $this->details['email'] ?? null,
      'mailer' => $this->details['mailer'] ?? null,
      'subject' => $this->details['subject'] ?? null,
    ]);

    try {

      Mail::mailer($this->details['mailer'])
        ->to($this->details['email'])
        ->send(new SendEmailTest($this->details));

      Log::info('[SendEmailJob] ✅ SENT SUCCESS', [
        'to' => $this->details['email'],
      ]);

    } catch (\Throwable $e) {

      Log::error('[SendEmailJob] ❌ SEND FAILED', [
        'to' => $this->details['email'],
        'error' => $e->getMessage(),
      ]);

      throw $e; // important so failed_jobs table records it
    }
  }



}
