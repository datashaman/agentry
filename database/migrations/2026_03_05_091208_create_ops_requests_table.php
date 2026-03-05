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
        Schema::create('ops_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('new');
            $table->string('category');
            $table->string('execution_type');
            $table->string('risk_level');
            $table->string('environment')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ops_request_story', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ops_request_id', 'story_id']);
        });

        Schema::create('bug_ops_request', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bug_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ops_request_id', 'bug_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_ops_request');
        Schema::dropIfExists('ops_request_story');
        Schema::dropIfExists('ops_requests');
    }
};
