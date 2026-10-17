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
        // 1. Multi-device FCM Device Token Registry
        if (!Schema::hasTable('user_device_tokens')) {
            Schema::create('user_device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('device_token', 512)->index();
                $table->enum('device_type', ['android', 'ios', 'web'])->default('android');
                $table->enum('app_type', ['rider', 'vendor', 'customer', 'admin'])->default('rider');
                $table->string('device_name')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'device_token'], 'user_device_token_unique');
            });
        }

        // 2. Real-time In-App Notifications (Bell Drawer)
        if (!Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('body');
                $table->string('type', 64)->index();
                $table->json('data')->nullable();
                $table->string('action_url')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // 3. Notification Delivery Audit Log (Super Admin Visibility)
        if (!Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 32)->index(); // 'fcm', 'sms', 'in_app', 'whatsapp'
                $table->string('recipient', 128)->index();
                $table->string('event_type', 64)->index();
                $table->string('title')->nullable();
                $table->string('status', 32)->index(); // 'delivered', 'failed', 'skipped'
                $table->text('gateway_response')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('user_device_tokens');
    }
};
