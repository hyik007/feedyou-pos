<?php

namespace Tests\Feature;

use App\Services\AI\Contracts\LlmClient;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiOrderParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoRestaurantSeeder::class);
    }

    public function test_rule_parser_understands_manglish_without_api_key(): void
    {
        $res = $this->postJson('/api/v1/ai/parse-order', [
            'text' => '2 kopi o kurang manis, satu nasi lemak tambah telur, teh tarik x3 and ckt, pizza',
        ])->assertOk()->assertJsonPath('source', 'rules');

        $items = collect($res->json('items'))->keyBy('name');
        $this->assertSame(2, $items['Kopi O']['quantity']);
        $this->assertSame('kurang manis', $items['Kopi O']['notes']);
        $this->assertSame(1, $items['Nasi Lemak Biasa']['quantity']);
        $this->assertSame(3, $items['Teh Tarik']['quantity']);
        $this->assertArrayHasKey('Char Kuey Teow', $items->all());
        $this->assertSame(['pizza'], $res->json('unmatched'));
    }

    public function test_llm_output_is_validated_against_menu(): void
    {
        $this->app->bind(LlmClient::class, fn () => new class implements LlmClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function complete(string $prompt, bool $json = false): ?string
            {
                return json_encode(['items' => [
                    ['menu_item_id' => 1, 'quantity' => 2, 'notes' => 'ais'],
                    ['menu_item_id' => 9999, 'quantity' => 1],          // hallucinated -> dropped
                ], 'unmatched' => ['burger']]);
            }
        });

        $this->postJson('/api/v1/ai/parse-order', ['text' => 'whatever'])
            ->assertOk()
            ->assertJsonPath('source', 'fake')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('unmatched.0', 'burger');
    }

    public function test_falls_back_to_rules_when_llm_fails(): void
    {
        $this->app->bind(LlmClient::class, fn () => new class implements LlmClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function complete(string $prompt, bool $json = false): ?string
            {
                return null;
            }
        });

        $this->postJson('/api/v1/ai/parse-order', ['text' => 'roti canai'])
            ->assertOk()->assertJsonPath('source', 'rules')->assertJsonPath('items.0.name', 'Roti Canai');
    }
}
