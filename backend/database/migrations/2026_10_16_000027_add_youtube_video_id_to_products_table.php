<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'youtube_video_id')) {
                $table->string('youtube_video_id', 11)->nullable()->after('thumbnail');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'youtube_video_id')) {
                $table->dropColumn('youtube_video_id');
            }
        });
    }
};