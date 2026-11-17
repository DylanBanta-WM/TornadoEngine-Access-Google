<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One rule per role can be primary: the one that should cover everyone with the role.
    public function up(): void
    {
        Schema::table('access_google_rules', function (Blueprint $table) {
            $table->boolean('primary')->default(false)->after('sub_ous');
        });
    }

    public function down(): void
    {
        Schema::table('access_google_rules', function (Blueprint $table) {
            $table->dropColumn('primary');
        });
    }
};
