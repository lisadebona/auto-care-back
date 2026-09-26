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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('make');
            $table->string('model');
            $table->string('sub_model')->nullable();
            $table->string('transmission')->nullable();
            $table->string('engine_size')->nullable();
            $table->string('drivetrain')->nullable();
            $table->string('type');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
