<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTarget extends Model
{
    protected $fillable = ['agent_name', 'target_month', 'target_amount'];
}
