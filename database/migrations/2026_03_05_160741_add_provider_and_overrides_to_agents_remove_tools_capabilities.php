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
            $table->string('provider')->default('anthropic')->after('model');
            $table->float('temperature')->nullable()->after('provider');
            $table->unsignedInteger('max_steps')->nullable()->after('temperature');
            $table->unsignedInteger('max_tokens')->nullable()->after('max_steps');
            $table->unsignedInteger('timeout')->nullable()->after('max_tokens');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['tools', 'capabilities']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->json('tools')->nullable()->after('confidence_threshold');
            $table->json('capabilities')->nullable()->after('tools');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['provider', 'temperature', 'max_steps', 'max_tokens', 'timeout']);
        });
    }
};
