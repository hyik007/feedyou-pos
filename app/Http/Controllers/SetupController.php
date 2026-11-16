<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\AI\Contracts\LlmClient;
use App\Services\Settings\SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * 1-click setup: restaurant profile, tax/service charge, AI key, staff PIN.
 * Open on first run; once a staff PIN exists it sits behind StaffAuth.
 */
class SetupController extends Controller
{
    public function __construct(private SettingsStore $settings) {}

    public function show(LlmClient $llm): View
    {
        $outlet = Outlet::query()->first();

        return view('setup', [
            'restaurantName' => $this->settings->restaurantName(),
            'outlet' => $outlet,
            'hasApiKey' => $llm->isConfigured(),
            'hasPin' => (bool) $this->settings->staffPinHash(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_rate' => ['required', 'numeric', 'between:0,30'],
            'service_charge' => ['required', 'numeric', 'between:0,30'],
            'gemini_api_key' => ['nullable', 'string', 'max:200'],
            'staff_pin' => ['nullable', 'digits_between:4,8'],
        ]);

        $this->settings->set('restaurant_name', $data['restaurant_name']);
        // Blank key field = keep the existing key (we never echo secrets back into the form).
        if (filled($data['gemini_api_key'] ?? null)) {
            $this->settings->set('gemini_api_key', $data['gemini_api_key']);
        }
        if (filled($data['staff_pin'] ?? null)) {
            $this->settings->set('staff_pin_hash', Hash::make($data['staff_pin']));
            $request->session()->put('staff_ok', true);
        }

        Outlet::query()->updateOrCreate(['id' => Outlet::query()->value('id') ?? 0], [
            'name' => $data['restaurant_name'],
            'address' => $data['address'] ?? null,
            'tax_rate_bp' => (int) round($data['tax_rate'] * 100),
            'service_charge_bp' => (int) round($data['service_charge'] * 100),
        ]);

        return back()->with('success', 'Settings saved.');
    }
}
