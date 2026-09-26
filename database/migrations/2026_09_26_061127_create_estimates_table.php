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
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_writer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('customer_comments')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('po_number')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('payment_terms')->default('On Receipt');
            $table->string('order_status')->default('estimate');
            $table->string('workflow')->default('estimates');
            $table->timestamp('authorized_at')->nullable();
            $table->json('labels')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
