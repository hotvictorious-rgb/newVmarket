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
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->boolean('has_draft')->default(false)->after('is_published');
            $table->string('draft_title', 255)->nullable()->after('has_draft');
            $table->string('draft_version', 32)->nullable()->after('draft_title');
            $table->longText('draft_content')->nullable()->after('draft_version');
            $table->text('draft_summary')->nullable()->after('draft_content');
            $table->text('draft_summary_of_changes')->nullable()->after('draft_summary');
            $table->date('draft_effective_date')->nullable()->after('draft_summary_of_changes');
            $table->timestamp('draft_updated_at')->nullable()->after('draft_effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropColumn([
                'has_draft',
                'draft_title',
                'draft_version',
                'draft_content',
                'draft_summary',
                'draft_summary_of_changes',
                'draft_effective_date',
                'draft_updated_at',
            ]);
        });
    }
};
