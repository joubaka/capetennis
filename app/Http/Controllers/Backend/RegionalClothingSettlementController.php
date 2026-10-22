<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class RegionalClothingSettlementController extends Controller
{
    public function store(Request $request, Event $event, FinancialLedgerService $ledger)
    {
        $this->authorize('event-finance.manage', $event);
        abort_unless($request->user()->hasRole('super-user'), 403);
        $ledger->recordClothingSettlement($event, $request->user(), $request->all());

        return redirect()->to(route('admin.events.finances', $event).'#finance-clothing-settlements')
            ->with('success', 'Clothing settlement evidence recorded.');
    }
}
