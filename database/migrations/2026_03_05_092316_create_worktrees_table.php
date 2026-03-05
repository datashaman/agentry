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
        Schema::create('worktrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('work_item');
            $table->string('path');
            $table->string('status')->default('active');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('interrupted_at')->nullable();
            $table->string('interrupted_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worktrees');
    }
};
