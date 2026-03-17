<?php

namespace App\Http\Controllers\backend;

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
        $payfastSettings = SiteSetting::where('group', 'payfast')->get()->keyBy('key');
        $paymentMethods  = SiteSetting::PAYMENT_METHOD_LABELS;

        return view('backend.settings.settings-index', compact('payfastSettings', 'paymentMethods'));
    }

    /**
     * Update settings.
     */
    public function store(Request $request)
    {
        $rules = [
            'payfast_fee_percentage' => 'required|numeric|min:0|max:100',
            'payfast_fee_flat'       => 'required|numeric|min:0',
            'payfast_vat_rate'       => 'required|numeric|min:0|max:100',
        ];

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            $rules["payfast_fee_pct_{$method}"] = 'required|numeric|min:0|max:100';
        }

        $request->validate($rules);

        SiteSetting::set('payfast_fee_percentage', $request->input('payfast_fee_percentage'));
        SiteSetting::set('payfast_fee_flat', $request->input('payfast_fee_flat'));
        SiteSetting::set('payfast_vat_rate', $request->input('payfast_vat_rate'));

        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            SiteSetting::set("payfast_fee_pct_{$method}", $request->input("payfast_fee_pct_{$method}"));
        }

        return redirect()->route('settings.index')
            ->with('success', 'PayFast fee settings updated successfully.');
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
