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
        Schema::table('user_permissions', function (Blueprint $table) {
            $table->boolean('can_print')->default(false)->after('can_delete');
            $table->boolean('can_export_excel')->default(false)->after('can_print');
            $table->boolean('can_export_pdf')->default(false)->after('can_export_excel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropColumn(['can_print', 'can_export_excel', 'can_export_pdf']);
        });
    }
};
