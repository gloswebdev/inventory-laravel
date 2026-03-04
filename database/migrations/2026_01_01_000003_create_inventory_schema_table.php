<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')->constrained('products');
            $table->decimal('quantity_produced', 15, 3);
            $table->timestamps(); // created_at will be production_date
        });

        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('adjustment_type', ['add', 'deduct']);
            $table->decimal('quantity', 15, 3);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('transaction_type'); // production_add, production_deduct, adjustment_add, etc.
            $table->unsignedBigInteger('transaction_id')->nullable(); // ID of production or adjustment
            $table->decimal('change_quantity', 15, 3); // Positive or Negative
            $table->decimal('new_stock', 15, 3);
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('adjustments');
        Schema::dropIfExists('production_records');
    }
};
