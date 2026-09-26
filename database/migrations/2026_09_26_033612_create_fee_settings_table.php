<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_supplies_cap')->default('none');
            $table->decimal('shop_supplies_cap_amount', 10, 2)->nullable();
            $table->decimal('shop_supplies_fee', 10, 3)->default(0);
            $table->string('shop_supplies_fee_type')->default('percent');
            $table->boolean('shop_supplies_on_parts')->default(false);
            $table->boolean('shop_supplies_on_labor')->default(false);
            $table->decimal('epa_rate', 6, 3)->default(0);
            $table->boolean('epa_on_parts')->default(false);
            $table->boolean('epa_on_labor')->default(false);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->boolean('tax_on_parts')->default(false);
            $table->boolean('tax_on_labor')->default(false);
            $table->boolean('tax_on_epa')->default(false);
            $table->boolean('tax_on_shop_supplies')->default(false);
            $table->boolean('tax_on_subcontract')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_settings');
    }
};
