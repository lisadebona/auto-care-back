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
        if (! Schema::hasColumn('email_messages', 'mailtrap_id')) {
            return;
        }

        Schema::table('email_messages', function (Blueprint $table) {
            $table->renameColumn('mailtrap_id', 'mailgun_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('email_messages', 'mailgun_id') || Schema::hasColumn('email_messages', 'mailtrap_id')) {
            return;
        }

        Schema::table('email_messages', function (Blueprint $table) {
            $table->renameColumn('mailgun_id', 'mailtrap_id');
        });
    }
};
