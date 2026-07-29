<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricelist_push_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('total_items')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->string('price_list'); // Sp_Rate1 .. Sp_Rate5
            $table->string('status')->default('success'); // success, partial, failed
            $table->text('error_message')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('pushed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pricelist_push_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricelist_push_log_id')->constrained('pricelist_push_logs')->onDelete('cascade');
            $table->string('user_code');
            $table->string('item_name')->nullable();
            $table->string('price_list');
            $table->decimal('old_value', 15, 4)->nullable();
            $table->decimal('new_value', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricelist_push_log_items');
        Schema::dropIfExists('pricelist_push_logs');
    }
};
