<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Entries\Services\CategoryCancellationService;
use App\Http\Controllers\Controller;
use App\Models\{CategoryEvent, Event};
use Illuminate\Http\Request;

class CategoryCancellationController extends Controller
{
    public function show(Request $request, Event $event, CategoryEvent $categoryEvent, CategoryCancellationService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        return view('backend.event.individual.cancel-category', ['event' => $event, 'categoryEvent' => $categoryEvent, 'preview' => null]);
    }

    public function preview(Request $request, Event $event, CategoryEvent $categoryEvent, CategoryCancellationService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $preview = $service->preview($event, $categoryEvent, $request->user(), $data['reason']);
        return view('backend.event.individual.cancel-category', compact('event', 'categoryEvent', 'preview'));
    }

    public function store(Request $request, Event $event, CategoryEvent $categoryEvent, CategoryCancellationService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        $data = $request->validate(['preview_token' => 'required|string', 'confirm' => 'required|accepted']);
        $result = $service->cancel($event, $categoryEvent, $request->user(), $data['preview_token']);
        return redirect()->route('admin.events.entries.new', $event)->with('success', $result['duplicate']
            ? 'This category was already cancelled. No refunds or emails were repeated.'
            : 'Category cancelled. R'.number_format($result['total'], 2).' refunded to original payer wallets. '.$result['queued'].' emails queued; inbox delivery is not yet confirmed.');
    }
}
