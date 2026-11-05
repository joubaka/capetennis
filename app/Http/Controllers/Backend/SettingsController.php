<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use App\Services\SettingsMutationService;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsMutationService $settings) {}

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
            'brand_logo_url'                   => 'nullable|string|max:2048',
            'withdrawal_deadline_days'         => 'nullable|integer|min:0|max:365',
            'player_plus_annual_price' => ['sometimes', 'required', 'string', 'regex:/^(0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/D'],
        ];

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            $rules["payfast_fee_pct_{$method}"] = 'required|numeric|min:0|max:100';
        }

        $request->validate($rules);
        $this->mutate($request, function (callable $set) use ($request): void {
            if ($request->has('player_plus_annual_price')) $this->savePlusPrice($request, $set);

            // PayFast
            $set('payfast_fee_percentage', $request->input('payfast_fee_percentage'), SiteSetting::GROUP_PAYFAST);
            $set('payfast_fee_flat', $request->input('payfast_fee_flat'), SiteSetting::GROUP_PAYFAST);
            $set('payfast_vat_rate', $request->input('payfast_vat_rate'), SiteSetting::GROUP_PAYFAST);

            foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
                $set("payfast_fee_pct_{$method}", $request->input("payfast_fee_pct_{$method}"), SiteSetting::GROUP_PAYFAST);
            }

            // General / Code of Conduct & Terms
            $set('require_code_of_conduct', $request->boolean('require_code_of_conduct') ? '1' : '0', SiteSetting::GROUP_GENERAL);
            $set('require_terms', $request->boolean('require_terms') ? '1' : '0', SiteSetting::GROUP_GENERAL);
            $set('require_profile_update', $request->boolean('require_profile_update') ? '1' : '0', SiteSetting::GROUP_GENERAL);
            if ($request->has(\App\Services\Performance\PlayerRatingAccess::SETTING)) {
                $set(\App\Services\Performance\PlayerRatingAccess::SETTING, $request->boolean(\App\Services\Performance\PlayerRatingAccess::SETTING) ? '1' : '0', SiteSetting::GROUP_GENERAL);
            }
            if ($request->has(\App\Services\Performance\PlayerRatingAccess::COMING_SOON_SETTING)) {
                $set(\App\Services\Performance\PlayerRatingAccess::COMING_SOON_SETTING, $request->boolean(\App\Services\Performance\PlayerRatingAccess::COMING_SOON_SETTING) ? '1' : '0', SiteSetting::GROUP_GENERAL);
            }
            $set(SiteSetting::DISCIPLINARY_SYSTEM_ENABLED, $request->boolean(SiteSetting::DISCIPLINARY_SYSTEM_ENABLED) ? '1' : '0', SiteSetting::GROUP_GENERAL);
            $set('brand_logo_url', trim((string) $request->input('brand_logo_url', '')), SiteSetting::GROUP_GENERAL);

            if ($request->filled('code_of_conduct_content')) {
                $set('code_of_conduct_content', $request->input('code_of_conduct_content'), SiteSetting::GROUP_GENERAL);
            }
            if ($request->filled('terms_conditions_content')) {
                $set('terms_conditions_content', $request->input('terms_conditions_content'), SiteSetting::GROUP_GENERAL);
            }

            // Email notifications (admin)
            $set('email_on_registration', $request->boolean('email_on_registration') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('email_on_withdrawal', $request->boolean('email_on_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('email_on_team_withdrawal', $request->boolean('email_on_team_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('email_on_wallet_topup', $request->boolean('email_on_wallet_topup') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('email_on_bank_refund_request', $request->boolean('email_on_bank_refund_request') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('email_on_violation', $request->boolean('email_on_violation') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('admin_notification_email', $request->input('admin_notification_email') ?? 'support@capetennis.co.za', SiteSetting::GROUP_EMAIL);

            // Player confirmation emails
            $set('player_email_on_registration', $request->boolean('player_email_on_registration') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('player_email_on_withdrawal', $request->boolean('player_email_on_withdrawal') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            $set('player_email_on_move', $request->boolean('player_email_on_move') ? '1' : '0', SiteSetting::GROUP_EMAIL);
            foreach (SiteSetting::AUTOMATED_EMAIL_TOGGLES as $key) {
                $set($key, $request->boolean($key) ? '1' : '0', SiteSetting::GROUP_EMAIL);
            }

            // Registration and withdrawal switches are saved only through storeSingle().
            $set('withdrawal_deadline_days', (string) max(0, (int) $request->input('withdrawal_deadline_days', 7)), SiteSetting::GROUP_REGISTRATION);
        });

        if ($request->ajax() || $request->expectsJson()) {
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
            'email_on_violation'           => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_registration' => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_withdrawal'   => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'player_email_on_move'         => [SiteSetting::GROUP_EMAIL, 'boolean'],
            'registration_open'            => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'withdrawal_allowed'           => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'profile_required_for_registration' => [SiteSetting::GROUP_REGISTRATION, 'boolean'],
            'require_code_of_conduct'      => [SiteSetting::GROUP_GENERAL, 'boolean'],
            'require_terms'                => [SiteSetting::GROUP_GENERAL, 'boolean'],
            'require_profile_update'       => [SiteSetting::GROUP_GENERAL, 'boolean'],
            SiteSetting::DISCIPLINARY_SYSTEM_ENABLED => [SiteSetting::GROUP_GENERAL, 'boolean'],
            \App\Services\Performance\PlayerRatingAccess::SETTING => [SiteSetting::GROUP_GENERAL, 'boolean'],
            \App\Services\Performance\PlayerRatingAccess::COMING_SOON_SETTING => [SiteSetting::GROUP_GENERAL, 'boolean'],
        ];
        foreach (SiteSetting::AUTOMATED_EMAIL_TOGGLES as $emailKey) {
            $allowedKeys[$emailKey] = [SiteSetting::GROUP_EMAIL, 'boolean'];
        }

        $request->validate(['key' => 'required|string', 'value' => 'required|in:0,1']);

        $key = $request->input('key');

        if (!array_key_exists($key, $allowedKeys)) {
            return response()->json(['success' => false, 'message' => 'Invalid setting key.'], 422);
        }

        [$group] = $allowedKeys[$key];

        $this->mutate($request, fn (callable $set) => $set($key, $request->input('value'), $group));

        return response()->json(['success' => true, 'message' => 'Setting saved.']);
    }

    public function storePlusPrice(Request $request)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $request->validate(['player_plus_annual_price' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/D']]);
        $this->mutate($request, fn (callable $set) => $this->savePlusPrice($request, $set));
        return response()->json(['success' => true, 'message' => 'Annual price saved for new checkouts. Existing checkout prices are unchanged.']);
    }

    private function savePlusPrice(Request $request, callable $set): void
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $cents = \App\Services\Performance\PlayerPlusService::parseAmount($request->input('player_plus_annual_price'));
        if ($cents === null || $cents < \App\Services\Performance\PlayerPlusService::MIN_PRICE_CENTS || $cents > \App\Services\Performance\PlayerPlusService::MAX_PRICE_CENTS) {
            throw \Illuminate\Validation\ValidationException::withMessages(['player_plus_annual_price' => 'Enter an annual price from R'
                .\App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::MIN_PRICE_CENTS).' to R'
                .number_format(\App\Services\Performance\PlayerPlusService::MAX_PRICE_CENTS / 100, 2).' with at most two decimal places.']);
        }
        $set(\App\Services\Performance\PlayerPlusService::PRICE_SETTING, (string) $cents, SiteSetting::GROUP_GENERAL);
    }

    public function storePlusMonthly(Request $request)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate(['player_plus_monthly_price' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/D'],
            'player_plus_monthly_enabled' => ['required', 'boolean']]);
        $cents = \App\Services\Performance\PlayerPlusService::parseAmount($data['player_plus_monthly_price']);
        if ($cents === null || $cents < \App\Services\Performance\PlayerPlusService::MIN_PRICE_CENTS || $cents > \App\Services\Performance\PlayerPlusService::MAX_PRICE_CENTS) {
            throw \Illuminate\Validation\ValidationException::withMessages(['player_plus_monthly_price' => 'Enter a monthly price from R5.00 to R99,999.99 with at most two decimal places.']);
        }
        abort_unless(\App\Services\Performance\PlayerPlusService::monthlyReady(), 422, 'Monthly subscriptions need the reviewed database migration before they can be enabled.');
        $this->mutate($request, function (callable $set) use ($cents, $data): void {
            $set(\App\Services\Performance\PlayerPlusService::MONTHLY_PRICE_SETTING, (string) $cents, SiteSetting::GROUP_GENERAL);
            $set(\App\Services\Performance\PlayerPlusService::MONTHLY_ENABLED_SETTING, $data['player_plus_monthly_enabled'] ? '1' : '0', SiteSetting::GROUP_GENERAL);
        });
        return response()->json(['success' => true, 'message' => 'Monthly settings saved for new subscriptions. Existing subscription prices and paid terms are unchanged.']);
    }

    /**
     * Save the subject and body for a player confirmation email template.
     */
    public function storeTemplate(Request $request)
    {
        $request->validate([
            'type'    => 'required|in:registration,withdrawal,move',
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:10000',
        ]);

        $type = $request->input('type');

        $this->mutate($request, function (callable $set) use ($request, $type): void {
            $set("player_email_subject_{$type}", $request->input('subject'), SiteSetting::GROUP_EMAIL);
            $set("player_email_body_{$type}",    $request->input('body'),    SiteSetting::GROUP_EMAIL);
        });

        return response()->json(['success' => true, 'message' => 'Email template saved.']);
    }

    /**
     * Save editable conduct, terms or convenor guidance content via AJAX.
     */
    public function storeContent(Request $request)
    {
        $request->validate([
            'key'     => 'required|in:code_of_conduct_content,terms_conditions_content,'.SiteSetting::COURT_CONVENOR_GUIDE,
            'content' => 'required|string|max:65535',
        ]);

        $this->mutate($request, fn (callable $set) => $set($request->input('key'), $request->input('content'), SiteSetting::GROUP_GENERAL));

        return response()->json(['success' => true, 'message' => 'Content saved successfully.']);
    }

    private function mutate(Request $request, callable $collect): void
    {
        $changes = [];
        $collect(function (string $key, mixed $value, string $group) use (&$changes): void {
            $changes[$key] = ['value' => $value, 'group' => $group];
        });
        $this->settings->apply($request->user(), $changes);
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
