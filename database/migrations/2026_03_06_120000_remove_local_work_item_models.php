<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $removedClasses = [
            'App\\Models\\Story',
            'App\\Models\\Bug',
            'App\\Models\\Epic',
            'App\\Models\\Task',
            'App\\Models\\Subtask',
        ];

        foreach (['critiques', 'hitl_escalations', 'action_logs', 'attachments'] as $table) {
            DB::table($table)->whereIn('work_item_type', $removedClasses)->delete();
        }

        DB::table('dependencies')->whereIn('blocker_type', $removedClasses)->orWhereIn('blocked_type', $removedClasses)->delete();

        Schema::dropIfExists('subtasks');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('ops_request_story');
        Schema::dropIfExists('bug_ops_request');
        Schema::dropIfExists('bugs');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('epics');
        Schema::dropIfExists('labelables');
        Schema::dropIfExists('dependencies');

        Schema::table('projects', function (Blueprint $table) {
            $table->string('work_item_provider')->nullable()->after('instructions');
            $table->json('work_item_provider_config')->nullable()->after('work_item_provider');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('jira_account_id')->nullable()->after('github_nickname');
            $table->text('jira_token')->nullable()->after('jira_account_id');
            $table->text('jira_refresh_token')->nullable()->after('jira_token');
            $table->string('jira_cloud_id')->nullable()->after('jira_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['jira_account_id', 'jira_token', 'jira_refresh_token', 'jira_cloud_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['work_item_provider', 'work_item_provider_config']);
        });

        Schema::create('dependencies', function (Blueprint $table) {
            $table->id();
            $table->morphs('blocker');
            $table->morphs('blocked');
            $table->timestamps();
        });

        Schema::create('labelables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->morphs('labelable');
            $table->timestamps();
        });

        Schema::create('epics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->string('status')->default('backlog');
            $table->unsignedInteger('story_points')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->timestamps();
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });

        Schema::create('bugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('steps_to_reproduce')->nullable();
            $table->string('status')->default('new');
            $table->string('severity');
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
};
