<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('labelables');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('milestones');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('milestones', function ($table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('labels', function ($table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('#6b7280');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('labelables', function ($table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->morphs('labelable');
            $table->timestamps();
        });
    }
};
