<?php

namespace App\Http\Controllers;

use App\Events\AnnouncementPost;
use App\Events\UserRegistered;
use App\Models\Event;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriberController extends Controller
{
    public function subscribe(Request $request){

        //validate the request data
        $validator = Validator::make($request->all(), [
            'email'  =>  'required|email',
        ]);
    
        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'message' => $validator->errors()], 422);
        }
    
        // subscribe to the newsletter
        Subscriber::create([
            'email'=> $request->email
        ]);
    
      // call the event
       // event(new UserRegistered($request->email));
       $data['message'] = 'message';
       $data['event'] = 'test';
       $sendMail =1;

       
       $event = Event::find(115);
       $registrations  = $event->registrations;
       foreach ($registrations as $key => $reg) {
           $emails[] = $reg->registration->players[0]->email;
       }

       $data['message'] = $request->data;
       $data['recipants'] = $emails;
       $data['event'] = $event->name;
       // call the event
       // event(new UserRegistered('p@c.co.za'));
     
     
       if ($sendMail == 1) {
           event(new AnnouncementPost($data));
       }
       return new JsonResponse(['success' => true, 'message' => "Email send to all registrations"], 500);
        event(new AnnouncementPost($data));
        return new JsonResponse(['success' => true, 'message' => "Thank you for subscribing to the Sample newsletter!"], 200);
    }
}
