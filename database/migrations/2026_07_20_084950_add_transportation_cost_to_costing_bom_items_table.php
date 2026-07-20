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
        Schema::table('costing_bom_items', function (Blueprint $table) {
            $table->decimal('transportation_cost', 10, 4)->default(5.0000)->after('purity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costing_bom_items', function (Blueprint $table) {
            $table->dropColumn('transportation_cost');
        });
    }
};
