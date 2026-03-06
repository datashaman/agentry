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
        Schema::create('event_responders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_role_id')->constrained()->cascadeOnDelete();
            $table->string('work_item_type');
            $table->string('status');
            $table->text('instructions');
            $table->timestamps();

            $table->unique(['agent_role_id', 'work_item_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_responders');
    }
};
