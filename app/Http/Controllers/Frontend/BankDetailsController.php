<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\User;
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

    /**
     * Show the bank details form for all pending bank refunds of this user.
     */
    public function show(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired or is invalid.');

        $registrations = CategoryEventRegistration::with('categoryEvent.event', 'categoryEvent.category', 'players')
            ->where('user_id', $user->id)
            ->where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->get();

        abort_if($registrations->isEmpty(), 410, 'No pending bank refunds found.');

        $alreadyFilled = !empty($registrations->first()->refund_account_name);
        $bankNames     = self::BANK_NAMES;

        return view('frontend.refund.bank-details', compact(
            'user', 'registrations', 'alreadyFilled', 'bankNames'
        ));
    }

    /**
     * Save bank details and apply to ALL pending bank refund registrations for this user.
     */
    public function store(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired or is invalid.');

        $registrations = CategoryEventRegistration::where('user_id', $user->id)
            ->where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->get();

        abort_if($registrations->isEmpty(), 410, 'No pending bank refunds found.');

        $validated = $request->validate([
            'refund_account_name'   => ['required', 'string', 'max:255'],
            'refund_bank_name'      => ['required', 'string', 'max:255'],
            'refund_account_number' => ['required', 'string', 'max:20'],
            'refund_branch_code'    => ['required', 'digits_between:4,10'],
            'refund_account_type'   => ['required', 'in:current,savings'],
        ]);

        // Apply the same bank details to every pending registration for this user
        foreach ($registrations as $registration) {
            $registration->update($validated);
        }

        Log::info('BANK DETAILS SUBMITTED BY USER (multi-registration)', [
            'user_id'          => $user->id,
            'registration_ids' => $registrations->pluck('id')->toArray(),
            'bank_name'        => $validated['refund_bank_name'],
            'account_type'     => $validated['refund_account_type'],
            'count'            => $registrations->count(),
        ]);

        return view('frontend.refund.bank-details-submitted', compact('user', 'registrations'));
    }
}
