<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
    /**
     * Return all types as JSON (for dynamic dropdowns).
     */
    public function index()
    {
        return response()->json(ExpenseType::ordered()->get());
    }

    /**
     * Create a new user-defined expense type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Derive a unique key from the label
        $key = $this->makeKey($validated['label']);

        $type = ExpenseType::create([
            'key'        => $key,
            'label'      => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? 100,
            'is_system'  => false,
        ]);

        return response()->json($type, 201);
    }

    /**
     * Update an existing expense type.
     */
    public function update(Request $request, ExpenseType $expenseType)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $expenseType->update([
            'label'      => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? $expenseType->sort_order,
        ]);

        return response()->json($expenseType);
    }

    /**
     * Delete a non-system expense type.
     */
    public function destroy(ExpenseType $expenseType)
    {
        if ($expenseType->is_system) {
            return response()->json(['message' => 'System expense types cannot be deleted.'], 422);
        }

        $expenseType->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /* ------------------------------------------------------------------
     | Private helpers
     * ------------------------------------------------------------------ */

    /**
     * Convert a label to a unique snake_case key.
     * e.g. "Kos & Drinkgoed" → "kos_drinkgoed"
     */
    private function makeKey(string $label): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        $base = trim($base, '_');
        $key  = $base;
        $i    = 2;

        while (ExpenseType::where('key', $key)->exists()) {
            $key = $base . '_' . $i++;
        }

        return $key;
    }
}
