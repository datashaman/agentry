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
        Schema::create('agent_conversation_work_item', function (Blueprint $table) {
            $table->string('agent_conversation_id', 36);
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_conversation_id', 'work_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_conversation_work_item');
    }
};
