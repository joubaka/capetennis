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
        SiteSetting::set('payfast_fee_percentage', $request->input('payfast_fee_percentage'), SiteSetting::GROUP_PAYFAST);
        SiteSetting::set('payfast_fee_flat', $request->input('payfast_fee_flat'), SiteSetting::GROUP_PAYFAST);
        SiteSetting::set('payfast_vat_rate', $request->input('payfast_vat_rate'), SiteSetting::GROUP_PAYFAST);

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            SiteSetting::set("payfast_fee_pct_{$method}", $request->input("payfast_fee_pct_{$method}"), SiteSetting::GROUP_PAYFAST);
        }

        // General / Code of Conduct & Terms
        SiteSetting::set('require_code_of_conduct', $request->boolean('require_code_of_conduct') ? '1' : '0', SiteSetting::GROUP_GENERAL);
        SiteSetting::set('require_terms', $request->boolean('require_terms') ? '1' : '0', SiteSetting::GROUP_GENERAL);
        SiteSetting::set('require_profile_update', $request->boolean('require_profile_update') ? '1' : '0', SiteSetting::GROUP_GENERAL);

        // Email notifications (admin)
        SiteSetting::set('email_on_registration', $request->boolean('email_on_registration') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_withdrawal', $request->boolean('email_on_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_team_withdrawal', $request->boolean('email_on_team_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_wallet_topup', $request->boolean('email_on_wallet_topup') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_bank_refund_request', $request->boolean('email_on_bank_refund_request') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('admin_notification_email', $request->input('admin_notification_email') ?? 'support@capetennis.co.za', SiteSetting::GROUP_EMAIL);

        // Player confirmation emails
        SiteSetting::set('player_email_on_registration', $request->boolean('player_email_on_registration') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('player_email_on_withdrawal', $request->boolean('player_email_on_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('player_email_on_move', $request->boolean('player_email_on_move') ? '1' : '0', SiteSetting::GROUP_EMAIL);

        // Registration & withdrawal behaviour
        SiteSetting::set('registration_open', $request->boolean('registration_open') ? '1' : '0', SiteSetting::GROUP_REGISTRATION);
        SiteSetting::set('withdrawal_allowed', $request->boolean('withdrawal_allowed') ? '1' : '0', SiteSetting::GROUP_REGISTRATION);
        SiteSetting::set('withdrawal_deadline_days', (string) max(0, (int) $request->input('withdrawal_deadline_days', 7)), SiteSetting::GROUP_REGISTRATION);
        SiteSetting::set('profile_required_for_registration', $request->boolean('profile_required_for_registration') ? '1' : '0', SiteSetting::GROUP_REGISTRATION);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Settings saved successfully.']);
        }

        if ($request->input('_settings_origin') === 'superadmin') {
            return redirect()->route('backend.superadmin.index')
                ->with('success', 'Settings updated successfully.')
                ->with('open_settings_tab', true);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    /**
     * Save a single boolean/text setting via AJAX (used by auto-save on toggle).
     */
    public function storeSingle(Request $request)
    {
        $allowedKeys = [
            'email_on_registration'        => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'email_on_withdrawal'          => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'email_on_team_withdrawal'     => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'email_on_wallet_topup'        => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'email_on_bank_refund_request' => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_registration' => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_withdrawal'   => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_move'         => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'registration_open'            => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'withdrawal_allowed'           => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'profile_required_for_registration' => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'require_code_of_conduct'      => [SiteSetting::GROUP_GENERAL, 'boolean'],
            'require_terms'                => [SiteSetting::GROUP_GENERAL, 'boolean'],
            'require_profile_update'       => [SiteSetting::GROUP_GENERAL, 'boolean'],
        ];

        $request->validate(['key' => 'required|string', 'value' => 'required|in:0,1']);

        $key = $request->input('key');

        if (!array_key_exists($key, $allowedKeys)) {
            return response()->json(['success' => false, 'message' => 'Invalid setting key.'], 422);
        }

        [$group] = $allowedKeys[$key];

        SiteSetting::set($key, $request->input('value'), $group);

        return response()->json(['success' => true, 'message' => 'Setting saved.']);
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
