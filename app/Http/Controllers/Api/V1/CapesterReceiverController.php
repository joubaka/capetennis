<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Assistant\CapesterSupportQueue;
use Illuminate\Http\Request;

class CapesterReceiverController extends Controller
{
    public function claim(Request $request, CapesterSupportQueue $queue)
    {
        return response()->json(['request' => $queue->claim($request->user(), (int) $request->user()->currentAccessToken()->getKey())])
            ->header('Cache-Control', 'no-store');
    }

    public function acknowledge(Request $request, string $uuid, CapesterSupportQueue $queue)
    {
        validator(['uuid' => $uuid], ['uuid' => ['required', 'uuid']])->validate();
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'result' => ['required', 'in:received,hello_passed,hello_failed']]);
        $queue->acknowledge($request->user(), (int) $request->user()->currentAccessToken()->getKey(), $uuid, $data['token'], $data['result']);
        return response()->json(['acknowledged' => true])->header('Cache-Control', 'no-store');
    }
}
