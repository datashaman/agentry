<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_types', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('description');
            $table->json('tools')->nullable()->after('instructions');
        });

        foreach (DB::table('agent_types')->whereNotNull('default_capabilities')->get() as $row) {
            $capabilities = json_decode($row->default_capabilities, true);
            if (is_array($capabilities)) {
                DB::table('agent_types')
                    ->where('id', $row->id)
                    ->update(['tools' => json_encode($capabilities)]);
            }
        }

        Schema::table('agent_types', function (Blueprint $table) {
            $table->dropColumn('default_capabilities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_types', function (Blueprint $table) {
            $table->json('default_capabilities')->nullable()->after('description');
        });

        foreach (DB::table('agent_types')->whereNotNull('tools')->get() as $row) {
            $tools = json_decode($row->tools, true);
            if (is_array($tools)) {
                DB::table('agent_types')
                    ->where('id', $row->id)
                    ->update(['default_capabilities' => json_encode($tools)]);
            }
        }

        Schema::table('agent_types', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'tools']);
        });
    }
};
