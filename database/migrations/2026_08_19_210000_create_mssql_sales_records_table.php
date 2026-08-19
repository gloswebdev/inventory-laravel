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
        Schema::create('mssql_sales_records', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name')->nullable()->index();
            $table->integer('branch_code')->nullable()->index();
            $table->date('vouch_date')->nullable()->index();
            $table->string('vouch_time')->nullable();
            $table->string('vouch_num')->nullable()->index();
            $table->string('act_name')->nullable()->index();
            $table->integer('act_code')->nullable()->index();
            $table->integer('item_det_code')->nullable()->index();
            $table->decimal('tot_qty', 15, 4)->default(0);
            $table->decimal('calc_net_amt_n', 15, 4)->default(0);
            $table->decimal('free_qty', 15, 4)->default(0);
            $table->decimal('rate', 15, 4)->default(0);
            $table->decimal('calc_tax_1', 15, 4)->default(0);
            $table->decimal('calc_tax_2', 15, 4)->default(0);
            $table->decimal('calc_tax_3', 15, 4)->default(0);
            $table->decimal('discount_rs', 15, 4)->default(0);
            $table->decimal('calc_scheme_rs', 15, 4)->default(0);
            $table->decimal('calc_gross_amt', 15, 4)->default(0);
            $table->decimal('calc_net_amt', 15, 4)->default(0);
            $table->string('sale_or_sr', 10)->nullable();
            $table->string('user_code', 50)->nullable()->index();
            $table->decimal('weight_per_unit', 15, 4)->default(0);
            $table->decimal('cf_1', 15, 4)->default(0);
            $table->integer('item_hd_code')->nullable()->index();
            $table->string('item_hd_name')->nullable()->index();
            $table->string('lot_number')->nullable();
            $table->integer('lot_code')->nullable();
            $table->decimal('pur_rate', 15, 4)->default(0);
            $table->decimal('basic_rate', 15, 4)->default(0);
            $table->string('mobile_no')->nullable();
            $table->integer('cust_hd_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('cashier_name')->nullable();
            $table->string('group_name')->nullable()->index();
            $table->string('pack_name')->nullable();
            $table->string('series', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mssql_sales_records');
    }
};
