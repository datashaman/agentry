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
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedBigInteger('github_installation_id')->nullable()->unique()->after('slug');
            $table->string('github_account_login')->nullable()->after('github_installation_id');
            $table->string('github_account_type')->nullable()->after('github_account_login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['github_installation_id', 'github_account_login', 'github_account_type']);
        });
    }
};
