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
        $estimates = DB::table('estimates')
            ->whereNull('po_number')
            ->orderBy('id')
            ->get(['id', 'number']);

        foreach ($estimates as $estimate) {
            $uniqueNumber = (int) $estimate->number;
            $poNumber = 'PO'.$estimate->id.$uniqueNumber;

            while (DB::table('estimates')->where('po_number', $poNumber)->exists()) {
                $uniqueNumber++;
                $poNumber = 'PO'.$estimate->id.$uniqueNumber;
            }

            DB::table('estimates')->where('id', $estimate->id)->update([
                'po_number' => $poNumber,
            ]);
        }

        Schema::table('estimates', function (Blueprint $table) {
            $table->unique('po_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique(['po_number']);
        });
    }
};
