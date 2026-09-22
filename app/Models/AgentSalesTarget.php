<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Monthly sales target for an agent.
 *
 * Not to be confused with AgentTarget, which holds COLLECTION targets used by the
 * collection report. They are separate tables on purpose -- an agent normally has a
 * different number for what they should sell and what they should collect.
 */
class AgentSalesTarget extends Model
{
    protected $table = 'agent_sales_targets';

    protected $fillable = ['agent_name', 'target_month', 'target_amount'];

    protected $casts = ['target_amount' => 'decimal:2'];
}
