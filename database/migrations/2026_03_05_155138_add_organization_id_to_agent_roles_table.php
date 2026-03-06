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
        $orgId = DB::table('organizations')->value('id');
        if ($orgId === null) {
            $orgId = DB::table('organizations')->insertGetId([
                'name' => 'Default',
                'slug' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('agent_roles', function (Blueprint $table) use ($orgId) {
            $table->unsignedBigInteger('organization_id')->default($orgId)->after('id')->nullable(false);
        });

        DB::table('agent_roles')->whereNull('organization_id')->update(['organization_id' => $orgId]);

        Schema::table('agent_roles', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::table('agent_roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['organization_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_roles', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'slug']);
            $table->unique('slug');
            $table->dropForeign(['organization_id']);
        });

        Schema::table('agent_roles', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};
