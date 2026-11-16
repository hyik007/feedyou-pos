<?php

namespace Tests\Feature;

use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_render(): void
    {
        $this->seed(DemoRestaurantSeeder::class);
        $token = \App\Models\DiningTable::first()->qr_token;

        foreach (['/', '/pos', '/kitchen', '/admin', '/setup', '/login', "/t/{$token}"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/t/not-a-real-token')->assertNotFound();
    }
}
