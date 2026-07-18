<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricelists', function (Blueprint $table) {
            $table->decimal('prev_sp_rate1', 15, 4)->nullable()->after('sp_rate1');
            $table->decimal('prev_sp_rate2', 15, 4)->nullable()->after('sp_rate2');
            $table->decimal('prev_sp_rate3', 15, 4)->nullable()->after('sp_rate3');
            $table->decimal('prev_sp_rate4', 15, 4)->nullable()->after('sp_rate4');
            $table->decimal('prev_sp_rate5', 15, 4)->nullable()->after('sp_rate5');
        });
    }

    public function down(): void
    {
        Schema::table('pricelists', function (Blueprint $table) {
            $table->dropColumn([
                'prev_sp_rate1',
                'prev_sp_rate2',
                'prev_sp_rate3',
                'prev_sp_rate4',
                'prev_sp_rate5',
            ]);
        });
    }
};
