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
        Schema::create('agent_targets', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name');
            $table->string('target_month'); // YYYY-MM format
            $table->decimal('target_amount', 15, 2);
            $table->unique(['agent_name', 'target_month']); // One target per agent per month
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_targets');
    }
};
