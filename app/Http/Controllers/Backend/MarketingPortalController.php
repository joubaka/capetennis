<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{MarketingCampaign, MarketingContact};
use App\Services\Marketing\{HostCampaignTemplate, PanarottisCalculator};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingPortalController extends Controller
{
    public function index()
    {
        return redirect()->route('superadmin.marketing.contacts.index');
    }

    public function contacts(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $search = trim($data['search'] ?? '');
        $contacts = MarketingContact::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('academy', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')
            ->orWhere('town', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('academy')->orderBy('id')->paginate(20)->withQueryString();
        return view('backend.marketing.contacts', compact('contacts', 'search'));
    }

    public function contactForm(?MarketingContact $contact = null)
    {
        return view('backend.marketing.contact-form', ['contact' => $contact ?? new MarketingContact]);
    }

    public function saveContact(Request $request, ?MarketingContact $contact = null)
    {
        $data = $request->validate([
            'academy' => 'required|string|max:255', 'name' => 'required|string|max:255',
            'email' => 'nullable|email:rfc|max:255', 'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/'],
            'town' => 'nullable|string|max:255', 'courts' => 'nullable|integer|between:0,100',
            'status' => ['required', Rule::in(['prospect', 'interested', 'discussing', 'approved', 'inactive'])],
            'preference' => ['required', Rule::in(['none', 'email', 'whatsapp', 'both', 'opted_out'])],
            'notes' => 'nullable|string|max:10000', 'version' => 'sometimes|integer|min:0',
        ]);
        if (!empty($data['email'])) $data['email'] = mb_strtolower(trim($data['email']));
        $this->save($contact ?? new MarketingContact, $data, $request);
        return redirect()->route('superadmin.marketing.contacts.index')->with('success', 'Coach contact saved.');
    }

    public function deleteContact(Request $request, MarketingContact $contact)
    {
        $this->remove($contact, $request);
        return redirect()->route('superadmin.marketing.contacts.index')->with('success', 'Coach contact deleted.');
    }

    public function campaigns()
    {
        return view('backend.marketing.campaigns', ['campaigns' => MarketingCampaign::query()->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)]);
    }

    public function campaignForm(?MarketingCampaign $campaign = null)
    {
        $campaign ??= new MarketingCampaign(['title' => 'CTR academy host invitation', 'subject' => 'Host CTR tournaments and generate funds',
            'email_body' => HostCampaignTemplate::body(), 'whatsapp_body' => HostCampaignTemplate::whatsappBody()]);
        return view('backend.marketing.campaign-form', compact('campaign'));
    }

    public function saveCampaign(Request $request, ?MarketingCampaign $campaign = null)
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'subject' => 'nullable|string|max:255',
            'email_body' => 'required|string|max:30000', 'whatsapp_body' => 'required|string|max:30000', 'version' => 'sometimes|integer|min:0']);
        $this->save($campaign ?? new MarketingCampaign, $data, $request);
        return redirect()->route('superadmin.marketing.campaigns.index')->with('success', 'Campaign draft saved. Nothing was sent.');
    }

    public function deleteCampaign(Request $request, MarketingCampaign $campaign)
    {
        $this->remove($campaign, $request);
        return redirect()->route('superadmin.marketing.campaigns.index')->with('success', 'Campaign draft deleted.');
    }

    public function calculator(Request $request, PanarottisCalculator $calculator)
    {
        $input = $request->isMethod('post') ? $request->all() : $calculator->defaults();
        $result = $calculator->calculate($input);
        return view('backend.marketing.calculator', compact('input', 'result'));
    }

    private function save($model, array $data, Request $request): void
    {
        DB::transaction(function () use ($model, $data, $request) {
            if ($model->exists) {
                $model = $model->newQuery()->lockForUpdate()->findOrFail($model->id);
                abort_unless($request->has('version') && (int) $request->input('version') === (int) $model->version, 409, 'This record changed. Reload before saving.');
            }
            unset($data['version']);
            $model->fill($data);
            $model->version = (int) $model->version + 1;
            $model->save();
        });
    }

    private function remove($model, Request $request): void
    {
        $request->validate(['version' => 'required|integer|min:0']);
        DB::transaction(function () use ($model, $request) {
            $model = $model->newQuery()->lockForUpdate()->findOrFail($model->id);
            abort_unless((int) $request->input('version') === (int) $model->version, 409, 'This record changed. Reload before deleting.');
            $model->delete();
        });
    }
}
