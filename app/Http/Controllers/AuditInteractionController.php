<?php

namespace App\Http\Controllers;

use App\Support\Audit\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditInteractionController extends Controller
{
    public function store(Request $request, AuditWriter $writer): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:160'],
            'element' => ['nullable', 'string', 'max:40'],
            'target_path' => ['nullable', 'string', 'max:1000'],
            'page_path' => ['required', 'string', 'max:1000'],
            'client_at' => ['nullable', 'date'],
            'viewport_width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'viewport_height' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $writer->record([
            'category' => 'interaction',
            'action' => 'ui.'.$validated['action'],
            'metadata' => $validated,
        ]);

        return response()->json(['recorded' => true], 202);
    }
}
