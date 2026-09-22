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
        Schema::table('adjustments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('product_id')->constrained('users')->nullOnDelete();
            $table->string('branch_code', 50)->nullable()->after('user_id');
            $table->string('branch_name', 100)->nullable()->after('branch_code');
            $table->string('erp_push_status', 30)->default('skipped')->after('reason'); // 'pending', 'success', 'failed', 'skipped'
            $table->string('erp_doc_no', 100)->nullable()->after('erp_push_status');
            $table->text('erp_response')->nullable()->after('erp_doc_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'branch_code',
                'branch_name',
                'erp_push_status',
                'erp_doc_no',
                'erp_response',
            ]);
        });
    }
};
