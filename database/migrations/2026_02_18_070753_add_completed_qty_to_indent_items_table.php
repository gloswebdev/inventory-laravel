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
        Schema::table('indent_items', function (Blueprint $table) {
            $table->decimal('completed_qty', 15, 2)->default(0)->after('final_qty_box');
        });
    }

    public function down(): void
    {
        Schema::table('indent_items', function (Blueprint $table) {
            $table->dropColumn('completed_qty');
        });
    }
};
