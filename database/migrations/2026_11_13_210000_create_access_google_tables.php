<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each rule gives one role to a Google user, a group's members, or an OU's people.
        Schema::create('access_google_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('value');
            $table->boolean('sub_ous')->default(true);
            $table->unsignedInteger('matched')->nullable();
            $table->timestamps();
        });

        // The roles the sync gave, so it only ever takes back its own (not roles given by hand).
        Schema::create('access_google_grants', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_google_grants');
        Schema::dropIfExists('access_google_rules');
    }
};
