<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_bom_items', function (Blueprint $table) {
            $table->decimal('purity', 8, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('costing_bom_items', function (Blueprint $table) {
            $table->dropColumn('purity');
        });
    }
};
