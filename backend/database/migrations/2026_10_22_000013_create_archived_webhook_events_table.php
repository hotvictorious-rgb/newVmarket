<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('archived_webhook_events')) {
            Schema::create('archived_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id')->nullable()->index();
                $table->string('event_id', 128)->index();
                $table->string('event_type', 64)->index();
                $table->string('payload_hash', 64)->nullable();
                $table->string('status', 32)->default('completed')->index();
                $table->text('error_reason')->nullable();
                $table->unsignedInteger('attempts')->default(1);
                $table->longText('payload')->nullable();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamp('original_created_at')->nullable();
                $table->timestamp('original_updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent()->index();

                $table->index(['event_type', 'processed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_webhook_events');
    }
};
