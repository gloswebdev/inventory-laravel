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
        Schema::create('indent_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indent_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('demand_qty', 15, 2);
            $table->string('demand_unit'); // box or kg
            $table->decimal('stock_box', 15, 2)->default(0);
            $table->decimal('stock_kg', 15, 2)->default(0);
            $table->decimal('final_qty_box', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indent_items');
    }
};
