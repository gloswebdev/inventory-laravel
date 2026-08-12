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
        Schema::create('sales_registers', function (Blueprint $table) {
            $table->id();
            $table->date('vouch_date')->nullable();
            $table->string('act_code')->nullable();
            $table->string('act_name')->nullable();
            $table->string('agent_code')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name')->nullable();
            $table->decimal('qty', 15, 4)->default(0);
            $table->decimal('amount', 15, 4)->default(0);
            $table->string('branch')->nullable();
            $table->json('raw_data')->nullable(); // Storing any extra fields dynamically
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_registers');
    }
};
