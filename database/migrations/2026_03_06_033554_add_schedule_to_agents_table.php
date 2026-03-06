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
        Schema::table('agents', function (Blueprint $table) {
            $table->string('schedule')->nullable()->after('status');
            $table->text('scheduled_instructions')->nullable()->after('schedule');
            $table->timestamp('last_scheduled_at')->nullable()->after('scheduled_instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'scheduled_instructions', 'last_scheduled_at']);
        });
    }
};
