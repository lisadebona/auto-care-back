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
        Schema::create('estimate_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_service_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('discount', 12, 2)->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_line_items');
    }
};
