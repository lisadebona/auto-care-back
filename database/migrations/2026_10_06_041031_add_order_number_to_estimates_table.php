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
            $table->unsignedInteger('order_sequence')->nullable()->unique()->after('invoice_number');
            $table->string('order_number')->nullable()->unique()->after('order_sequence');
        });

        $sequence = 1;

        foreach (DB::table('estimates')->where('workflow', 'in_progress')->orderBy('id')->pluck('id') as $recordId) {
            DB::table('estimates')->where('id', $recordId)->update([
                'order_sequence' => $sequence,
                'order_number' => $recordId.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
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
            $table->dropUnique(['order_sequence']);
            $table->dropUnique(['order_number']);
            $table->dropColumn(['order_sequence', 'order_number']);
        });
    }
};
