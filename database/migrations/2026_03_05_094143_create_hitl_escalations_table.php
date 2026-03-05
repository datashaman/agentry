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
        Schema::create('hitl_escalations', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('work_item');
            $table->foreignId('raised_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('trigger_type');
            $table->string('trigger_class')->nullable();
            $table->float('agent_confidence')->nullable();
            $table->text('reason');
            $table->text('resolution')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hitl_escalations');
    }
};
