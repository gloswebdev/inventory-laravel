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
        Schema::table('products', function (Blueprint $table) {
            $table->string('item_code')->nullable()->after('id');
            $table->string('category')->nullable()->after('group_id'); // Corresponds to CATEGORY column
            $table->string('form')->nullable()->after('category');
            $table->string('technical_name')->nullable()->after('form');
            $table->string('rm_type')->nullable()->after('technical_name');
            $table->string('pack_name')->nullable()->after('product_type_id'); // Or after rm_type?
            $table->string('unit_box')->nullable()->after('pack_name');
            $table->string('weight_unit')->nullable()->after('unit_box');
            $table->string('weight_in')->nullable()->after('weight_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
