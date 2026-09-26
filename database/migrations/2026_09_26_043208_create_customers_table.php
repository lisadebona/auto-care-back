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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('home_address')->nullable();
            $table->string('home_city')->nullable();
            $table->string('home_state')->nullable();
            $table->string('home_zip_code', 20)->nullable();
            $table->string('home_country')->nullable()->default('United States');
            $table->string('office_address')->nullable();
            $table->string('office_city')->nullable();
            $table->string('office_state')->nullable();
            $table->string('office_zip_code', 20)->nullable();
            $table->string('office_country')->nullable()->default('United States');
            $table->string('phone', 30);
            $table->string('email')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
