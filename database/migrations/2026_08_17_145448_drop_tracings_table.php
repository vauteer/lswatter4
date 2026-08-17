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
        Schema::dropIfExists('tracings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('tracings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('at');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('action_type');
            $table->unsignedInteger('table_type')->nullable();
            $table->unsignedInteger('row_id')->nullable();
            $table->string('old_values', 1000)->nullable();
            $table->timestamps();
        });
    }
};
