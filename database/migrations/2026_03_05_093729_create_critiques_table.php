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
        Schema::create('critiques', function (Blueprint $table) {
            $table->id();
            $table->morphs('work_item');
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('critic_type');
            $table->unsignedInteger('revision')->default(1);
            $table->json('issues')->nullable();
            $table->json('questions')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('severity')->default('suggestion');
            $table->string('disposition')->default('pending');
            $table->foreignId('supersedes_id')->nullable()->constrained('critiques')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('critiques');
    }
};
