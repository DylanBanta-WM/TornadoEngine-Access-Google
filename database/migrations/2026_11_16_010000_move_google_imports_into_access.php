<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Access now keeps every import's roles and one exclusions list (Access 0.5).
    public function up(): void
    {
        if (Schema::hasTable('access_google_grants')) {
            foreach (DB::table('access_google_grants')->get() as $row) {
                DB::table('access_imported_roles')->insertOrIgnore(['role_id' => $row->role_id, 'user_id' => $row->user_id, 'source' => 'google']);
            }
            Schema::drop('access_google_grants');
        }

        if (Schema::hasTable('access_google_exclusions')) {
            foreach (DB::table('access_google_exclusions')->get() as $row) {
                DB::table('access_exclusions')->insertOrIgnore((array) $row + []);
            }
            Schema::drop('access_google_exclusions');
        }
    }

    public function down(): void
    {
        // The old tables aren't brought back; Access keeps the data.
    }
};
