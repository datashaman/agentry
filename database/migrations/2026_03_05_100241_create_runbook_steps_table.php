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
        Schema::create('runbook_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runbook_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('instruction');
            $table->string('status')->default('pending');
            $table->string('executed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runbook_steps');
    }
};
