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
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title', 255);
            $table->string('category', 64)->default('general');
            $table->string('current_version', 32)->default('1.0.0');
            $table->date('effective_date');
            $table->boolean('is_published')->default(true);
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->onDelete('cascade');
            $table->string('version', 32);
            $table->string('title', 255);
            $table->longText('content');
            $table->text('summary_of_changes')->nullable();
            $table->date('effective_date');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['legal_document_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
