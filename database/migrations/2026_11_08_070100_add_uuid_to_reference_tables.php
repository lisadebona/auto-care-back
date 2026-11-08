<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'users',
        'customers',
        'vehicles',
        'brands',
        'categories',
        'products',
        'estimates',
        'canned_jobs',
        'email_messages',
        'custom_workflows',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable()->unique();
            });

            $ids = DB::table($table)->whereNull('uuid')->pluck('id');

            foreach ($ids as $id) {
                DB::table($table)->where('id', $id)->update([
                    'uuid' => (string) Str::uuid(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
