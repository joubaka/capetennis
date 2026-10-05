<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Services\SeriesCommunicationService;
use Illuminate\Http\Request;

class SeriesCommunicationController extends Controller
{
    public function review(Request $request, Series $series, SeriesCommunicationService $service)
    {
        $intent = $request->validate(['intent'=>'required|uuid'])['intent'];
        $batches = $service->batches($series,$request->user(),$intent);
        $emailCount = $batches->sum(fn ($batch)=>count($batch->recipients));
        $uniqueCount = $batches->flatMap(fn ($batch)=>array_column($batch->recipients,'email'))->unique()->count();

        return view('backend.series.email-review',compact('series','intent','batches','emailCount','uniqueCount'));
    }

    public function send(Request $request, Series $series, SeriesCommunicationService $service)
    {
        $data = $request->validate(['intent'=>'required|uuid','confirmed'=>'accepted','acknowledge_missing'=>'nullable|boolean']);
        $stats = $service->approve($series,$request->user(),$data['intent'],$request->boolean('acknowledge_missing'));
        $severity = $stats['duplicate'] ? 'info' : (!$stats['queued'] ? ($stats['pending'] ? 'warning' : 'error') : ($stats['failed'] || $stats['skipped'] || $stats['pending'] ? 'warning' : 'success'));
        $message = $stats['duplicate'] ? 'This intent was already approved. No additional emails were queued.'
            : "{$stats['queued']} emails submitted to the queue; {$stats['pending']} queue submissions unconfirmed; {$stats['failed']} failed to queue; {$stats['skipped']} excluded.";

        return redirect()->route('series.email.review',['series'=>$series,'intent'=>$data['intent']])->with($severity,$message);
    }
}
