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
        Schema::table('costing_boms', function (Blueprint $table) {
            $table->string('badge', 50)->nullable()->after('finished_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costing_boms', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
