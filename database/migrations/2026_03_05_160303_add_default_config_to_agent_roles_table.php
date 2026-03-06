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
        Schema::table('agent_roles', function (Blueprint $table) {
            $table->string('default_model')->nullable()->after('tools');
            $table->string('default_provider')->nullable()->after('default_model');
            $table->float('default_temperature')->nullable()->after('default_provider');
            $table->unsignedInteger('default_max_steps')->nullable()->after('default_temperature');
            $table->unsignedInteger('default_max_tokens')->nullable()->after('default_max_steps');
            $table->unsignedInteger('default_timeout')->nullable()->after('default_max_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_roles', function (Blueprint $table) {
            $table->dropColumn([
                'default_model',
                'default_provider',
                'default_temperature',
                'default_max_steps',
                'default_max_tokens',
                'default_timeout',
            ]);
        });
    }
};
