<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\StaffAuth;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Services\Settings\SettingsStore;
use Illuminate\View\View;

/** Serves the four front ends. Each is a Blade shell + vanilla JS talking to /api/v1. */
class PageController extends Controller
{
    public function __construct(private SettingsStore $settings) {}

    public function home(): View
    {
        return view('home', ['name' => $this->settings->restaurantName(), 'ready' => Outlet::query()->exists()]);
    }

    public function pos(): View
    {
        return view('staff.pos', $this->staffData());
    }

    public function kitchen(): View
    {
        return view('staff.kitchen', $this->staffData());
    }

    public function admin(): View
    {
        return view('staff.admin', $this->staffData() + [
            'tables' => DiningTable::where('outlet_id', Outlet::current()->id)->orderBy('id')->get(),
        ]);
    }

    /** Customer QR ordering page for one table. */
    public function table(string $token): View
    {
        $table = DiningTable::where('qr_token', $token)->firstOrFail();

        return view('customer.order', [
            'name' => $this->settings->restaurantName(),
            'table' => $table,
        ]);
    }

    private function staffData(): array
    {
        $hash = $this->settings->staffPinHash();

        return [
            'name' => $this->settings->restaurantName(),
            'staffToken' => $hash ? StaffAuth::tokenFor($hash) : '',
        ];
    }
}
