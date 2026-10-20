<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable();
            $table->string('idempotency_request_hash', 64)->nullable();
            $table->unique(['user_id', 'idempotency_key'], 'checkout_intents_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->dropUnique('checkout_intents_user_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_request_hash']);
        });
    }
};
