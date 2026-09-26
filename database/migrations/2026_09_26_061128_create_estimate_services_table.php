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
        Schema::create('estimate_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('authorized')->default(false);
            $table->decimal('discount_percent', 8, 3)->default(0);
            $table->decimal('epa_percent', 8, 3)->default(0);
            $table->decimal('shop_supplies_percent', 8, 3)->default(0);
            $table->decimal('tax_percent', 8, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_services');
    }
};
