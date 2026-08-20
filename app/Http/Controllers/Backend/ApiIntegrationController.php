<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\ApiIntegrationStatusService;
use Illuminate\Http\Request;

class ApiIntegrationController extends Controller
{
    public function __construct(private readonly ApiIntegrationStatusService $statuses) {}

    public function index(Request $request)
    {
        $allIntegrations = $this->statuses->all();
        $integrations = $allIntegrations;
        $selectedStatus = trim((string) $request->query('status'));

        if ($selectedStatus !== '') {
            $integrations = $integrations
                ->where('status', $selectedStatus)
                ->values();
        }

        return view('backend.superadmin.api-integrations', [
            'integrations' => $integrations,
            'selectedStatus' => $selectedStatus,
            'summary' => [
                'total' => $allIntegrations->count(),
                'active' => $allIntegrations->whereIn('status', ['active', 'connected', 'rate_limited'])->count(),
                'connecting' => $allIntegrations->whereIn('status', ['connecting', 'awaiting_connection'])->count(),
                'attention' => $allIntegrations->whereIn('status', ['needs_attention', 'expired', 'inactive'])->count(),
            ],
        ]);
    }
}
