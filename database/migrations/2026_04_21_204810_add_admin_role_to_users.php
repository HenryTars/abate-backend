<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite does not support modifying enum columns.
    // Strategy: add a plain string column, copy data, drop old column, rename.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_tmp')->default('user')->after('role');
        });

        DB::statement('UPDATE users SET role_tmp = role');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_tmp', 'role');
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role = 'user' WHERE role = 'admin'");
    }
};