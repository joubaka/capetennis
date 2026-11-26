<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Entries\Services\CategoryCancellationService;
use App\Http\Controllers\Controller;
use App\Models\{CategoryEvent, Event};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        try {
            $data = $request->validate(['reason' => 'required|string|max:2000', 'send_emails' => 'nullable|boolean']);
            $preview = $service->preview($event, $categoryEvent, $request->user(), $data['reason'], (bool) ($data['send_emails'] ?? true));
        } catch (ValidationException $exception) {
            return $this->reviewError($request, $event, $categoryEvent, $exception);
        }
        return view('backend.event.individual.cancel-category', compact('event', 'categoryEvent', 'preview'));
    }

    public function store(Request $request, Event $event, CategoryEvent $categoryEvent, CategoryCancellationService $service)
    {
        $service->authorize($event, $categoryEvent, $request->user());
        try {
            $data = $request->validate(['preview_token' => 'required|string', 'confirm' => 'required|accepted']);
            $result = $service->cancel($event, $categoryEvent, $request->user(), $data['preview_token']);
        } catch (ValidationException $exception) {
            return $this->reviewError($request, $event, $categoryEvent, $exception);
        }
        return redirect()->route('admin.events.entries.new', $event)->with('success', $result['duplicate']
            ? 'This category was already cancelled. No refunds or emails were repeated.'
            : 'Category cancelled. R'.number_format($result['total'], 2).' refunded to original payer wallets. '.($result['emails_suppressed']
                ? 'No emails sent or queued, as reviewed.'
                : $result['queued'].' emails queued; inbox delivery is not yet confirmed.'));
    }

    private function reviewError(Request $request, Event $event, CategoryEvent $category, ValidationException $exception)
    {
        if ($request->expectsJson()) throw $exception;
        // The preview is a POST document; redirecting back to it would produce a GET 405.
        return redirect()->route('admin.category.cancel.show', [$event, $category])
            ->withErrors($exception->errors())->withInput($request->only('reason', 'send_emails'));
    }
}
