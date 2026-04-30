<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DisciplineSetting;
use App\Models\ViolationType;
use Illuminate\Http\Request;

class DisciplineSettingsController extends Controller
{
    /**
     * Show the settings panel (violation types + threshold settings).
     */
    public function index()
    {
        $violationTypes = ViolationType::orderBy('category')->orderBy('name')->get();
        $settings       = DisciplineSetting::all()->keyBy('key');

        return view('backend.disciplinary.settings', compact('violationTypes', 'settings'));
    }

    /**
     * Update threshold/expiry settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'suspension_threshold'     => 'required|integer|min:1|max:1000',
            'expiry_days'              => 'required|integer|min:1|max:3650',
            'first_suspension_months'  => 'required|integer|min:1|max:120',
            'second_suspension_months' => 'required|integer|min:1|max:120',
        ]);

        foreach ($validated as $key => $value) {
            DisciplineSetting::set($key, $value);
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Create a new violation type.
     */
    public function storeViolationType(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'category'       => 'required|in:on_court,withdrawal,no_show,abuse',
            'default_points' => 'required|integer|min:0|max:100',
            'description'    => 'nullable|string|max:500',
            'active'         => 'boolean',
        ]);

        ViolationType::create($validated + ['active' => $request->boolean('active', true)]);

        return back()->with('success', 'Violation type created.');
    }

    /**
     * Update an existing violation type.
     */
    public function updateViolationType(Request $request, ViolationType $violationType)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'category'       => 'required|in:on_court,withdrawal,no_show,abuse',
            'default_points' => 'required|integer|min:0|max:100',
            'description'    => 'nullable|string|max:500',
            'active'         => 'boolean',
        ]);

        $violationType->update($validated + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Violation type updated.');
    }

    /**
     * Delete a violation type (only if no violations reference it).
     */
    public function destroyViolationType(ViolationType $violationType)
    {
        if ($violationType->violations()->exists()) {
            return back()->with('error', 'Cannot delete: this type is used by existing violations.');
        }

        $violationType->delete();

        return back()->with('success', 'Violation type deleted.');
    }
}
