<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                $table->string('status', 32)->default('completed')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                $table->string('status', 32)->default('completed')->change();
            });
        }
    }
};
