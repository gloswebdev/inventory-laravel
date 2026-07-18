<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricelists', function (Blueprint $table) {
            $table->id();
            $table->string('item_det_code')->nullable();
            $table->string('user_code')->unique();
            $table->string('item_hd_name')->nullable();
            $table->string('item_short_name')->nullable();
            $table->string('size')->nullable();
            $table->string('size_desc')->nullable();
            $table->string('group1')->nullable();
            $table->string('group2')->nullable();
            $table->string('group3')->nullable();
            $table->string('group4')->nullable();
            $table->string('group5')->nullable();
            $table->string('group6')->nullable();
            $table->decimal('mrp', 15, 4)->nullable();
            $table->decimal('sp_rate1', 15, 4)->nullable();
            $table->decimal('sp_rate2', 15, 4)->nullable();
            $table->decimal('sp_rate3', 15, 4)->nullable();
            $table->decimal('sp_rate4', 15, 4)->nullable();
            $table->decimal('sp_rate5', 15, 4)->nullable();
            $table->decimal('sale_rate', 15, 4)->nullable();
            $table->string('barcode')->nullable();
            $table->string('item_nature')->nullable();
            $table->decimal('cf_1', 15, 4)->nullable();
            $table->decimal('cf_2', 15, 4)->nullable();
            $table->decimal('cf_3', 15, 4)->nullable();
            $table->string('modify_date')->nullable();
            $table->string('gst_tax')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricelists');
    }
};
