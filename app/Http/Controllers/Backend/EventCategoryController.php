<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Category;
use App\Models\CategoryEvent;
use Illuminate\Http\Request;

class EventCategoryController extends Controller
{
  /**
   * List all categories for an event
   * (with entry counts and fee overrides)
   */
  public function index(Event $event)
  {
    // Load category events sorted alphabetically by category name
    $categoryEvents = $event->categoryEvents()
      ->join('categories', 'categories.id', '=', 'category_events.category_id')
      ->orderBy('categories.name')
      ->select('category_events.*')
      ->with([
        'category',
        'categoryEventRegistrations',
      ])
      ->get();

    // ✅ ALL categories (required for Select2 preselect)
    $allCategories = Category::orderBy('name')->get();

    // (Optional – keep if used elsewhere, NOT for Select2)
    $availableCategories = Category::whereNotIn(
      'id',
      $categoryEvents->pluck('category_id')
    )
      ->orderBy('name')
      ->get();

    return view('backend.event.categories', [
      'event' => $event,
      'categoryEvents' => $categoryEvents,
      'allCategories' => $allCategories,   // 🔴 THIS WAS MISSING
      'availableCategories' => $availableCategories, // optional
    ]);
  }

  /**
   * Attach an existing category to the event
   */
  public function attach(Request $request, Event $event)
  {
    $validated = $request->validate([
      'category_ids' => ['required', 'array', 'min:1'],
      'category_ids.*' => ['integer', 'exists:categories,id'],
    ]);

    foreach ($validated['category_ids'] as $categoryId) {
      $event->categoryEvents()->firstOrCreate(
        ['category_id' => $categoryId],
        [] // future-proof if you add extra columns later
      );
    }

    return redirect()
      ->back()
      ->with('success', 'Categories added successfully.');
  }



  /**
   * Create a new category and attach it to the event
   */
  public function createAndAttach(Request $request, Event $event)
  {
    $data = $request->validate([
      'name' => 'required|string|max:255|unique:categories,name',
    ]);

    $category = Category::create([
      'name' => $data['name'],
    ]);

    $event->categoryEvents()->create([
      'category_id' => $category->id,
    ]);

    return back()->with('success', 'Category created and added to event.');
  }

  /**
   * Delete a single category IF empty
   */
  public function destroy(CategoryEvent $categoryEvent)
  {
    if ($categoryEvent->categoryEventRegistrations()->exists()) {
      return response()->json([
        'message' => 'Category has players and cannot be removed.'
      ], 422);
    }

    $categoryEvent->delete();

    return response()->json([
      'message' => 'Category removed successfully.'
    ]);
  }

  /**
   * Bulk cleanup: remove all empty categories for event
   */
  public function cleanup(Event $event)
  {
    $removed = $event->categoryEvents()
      ->whereDoesntHave('categoryEventRegistrations')
      ->delete();

    return response()->json([
      'removed' => $removed
    ]);
  }
}
