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
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('team1_id')->constrained('teams')->onDelete('restrict');
            $table->foreignId('team2_id')->constrained('teams')->onDelete('restrict');
            $table->unsignedInteger('round');
            $table->unsignedInteger('table_number'); // not `table` ! Reserved by Laravel for the table name
            $table->string('score')->nullable();
            $table->unsignedInteger('team1_won')->default(0);
            $table->unsignedInteger('team2_won')->default(0);
            $table->unsignedInteger('team1_points')->default(0);
            $table->unsignedInteger('team2_points')->default(0);

            $table->timestamps();

            $table->index(['tournament_id', 'team1_id', 'team2_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
