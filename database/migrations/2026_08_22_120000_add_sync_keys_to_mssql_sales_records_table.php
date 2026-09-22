<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the identity + classification columns the InvoFlow Sync Agent needs so that
 * every push is idempotent (upsert on financial_year + txn_code) instead of the old
 * "delete a date range, then insert" approach which lost data whenever a batch failed
 * halfway through.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            return;
        }

        Schema::table('mssql_sales_records', function (Blueprint $table) {
            if (!Schema::hasColumn('mssql_sales_records', 'financial_year')) {
                $table->string('financial_year', 15)->nullable()->after('id');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'txn_code')) {
                // Sl_Txn<FY>.code -> the ERP's own primary key for a bill line.
                $table->integer('txn_code')->nullable()->after('financial_year');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'vouch_code')) {
                $table->integer('vouch_code')->nullable()->after('vouch_num');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'series_type')) {
                // Bill_Ser.type -> SL = sale, SR = sale return, CH/JV/... = non-sales
                $table->string('series_type', 5)->nullable()->after('series');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'is_stock_transfer')) {
                // Bill_Ser.Stock_Trans -> replaces the fragile "series LIKE '%ST%'" guessing
                $table->boolean('is_stock_transfer')->default(false)->after('series_type');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'entry_datetime')) {
                // Sl_Head.LTZ_Entry_Date -> the watermark the agent syncs on
                $table->dateTime('entry_datetime')->nullable()->after('vouch_time');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'calc_freight')) {
                $table->decimal('calc_freight', 15, 4)->default(0)->after('calc_net_amt');
            }
        });

        $this->addIndexIfMissing('mssql_sales_uniq_line', function (Blueprint $table) {
            $table->unique(['financial_year', 'txn_code'], 'mssql_sales_uniq_line');
        });

        $this->addIndexIfMissing('mssql_sales_entry_idx', function (Blueprint $table) {
            $table->index(['financial_year', 'entry_datetime'], 'mssql_sales_entry_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            return;
        }

        Schema::table('mssql_sales_records', function (Blueprint $table) {
            foreach (['mssql_sales_uniq_line', 'mssql_sales_entry_idx'] as $index) {
                if ($this->indexExists($index)) {
                    $table->dropIndex($index);
                }
            }

            $drop = array_values(array_filter(
                ['txn_code', 'vouch_code', 'series_type', 'is_stock_transfer', 'entry_datetime', 'calc_freight'],
                fn ($c) => Schema::hasColumn('mssql_sales_records', $c)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }

    private function addIndexIfMissing(string $name, callable $definition): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        Schema::table('mssql_sales_records', $definition);
    }

    private function indexExists(string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'mssql_sales_records')
            ->where('index_name', $name)
            ->exists();
    }
};
