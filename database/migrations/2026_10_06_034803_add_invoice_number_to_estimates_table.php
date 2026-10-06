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
        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedInteger('invoice_sequence')->nullable()->unique()->after('number');
            $table->string('invoice_number')->nullable()->unique()->after('invoice_sequence');
        });

        $sequence = 1;

        foreach (DB::table('estimates')->where('order_status', 'invoice')->orderBy('id')->pluck('id') as $recordId) {
            DB::table('estimates')->where('id', $recordId)->update([
                'invoice_sequence' => $sequence,
                'invoice_number' => $recordId.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            ]);
            $sequence++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique(['invoice_sequence']);
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_sequence', 'invoice_number']);
        });
    }
};
