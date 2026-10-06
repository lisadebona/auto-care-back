<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('value')->unique();
            $table->timestamps();

            $table->unique('name');
        });

        $permissions = [
            'workflows.view',
            'workflows.create',
            'workflows.edit',
            'workflows.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::query()->where('name', 'super-admin')->first()?->givePermissionTo($permissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_workflows');

        Permission::query()
            ->whereIn('name', [
                'workflows.view',
                'workflows.create',
                'workflows.edit',
                'workflows.delete',
            ])
            ->delete();
    }
};
