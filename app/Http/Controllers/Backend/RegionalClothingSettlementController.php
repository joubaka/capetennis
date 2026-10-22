<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

class RegionalClothingSettlementController extends Controller
{
    public function users(Request $request, Event $event)
    {
        $this->authorize('event-finance.manage', $event);
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }
        return response()->json(User::query()
            ->where(fn ($users) => $users->where('name', 'like', '%'.$query.'%')->orWhere('email', 'like', '%'.$query.'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get(['id', 'name', 'email'])
            ->map(fn ($user) => ['id' => $user->id, 'text' => $user->name.' ('.$user->email.')']));
    }

    public function store(Request $request, Event $event, FinancialLedgerService $ledger)
    {
        $this->authorize('event-finance.manage', $event);
        abort_unless($request->user()->hasRole('super-user'), 403);
        $ledger->recordClothingSettlement($event, $request->user(), $request->all());

        return redirect()->to(route('admin.events.finances', $event).'#finance-clothing-settlements')
            ->with('success', 'Clothing settlement evidence recorded.');
    }
}
