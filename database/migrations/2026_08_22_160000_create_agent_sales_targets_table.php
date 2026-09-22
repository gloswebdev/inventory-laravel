<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly SALES targets per agent.
 *
 * Deliberately separate from `agent_targets`, which the collection report uses for
 * COLLECTION targets (it compares them against the credit/receipt amounts). Sharing one
 * table would make setting a sales target silently overwrite that agent's collection
 * target for the same month, and vice versa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_sales_targets')) {
            return;
        }

        Schema::create('agent_sales_targets', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name')->index();
            $table->string('target_month', 7);           // YYYY-MM
            $table->decimal('target_amount', 15, 2);
            $table->timestamps();

            $table->unique(['agent_name', 'target_month'], 'agent_sales_target_unique');
            $table->index('target_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sales_targets');
    }
};
