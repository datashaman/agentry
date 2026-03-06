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
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_key');
            $table->string('title');
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('priority')->nullable();
            $table->string('assignee')->nullable();
            $table->string('url');
            $table->timestamps();

            $table->unique(['project_id', 'provider', 'provider_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
