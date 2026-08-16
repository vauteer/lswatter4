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
        Schema::table('player_tournament', function (Blueprint $table) {
            $table->unique(['player_id', 'tournament_id']);
        });

        Schema::table('team_tournament', function (Blueprint $table) {
            $table->unique(['team_id', 'tournament_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_tournament', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'tournament_id']);
        });

        Schema::table('team_tournament', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'tournament_id']);
        });
    }
};
