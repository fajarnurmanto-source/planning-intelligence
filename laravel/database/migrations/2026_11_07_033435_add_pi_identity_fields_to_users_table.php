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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('id');
            $table->string('tenant_id', 64)->nullable()->index()->after('email');
            $table->string('role', 32)->default('viewer')->after('tenant_id');
            $table->string('legacy_password_salt', 64)->nullable();
            $table->string('legacy_password_digest', 128)->nullable();
            $table->unsignedInteger('legacy_password_iterations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn([
                'username',
                'tenant_id',
                'role',
                'legacy_password_salt',
                'legacy_password_digest',
                'legacy_password_iterations',
            ]);
        });
    }
};
