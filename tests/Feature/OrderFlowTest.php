<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Order;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoRestaurantSeeder::class);
    }

    private function item(string $name): MenuItem
    {
        return MenuItem::where('name', $name)->firstOrFail();
    }

    public function test_menu_is_public(): void
    {
        $this->getJson('/api/v1/menu')->assertOk()
            ->assertJsonPath('outlet.name', 'Kopitiam FeedMe Demo')
            ->assertJsonCount(4, 'categories');
    }

    public function test_qr_order_is_priced_server_side_with_service_charge_sst_and_rounding(): void
    {
        $table = DiningTable::where('code', 'T1')->first();
        $egg = Modifier::where('name', 'Telur mata')->first();

        $res = $this->postJson('/api/v1/orders', [
            'table_token' => $table->qr_token,
            'items' => [
                ['menu_item_id' => $this->item('Nasi Lemak Biasa')->id, 'quantity' => 2, 'modifier_ids' => [$egg->id]],
                ['menu_item_id' => $this->item('Teh Tarik')->id, 'quantity' => 1, 'price_sen' => 1],   // client price ignored
            ],
        ])->assertCreated();

        // subtotal = 2*(450+150) + 280 = 1480; service 10% = 148; SST 6% of 1628 = 97.68 -> 98; raw 1726 -> rounded 1725
        $res->assertJsonPath('data.subtotal_sen', 1480)
            ->assertJsonPath('data.service_charge_sen', 148)
            ->assertJsonPath('data.tax_sen', 98)
            ->assertJsonPath('data.rounding_sen', -1)
            ->assertJsonPath('data.total_sen', 1725)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.channel', 'qr')
            ->assertJsonPath('data.table', 'T1');
    }

    public function test_takeaway_pos_order_has_no_service_charge_and_starts_accepted(): void
    {
        $this->postJson('/api/v1/orders', [
            'type' => 'takeaway',
            'items' => [['menu_item_id' => $this->item('Roti Canai')->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.service_charge_sen', 0)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.channel', 'pos');
    }

    public function test_sold_out_item_is_rejected(): void
    {
        $item = $this->item('Laksa Penang');
        $item->update(['is_available' => false]);

        $this->postJson('/api/v1/orders', ['items' => [['menu_item_id' => $item->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('message', 'Laksa Penang is sold out.');
    }

    public function test_full_lifecycle_kitchen_to_payment_to_completed(): void
    {
        $id = $this->postJson('/api/v1/orders', [
            'table_code' => 'T2',
            'items' => [['menu_item_id' => $this->item('Kopi O')->id, 'quantity' => 1]],
        ])->json('data.id');

        foreach (['preparing', 'ready', 'served'] as $s) {
            $this->postJson("/api/v1/orders/{$id}/status", ['status' => $s])->assertOk()->assertJsonPath('data.status', $s);
        }

        // cannot complete before paying
        $this->postJson("/api/v1/orders/{$id}/status", ['status' => 'completed'])->assertStatus(422);

        $total = Order::find($id)->total_sen;
        $this->postJson("/api/v1/orders/{$id}/pay", ['method' => 'cash', 'tendered_sen' => 1000])
            ->assertOk()
            ->assertJsonPath('change_sen', 1000 - $total)
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.status', 'completed');   // auto-closed because already served
    }

    public function test_illegal_status_jump_is_blocked(): void
    {
        $id = $this->postJson('/api/v1/orders', [
            'items' => [['menu_item_id' => $this->item('Kopi')->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->postJson("/api/v1/orders/{$id}/status", ['status' => 'served'])
            ->assertStatus(422)->assertJsonPath('message', 'Cannot move order from accepted to served.');
    }

    public function test_staff_endpoints_require_token_once_pin_is_set(): void
    {
        $this->post('/setup', [
            'restaurant_name' => 'X', 'tax_rate' => 6, 'service_charge' => 10, 'staff_pin' => '1234',
        ]);
        $this->flushSession();

        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->postJson('/api/v1/orders', ['items' => [['menu_item_id' => 1, 'quantity' => 1]]])->assertUnauthorized();
        $this->get('/pos')->assertRedirect('/login');

        $hash = app(\App\Services\Settings\SettingsStore::class)->staffPinHash();
        $this->getJson('/api/v1/orders', ['X-Staff-Token' => \App\Http\Middleware\StaffAuth::tokenFor($hash)])->assertOk();

        // QR ordering stays public
        $this->postJson('/api/v1/orders', [
            'table_token' => DiningTable::first()->qr_token,
            'items' => [['menu_item_id' => $this->item('Kopi')->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    public function test_gemini_key_is_encrypted_at_rest(): void
    {
        $this->post('/setup', [
            'restaurant_name' => 'X', 'tax_rate' => 6, 'service_charge' => 10, 'gemini_api_key' => 'secret-key-123',
        ]);
        $raw = \App\Models\Setting::find('gemini_api_key')->value;
        $this->assertNotSame('secret-key-123', $raw);
        $this->assertSame('secret-key-123', app(\App\Services\Settings\SettingsStore::class)->geminiKey());
    }

    public function test_daily_report(): void
    {
        $this->postJson('/api/v1/orders', ['items' => [['menu_item_id' => $this->item('Teh Tarik')->id, 'quantity' => 3]]]);
        $this->getJson('/api/v1/reports/daily')->assertOk()
            ->assertJsonPath('orders', 1)
            ->assertJsonPath('top_items.0.name', 'Teh Tarik');
        $this->getJson('/api/v1/ai/insights')->assertOk()->assertJsonPath('source', 'rules');
    }
}
