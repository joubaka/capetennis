<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    /**
     * List all agreements.
     */
    public function index()
    {
        $agreements = Agreement::orderByDesc('created_at')->get();

        return view('backend.agreements.index', compact('agreements'));
    }

    /**
     * Show form to create a new agreement.
     */
    public function create()
    {
        return view('backend.agreements.create');
    }

    /**
     * Store a new agreement.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'content' => 'required',
        ]);

        Agreement::create([
            'title' => $request->title,
            'version' => $request->version,
            'content' => $request->content,
            'is_active' => 0,
        ]);

        return redirect()->route('backend.agreements.index')
            ->with('success', 'Agreement created successfully.');
    }

    /**
     * Show a single agreement.
     */
    public function show(Agreement $agreement)
    {
        $acceptances = $agreement->playerAgreements()
            ->with('player')
            ->orderByDesc('accepted_at')
            ->get();

        return view('backend.agreements.show', compact('agreement', 'acceptances'));
    }

    /**
     * Show form to edit an agreement (only if not active).
     */
    public function edit(Agreement $agreement)
    {
        if ($agreement->is_active) {
            return redirect()->route('backend.agreements.index')
                ->with('error', 'Cannot edit an active agreement. Duplicate it to create a new version.');
        }

        return view('backend.agreements.edit', compact('agreement'));
    }

    /**
     * Update an agreement (only if not active).
     */
    public function update(Request $request, Agreement $agreement)
    {
        if ($agreement->is_active) {
            return redirect()->route('backend.agreements.index')
                ->with('error', 'Cannot edit an active agreement. Duplicate it to create a new version.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'content' => 'required',
        ]);

        $agreement->update([
            'title' => $request->title,
            'version' => $request->version,
            'content' => $request->content,
        ]);

        return redirect()->route('backend.agreements.index')
            ->with('success', 'Agreement updated successfully.');
    }

    /**
     * Duplicate an agreement to create a new version.
     */
    public function duplicate(Agreement $agreement)
    {
        $new = $agreement->replicate();
        $new->version = 'v' . (intval(substr($agreement->version, 1)) + 1);
        $new->is_active = 0;
        $new->save();

        return redirect()->route('backend.agreements.index')
            ->with('success', 'Agreement duplicated as ' . $new->version . '.');
    }

    /**
     * Set an agreement as the active one (deactivates all others).
     */
    public function setActive(Agreement $agreement)
    {
        Agreement::query()->update(['is_active' => 0]);
        $agreement->update(['is_active' => 1]);

        return redirect()->route('backend.agreements.index')
            ->with('success', 'Agreement ' . $agreement->version . ' is now active. Players will need to re-accept.');
    }
}
