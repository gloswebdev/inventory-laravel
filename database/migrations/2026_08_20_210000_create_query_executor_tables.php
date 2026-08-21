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
        // 1. Query Jobs table (Queue of on-demand & scheduled query executions)
        Schema::create('query_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_token', 64)->unique()->index();
            $table->text('query_sql');
            $table->string('db_type', 30)->default('mssql'); // mssql, mysql
            $table->string('status', 30)->default('pending')->index(); // pending, running, completed, failed, expired
            $table->json('result_columns')->nullable(); // ['item_code', 'item_name', 'qty', ...]
            $table->longText('result_rows')->nullable(); // JSON array of row objects
            $table->integer('row_count')->default(0);
            $table->decimal('execution_seconds', 8, 3)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 2. Saved Queries table (Preset query templates)
        Schema::create('saved_queries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('description', 255)->nullable();
            $table->text('query_sql');
            $table->string('target_table', 60)->nullable(); // sales_registers, mssql_sales_records, products, purchase_registers, etc.
            $table->json('column_mapping')->nullable(); // {'vouch_date': 'VouchDate', 'item_code': 'ItemCode'}
            $table->boolean('is_favorite')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_queries');
        Schema::dropIfExists('query_jobs');
    }
};
