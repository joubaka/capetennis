<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventSettingsController extends Controller
{
  /**
   * Display the event settings page
   */
  public function index(Event $event)
  {
    return view('backend.event.settings', [
      'event' => $event,
    ]);
  }

  /**
   * Update event settings (AJAX, partial-safe)
   */
  public function update(Request $request, Event $event)
  {
    Log::info('🛠 Event settings update START', [
      'event_id' => $event->id,
      'payload' => $request->all(),
      'user_id' => auth()->id(),
    ]);

    // ✅ PARTIAL-SAFE VALIDATION
    $data = $request->validate([
      'name' => 'sometimes|required|string|max:255',
      'status' => 'sometimes|nullable|string',
      'start_date' => 'sometimes|nullable|date',
      'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
      'information' => 'sometimes|nullable|string',
      'venue_notes' => 'sometimes|nullable|string',
      'entryFee' => 'sometimes|nullable|integer',
      'deadline' => 'sometimes|nullable|integer',
      'withdrawal_deadline' => 'sometimes|nullable|date',
      'eventType' => 'sometimes|required|integer',
      'email' => 'sometimes|nullable|email',
      'published' => 'sometimes|boolean',
      'signUp' => 'sometimes|boolean',
      'organizer' => 'sometimes|nullable|string|max:191',

      'logo_existing' => 'sometimes|nullable|string',
      'logo_upload' => 'sometimes|image|max:2048',

      'admins' => 'sometimes|array',
      'admins.*' => 'integer|exists:users,id',
    ]);

    Log::debug('📥 Validated data', $data);

    /**
     * LOGO HANDLING
     */
    if ($request->hasFile('logo_upload')) {
      $file = $request->file('logo_upload');

      $filename = Str::slug(
        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
      ) . '.' . $file->getClientOriginalExtension();

      $file->move(public_path('assets/img/logos'), $filename);
      $event->logo = $filename;

      Log::info('🖼 Logo uploaded', ['logo' => $filename]);
    } elseif (!empty($data['logo_existing'])) {
      $event->logo = basename($data['logo_existing']);
    }

    /**
     * UPDATE EVENT FIELDS
     */
    $updateData = collect($data)
      ->except(['admins', 'logo_upload', 'logo_existing'])
      ->toArray();

    // Boolean safety
    if ($request->has('published')) {
      $updateData['published'] = $request->boolean('published');
    }
    if ($request->has('signUp')) {
      $updateData['signUp'] = $request->boolean('signUp');
    }

    Log::debug('🔁 Mapped data', $updateData);

    $event->update($updateData);

    /**
     * ADMINS
     */
    if ($request->has('admins')) {
      Log::info('👥 Syncing admins', [
        'event_id' => $event->id,
        'admins' => $request->input('admins'),
      ]);

      $event->admins()->sync($request->input('admins', []));
    }

    Log::info('✅ Event settings update COMPLETE', [
      'event_id' => $event->id,
    ]);

    return response()->json(['success' => true]);
  }

  /**
   * Update category fee override (AJAX)
   */
  public function updateCategoryFee(Request $request, CategoryEvent $categoryEvent)
  {
    $data = $request->validate([
      'entry_fee' => 'nullable|integer|min:0',
      'enabled' => 'nullable|boolean',
    ]);

    if (array_key_exists('enabled', $data) && !$data['enabled']) {
      $data['entry_fee'] = null;
    }

    unset($data['enabled']);

    $categoryEvent->update($data);

    return response()->json(['success' => true]);
  }
}
