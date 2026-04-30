<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        $payfastSettings     = SiteSetting::where('group', SiteSetting::GROUP_PAYFAST)->get()->keyBy('key');
        $paymentMethods      = SiteSetting::PAYMENT_METHOD_LABELS;
        $generalSettings     = SiteSetting::where('group', SiteSetting::GROUP_GENERAL)->get()->pluck('value', 'key')->toArray();
        $emailSettings       = SiteSetting::where('group', SiteSetting::GROUP_EMAIL)->get()->pluck('value', 'key')->toArray();
        $registrationSettings = SiteSetting::where('group', SiteSetting::GROUP_REGISTRATION)->get()->pluck('value', 'key')->toArray();

        return view('backend.settings.settings-index', compact(
            'payfastSettings',
            'paymentMethods',
            'generalSettings',
            'emailSettings',
            'registrationSettings',
        ));
    }

    /**
     * Update settings.
     */
    public function store(Request $request)
    {
        $rules = [
            'payfast_fee_percentage'           => 'required|numeric|min:0|max:100',
            'payfast_fee_flat'                 => 'required|numeric|min:0',
            'payfast_vat_rate'                 => 'required|numeric|min:0|max:100',
            'admin_notification_email'         => 'nullable|email|max:255',
            'withdrawal_deadline_days'         => 'nullable|integer|min:0|max:365',
        ];

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            $rules["payfast_fee_pct_{$method}"] = 'required|numeric|min:0|max:100';
        }

        $request->validate($rules);

        // PayFast
        SiteSetting::set('payfast_fee_percentage', $request->input('payfast_fee_percentage'));
        SiteSetting::set('payfast_fee_flat', $request->input('payfast_fee_flat'));
        SiteSetting::set('payfast_vat_rate', $request->input('payfast_vat_rate'));

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            SiteSetting::set("payfast_fee_pct_{$method}", $request->input("payfast_fee_pct_{$method}"));
        }

        // General / Code of Conduct & Terms
        SiteSetting::set('require_code_of_conduct', $request->boolean('require_code_of_conduct') ? '1' : '0');
        SiteSetting::set('require_terms', $request->boolean('require_terms') ? '1' : '0');
        SiteSetting::set('require_profile_update', $request->boolean('require_profile_update') ? '1' : '0');

        // Email notifications
        SiteSetting::set('email_on_registration', $request->boolean('email_on_registration') ? '1' : '0');
        SiteSetting::set('email_on_withdrawal', $request->boolean('email_on_withdrawal') ? '1' : '0');
        SiteSetting::set('email_on_team_withdrawal', $request->boolean('email_on_team_withdrawal') ? '1' : '0');
        SiteSetting::set('email_on_wallet_topup', $request->boolean('email_on_wallet_topup') ? '1' : '0');
        SiteSetting::set('email_on_bank_refund_request', $request->boolean('email_on_bank_refund_request') ? '1' : '0');
        SiteSetting::set('admin_notification_email', $request->input('admin_notification_email') ?? 'support@capetennis.co.za');

        // Registration & withdrawal behaviour
        SiteSetting::set('registration_open', $request->boolean('registration_open') ? '1' : '0');
        SiteSetting::set('withdrawal_allowed', $request->boolean('withdrawal_allowed') ? '1' : '0');
        SiteSetting::set('withdrawal_deadline_days', (string) max(0, (int) $request->input('withdrawal_deadline_days', 7)));
        SiteSetting::set('profile_required_for_registration', $request->boolean('profile_required_for_registration') ? '1' : '0');

        return redirect()->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
