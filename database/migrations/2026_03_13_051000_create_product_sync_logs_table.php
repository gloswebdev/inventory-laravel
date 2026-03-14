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
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('total_created')->default(0);
            $table->integer('total_updated')->default(0);
            $table->integer('total_skipped')->default(0);
            $table->json('created_items')->nullable(); // [{item_code, name}]
            $table->json('updated_items')->nullable(); // [{item_code, name}]
            $table->string('status')->default('success'); // success, failed
            $table->text('error_message')->nullable();
            $table->string('synced_by')->nullable(); // user name
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sync_logs');
    }
};
