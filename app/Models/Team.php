<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name', 'agents', 'branches', 'child_teams', 'parent_id'];

    protected $casts = [
        'agents'      => 'array',
        'branches'    => 'array',
        'child_teams' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(Team::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Team::class, 'parent_id');
    }

    public function getEffectiveAgents($allTeams = null, &$visited = [])
    {
        if (in_array($this->id, $visited)) {
            return [];
        }
        $visited[] = $this->id;

        if ($allTeams === null) {
            $allTeams = self::all();
        }
        $agents = $this->agents ?: [];

        // Accumulate from children teams
        $children = $allTeams->where('parent_id', $this->id);
        foreach ($children as $child) {
            $agents = array_merge($agents, $child->getEffectiveAgents($allTeams, $visited));
        }

        // Keep fallback support for child_teams column
        if (is_array($this->child_teams)) {
            foreach ($this->child_teams as $cId) {
                $child = $allTeams->firstWhere('id', $cId);
                if ($child) {
                    $agents = array_merge($agents, $child->getEffectiveAgents($allTeams, $visited));
                }
            }
        }
        return array_unique(array_filter($agents));
    }

    public function getEffectiveBranches($allTeams = null, &$visited = [])
    {
        if (in_array($this->id, $visited)) {
            return [];
        }
        $visited[] = $this->id;

        if ($allTeams === null) {
            $allTeams = self::all();
        }
        $branches = $this->branches ?: [];

        // Accumulate from children teams
        $children = $allTeams->where('parent_id', $this->id);
        foreach ($children as $child) {
            $branches = array_merge($branches, $child->getEffectiveBranches($allTeams, $visited));
        }

        // Keep fallback support for child_teams column
        if (is_array($this->child_teams)) {
            foreach ($this->child_teams as $cId) {
                $child = $allTeams->firstWhere('id', $cId);
                if ($child) {
                    $branches = array_merge($branches, $child->getEffectiveBranches($allTeams, $visited));
                }
            }
        }
        return array_unique(array_filter($branches));
    }
}
