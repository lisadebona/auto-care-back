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
        Schema::dropIfExists('customer_vehicle');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('mileage')->nullable()->after('type');
            $table->string('vin', 32)->nullable()->after('mileage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['mileage', 'vin']);
        });

        Schema::create('customer_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'vehicle_id']);
        });
    }
};
