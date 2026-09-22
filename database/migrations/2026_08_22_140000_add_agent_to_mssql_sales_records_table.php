<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sales drill-down goes branch > category > sale type > agent > party > bill > product.
 * Everything except the agent was already synced -- Sl_Head.agent_code was never carried
 * across (the old cloud sync even joined Agents_Brokers without selecting from it).
 *
 * agent_name is stored as the plain name so it lines up with the collection report and the
 * agent_targets table, which are both keyed on the same string.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            return;
        }

        Schema::table('mssql_sales_records', function (Blueprint $table) {
            if (!Schema::hasColumn('mssql_sales_records', 'agent_code')) {
                $table->integer('agent_code')->nullable()->after('act_code');
            }
            if (!Schema::hasColumn('mssql_sales_records', 'agent_name')) {
                $table->string('agent_name')->nullable()->after('agent_code');
            }
        });

        if (!$this->indexExists('mssql_sales_agent_idx')) {
            Schema::table('mssql_sales_records', function (Blueprint $table) {
                $table->index('agent_name', 'mssql_sales_agent_idx');
            });
        }

        // The drill-down filters down this exact path, so one covering index keeps the deep
        // levels fast once the cloud table holds several financial years.
        if (!$this->indexExists('mssql_sales_drill_idx')) {
            Schema::table('mssql_sales_records', function (Blueprint $table) {
                $table->index(['branch_name', 'group_name', 'series'], 'mssql_sales_drill_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            return;
        }

        Schema::table('mssql_sales_records', function (Blueprint $table) {
            foreach (['mssql_sales_agent_idx', 'mssql_sales_drill_idx'] as $index) {
                if ($this->indexExists($index)) {
                    $table->dropIndex($index);
                }
            }

            $drop = array_values(array_filter(
                ['agent_code', 'agent_name'],
                fn ($c) => Schema::hasColumn('mssql_sales_records', $c)
            ));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
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
