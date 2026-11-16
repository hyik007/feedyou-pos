<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core POS schema.
 *
 * All money is stored as integer sen (1 MYR = 100 sen) to avoid float rounding.
 * Every business table hangs off an outlet so the system can grow to multi-outlet
 * without a schema rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->unsignedSmallInteger('tax_rate_bp')->default(600);            // basis points: 600 = 6.00% SST
            $table->unsignedSmallInteger('service_charge_bp')->default(1000);     // 1000 = 10%
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_sen');
            $table->string('image_url')->nullable();
            $table->json('aliases')->nullable();   // e.g. ["kopi-o","black coffee"] – helps the AI/rule parser
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['outlet_id', 'is_available']);
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('price_delta_sen')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_modifier_group', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['menu_item_id', 'modifier_group_id']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('code');                       // "T1"
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('qr_token', 40)->unique();     // used in /t/{token} QR links
            $table->timestamps();
            $table->unique(['outlet_id', 'code']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_no')->unique();         // human friendly, e.g. 20261116-0007
            $table->string('channel');                    // OrderChannel
            $table->string('type');                       // OrderType
            $table->string('status');                     // OrderStatus
            $table->string('payment_status')->default('unpaid');
            $table->unsignedInteger('subtotal_sen')->default(0);
            $table->unsignedInteger('service_charge_sen')->default(0);
            $table->unsignedInteger('tax_sen')->default(0);
            $table->integer('rounding_sen')->default(0);           // MY 5-sen rounding, can be negative
            $table->unsignedInteger('total_sen')->default(0);
            $table->unsignedInteger('paid_sen')->default(0);
            $table->text('customer_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'status']);
            $table->index(['outlet_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                       // snapshot – menu can change later
            $table->unsignedInteger('unit_price_sen');    // base + modifiers
            $table->unsignedSmallInteger('quantity');
            $table->json('modifiers')->nullable();        // snapshot [{id,name,price_delta_sen}]
            $table->string('notes')->nullable();
            $table->unsignedInteger('line_total_sen');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method');                     // PaymentMethod
            $table->unsignedInteger('amount_sen');
            $table->unsignedInteger('tendered_sen')->nullable();
            $table->unsignedInteger('change_sen')->default(0);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'order_items', 'orders', 'dining_tables', 'menu_item_modifier_group',
            'modifiers', 'modifier_groups', 'menu_items', 'categories', 'outlets', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
