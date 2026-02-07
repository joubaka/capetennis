<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\AdminFollowup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendAdminMail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle(UserRegistered $event)
{
    //perfom more actions(if need be)
    Mail::to('hermanustennisacemdy@gmail.com')->send(new AdminFollowup());
}
}
