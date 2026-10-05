<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->json('remarks')->nullable()->after('status');
        });

        DB::table('estimate_line_items')
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $item): void {
                DB::table('estimate_line_items')
                    ->where('id', $item->id)
                    ->update(['remarks' => json_encode([$item->status])]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
