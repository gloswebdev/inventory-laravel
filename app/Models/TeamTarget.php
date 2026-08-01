<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamTarget extends Model
{
    protected $fillable = ['team_id', 'target_month', 'target_amount'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
