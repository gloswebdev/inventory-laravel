<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_bom_packing_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_bom_id')->constrained('costing_boms')->onDelete('cascade');
            $table->foreignId('pricelist_id')->constrained('pricelists')->onDelete('cascade');
            $table->foreignId('raw_material_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('costing_bom_packing_materials');
    }
};
