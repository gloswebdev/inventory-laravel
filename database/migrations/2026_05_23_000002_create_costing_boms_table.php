<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('yield_quantity', 10, 3);
            $table->string('yield_uom');
            $table->timestamps();
        });

        Schema::create('costing_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_bom_id')->constrained('costing_boms')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('products');
            $table->decimal('quantity', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_bom_items');
        Schema::dropIfExists('costing_boms');
    }
};
