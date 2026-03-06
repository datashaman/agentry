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
        Schema::table('skills', function (Blueprint $table) {
            $table->foreignId('source_repo_id')->nullable()->constrained('repos')->nullOnDelete();
            $table->string('source_path')->nullable();
            $table->string('source_sha')->nullable();
            $table->json('frontmatter_metadata')->nullable();
            $table->json('resource_paths')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_repo_id');
            $table->dropColumn(['source_path', 'source_sha', 'frontmatter_metadata', 'resource_paths']);
        });
    }
};
