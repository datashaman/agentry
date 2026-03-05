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
        Schema::create('repos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('primary_language')->nullable();
            $table->string('default_branch')->default('main');
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('ops_request_repo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repo_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ops_request_id', 'repo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_request_repo');
        Schema::dropIfExists('repos');
    }
};
