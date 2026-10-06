<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('estimates')
            ->where('order_status', 'invoice')
            ->update(['workflow' => 'invoices']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('estimates')
            ->where('order_status', 'invoice')
            ->where('workflow', 'invoices')
            ->update(['workflow' => 'estimates']);
    }
};
