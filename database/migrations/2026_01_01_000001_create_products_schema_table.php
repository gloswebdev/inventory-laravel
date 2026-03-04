<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name');
            $table->timestamps();
        });

        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias_name')->nullable();
            $table->string('uom');
            $table->decimal('price', 10, 2)->default(0);
            $table->foreignId('group_id')->nullable()->constrained('product_groups')->nullOnDelete();
            $table->foreignId('product_type_id')->constrained('product_types'); // Required
            $table->decimal('low_alert_quantity', 10, 3)->default(0);
            $table->decimal('current_stock', 15, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_groups');
    }
};
