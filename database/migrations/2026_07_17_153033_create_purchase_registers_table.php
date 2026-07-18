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
        Schema::create('purchase_registers', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('item_name')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('vouch_no')->nullable();
            $table->date('vouch_date')->nullable();
            $table->decimal('qty', 15, 4)->default(0);
            $table->decimal('case_rate', 15, 4)->default(0);
            $table->decimal('purity', 8, 2)->nullable();
            $table->string('group_name4')->nullable();
            $table->string('group_name5')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_registers');
    }
};
