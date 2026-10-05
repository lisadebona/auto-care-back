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
        Schema::table('canned_job_line_items', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->nullable()->after('quantity');
            $table->json('remarks')->nullable()->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canned_job_line_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'remarks']);
        });
    }
};
