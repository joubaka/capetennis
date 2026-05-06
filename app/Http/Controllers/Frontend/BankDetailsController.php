<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankDetailsController extends Controller
{
    const BANK_NAMES = [
        'ABSA'          => 'ABSA',
        'Capitec'       => 'Capitec',
        'FNB'           => 'FNB',
        'Nedbank'       => 'Nedbank',
        'Standard Bank' => 'Standard Bank',
        'Investec'      => 'Investec',
        'African Bank'  => 'African Bank',
        'TymeBank'      => 'TymeBank',
        'Discovery'     => 'Discovery Bank',
        'Bidvest'       => 'Bidvest Bank',
    ];

    public function show(Request $request, CategoryEventRegistration $registration)
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired or is invalid.');
        abort_if($registration->refund_status === 'completed', 410, 'This refund has already been processed.');

        $event         = $registration->categoryEvent?->event;
        $player        = $registration->players->first();
        $playerName    = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
        $alreadyFilled = !empty($registration->refund_account_name);
        $bankNames     = self::BANK_NAMES;

        return view('frontend.refund.bank-details', compact(
            'registration', 'event', 'playerName', 'alreadyFilled', 'bankNames'
        ));
    }

    public function store(Request $request, CategoryEventRegistration $registration)
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired or is invalid.');
        abort_if($registration->refund_status === 'completed', 410, 'This refund has already been processed.');

        $validated = $request->validate([
            'refund_account_name'   => ['required', 'string', 'max:255'],
            'refund_bank_name'      => ['required', 'string', 'max:255'],
            'refund_account_number' => ['required', 'string', 'max:20'],
            'refund_branch_code'    => ['required', 'digits_between:4,10'],
            'refund_account_type'   => ['required', 'in:current,savings'],
        ]);

        $registration->update($validated);

        Log::info('BANK DETAILS SUBMITTED BY USER', [
            'registration_id' => $registration->id,
            'bank_name'       => $validated['refund_bank_name'],
            'account_type'    => $validated['refund_account_type'],
        ]);

        return view('frontend.refund.bank-details-submitted', compact('registration'));
    }
}
