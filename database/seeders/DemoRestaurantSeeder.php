<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/** A ready-to-demo Malaysian kopitiam: menu, modifiers and 8 QR tables. */
class DemoRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::create([
            'name' => 'FeedYou Kopitiam',
            'address' => 'Kuala Lumpur',
            'tax_rate_bp' => 600,
            'service_charge_bp' => 1000,
        ]);
        Setting::updateOrCreate(['key' => 'restaurant_name'], ['value' => $outlet->name]);

        // Modifier groups
        $temp = $this->group($outlet, 'Temperature', 0, 1, [['Hot', 0], ['Iced', 50]]);
        $sugar = $this->group($outlet, 'Sweetness', 0, 1, [['Kurang manis', 0], ['Kosong (no sugar)', 0], ['Extra manis', 0]]);
        $addons = $this->group($outlet, 'Add-ons', 0, 3, [['Telur mata', 150], ['Ayam goreng', 500], ['Extra sambal', 100]]);
        $spice = $this->group($outlet, 'Spice level', 0, 1, [['Not spicy', 0], ['Pedas', 0], ['Extra pedas', 0]]);

        $menu = [
            'Drinks' => [
                ['Kopi O', 220, ['kopi-o', 'black coffee', 'kopi o'], [$temp, $sugar]],
                ['Kopi', 250, ['white coffee', 'kopi susu'], [$temp, $sugar]],
                ['Teh Tarik', 280, ['teh', 'pulled tea', 'tea'], [$temp, $sugar]],
                ['Milo Dinosaur', 650, ['milo dino', 'dinosaur'], []],
                ['Limau Ais', 300, ['lime juice', 'ice lime', 'limau'], [$sugar]],
                ['Sirap Bandung', 350, ['bandung', 'rose milk'], [$temp]],
            ],
            'Rice' => [
                ['Nasi Lemak Biasa', 450, ['nasi lemak', 'nlemak'], [$addons, $spice]],
                ['Nasi Lemak Ayam Goreng', 1100, ['nasi lemak ayam', 'nl ayam'], [$addons, $spice]],
                ['Nasi Goreng Kampung', 950, ['nasi goreng', 'fried rice', 'ng kampung'], [$addons, $spice]],
                ['Nasi Ayam Hainan', 1050, ['chicken rice', 'nasi ayam'], [$spice]],
            ],
            'Noodles' => [
                ['Mee Goreng Mamak', 900, ['mee goreng', 'fried noodles', 'mamak mee'], [$addons, $spice]],
                ['Char Kuey Teow', 1100, ['ckt', 'kuey teow', 'char kway teow'], [$addons, $spice]],
                ['Laksa Penang', 1000, ['laksa', 'asam laksa'], []],
            ],
            'Snacks' => [
                ['Roti Canai', 180, ['roti kosong', 'roti', 'canai'], []],
                ['Roti Telur', 300, ['egg roti'], []],
                ['Kaya Butter Toast', 450, ['roti bakar', 'kaya toast', 'toast'], []],
                ['Half-boiled Eggs (2)', 400, ['telur separuh masak', 'half boiled', 'soft boiled eggs'], []],
                ['Curry Puff', 250, ['karipap', 'epok-epok'], []],
            ],
        ];

        $sort = 0;
        foreach ($menu as $catName => $items) {
            $cat = $outlet->categories()->create(['name' => $catName, 'sort' => $sort++]);
            foreach ($items as $i => [$name, $price, $aliases, $groups]) {
                $item = $outlet->menuItems()->create([
                    'category_id' => $cat->id,
                    'name' => $name,
                    'price_sen' => $price,
                    'aliases' => $aliases,
                    'sort' => $i,
                ]);
                $item->modifierGroups()->attach(array_map(fn ($g) => $g->id, $groups));
            }
        }

        foreach (range(1, 8) as $n) {
            DiningTable::create(['outlet_id' => $outlet->id, 'code' => "T{$n}", 'seats' => $n <= 4 ? 2 : 4]);
        }
    }

    private function group(Outlet $outlet, string $name, int $min, int $max, array $mods): ModifierGroup
    {
        $g = ModifierGroup::create(['outlet_id' => $outlet->id, 'name' => $name, 'min_select' => $min, 'max_select' => $max]);
        foreach ($mods as [$n, $delta]) {
            $g->modifiers()->create(['name' => $n, 'price_delta_sen' => $delta]);
        }

        return $g;
    }
}
