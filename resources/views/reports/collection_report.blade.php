@extends('layouts.app')
@section('header', 'Collection Report')

@section('content')
@php
    $parseAmt = fn($v) => is_numeric(str_replace(',', '', (string)$v)) ? (float)str_replace(',', '', (string)$v) : 0;

    $formatIndian = function($num) {
        $num = str_replace([',', ' '], '', (string)$num);
        if (!is_numeric($num)) {
            return $num;
        }
        $num = round((float)$num);
        $numStr = (string)$num;
        $isNegative = false;
        if (str_starts_with($numStr, '-')) {
            $isNegative = true;
            $numStr = substr($numStr, 1);
        }
        $len = strlen($numStr);
        if ($len <= 3) {
            return ($isNegative ? '-' : '') . $numStr;
        }
        $lastThree = substr($numStr, -3);
        $remaining = substr($numStr, 0, -3);
        $remainingGroups = [];
        while (strlen($remaining) > 0) {
            if (strlen($remaining) > 2) {
                $remainingGroups[] = substr($remaining, -2);
                $remaining = substr($remaining, 0, -2);
            } else {
                $remainingGroups[] = $remaining;
                $remaining = '';
            }
        }
        $remainingGroups = array_reverse($remainingGroups);
        return ($isNegative ? '-' : '') . implode(',', $remainingGroups) . ',' . $lastThree;
    };

    $renderTeamNode = function($team, $dbTeams, $grouped, $branchSummary, $teamTargets, $agentTargets, $crField, $drField, $partyNameKey, $parseAmt, $groupColors, $colorIndex) use (&$renderTeamNode, $formatIndian) {
        $branchName = $team->name;
        $branchSlug = 'grp_' . Str::slug($branchName) . '_' . $team->id;
        $bSummary = $branchSummary[$branchName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
        
        $tTargetAmt = $teamTargets[$team->id] ?? 0;
        if ($tTargetAmt == 0) {
            $sumTargets = function($tNode) use (&$sumTargets, $dbTeams, $agentTargets) {
                $sum = 0;
                foreach ($tNode->agents ?: [] as $ag) {
                    $sum += ($agentTargets[$ag] ?? 0);
                }
                foreach ($dbTeams->where('parent_id', $tNode->id) as $chNode) {
                    $sum += $sumTargets($chNode);
                }
                return $sum;
            };
            $tTargetAmt = $sumTargets($team);
        }
        $tPercent = $tTargetAmt > 0 ? min(999, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
        
        $childrenTeams = $dbTeams->where('parent_id', $team->id);
        $directAgentNames = $team->agents ?: [];

        // Determine level styling
        $level = $colorIndex; // 0 = Parent Region, 1 = Sub-Region, 2+ = Sub-Team
        $headerBg = 'bg-slate-950';
        $borderStyle = 'border-slate-800';
        $levelBadge = '<span class="bg-amber-400/20 text-amber-300 border border-amber-400/30 text-[8px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">Parent Region</span>';
        $iconClass = 'fa-crown text-amber-400';

        if ($level === 1) {
            $headerBg = 'bg-slate-900';
            $borderStyle = 'border-indigo-200';
            $levelBadge = '<span class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[8px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">Sub-Region</span>';
            $iconClass = 'fa-sitemap text-indigo-400';
        } elseif ($level >= 2) {
            $headerBg = 'bg-slate-800';
            $borderStyle = 'border-violet-200';
            $levelBadge = '<span class="bg-violet-500/20 text-violet-300 border border-violet-500/30 text-[8px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">Sub-Team</span>';
            $iconClass = 'fa-users-rectangle text-violet-400';
        }
        
        // Render current team card
        ?>
        <div class="bg-white rounded-3xl shadow-sm border <?php echo $borderStyle; ?> overflow-hidden mb-4 transition-all duration-200 hover:shadow-md" id="<?php echo $branchSlug; ?>">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between px-6 py-5 <?php echo $headerBg; ?> cursor-pointer select-none gap-4"
                 onclick="toggleBranch('<?php echo $branchSlug; ?>')">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-11 h-11 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0 shadow-inner">
                        <i class="fas <?php echo $iconClass; ?> text-base"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-white font-black text-base md:text-lg tracking-tight"><?php echo htmlspecialchars($branchName); ?></span>
                            <?php echo $levelBadge; ?>
                        </div>
                        <div class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-1 flex items-center gap-2">
                            <span><i class="fas fa-user-tie text-[9px] mr-1 text-slate-400"></i><?php echo $bSummary['agents']; ?> agents</span>
                            <span>·</span>
                            <span><i class="fas fa-building text-[9px] mr-1 text-slate-400"></i><?php echo number_format($bSummary['parties']); ?> parties / accounts</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between lg:justify-end gap-6 flex-wrap">
                    <?php if($tTargetAmt > 0): ?>
                    <div class="bg-white/5 border border-white/10 rounded-2xl px-4 py-2 flex flex-col items-end">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-emerald-400"><?php echo $tPercent; ?>% Achieved</span>
                            <span class="text-[9px] text-slate-400 font-bold">(Goal: ₹<?php echo $formatIndian($tTargetAmt); ?>)</span>
                        </div>
                        <div class="w-36 bg-slate-800 rounded-full h-1.5 overflow-hidden border border-slate-700 mt-1.5">
                            <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-1.5 rounded-full" style="width: <?php echo min(100, $tPercent); ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="text-right">
                        <div class="text-emerald-400 font-black text-xl tracking-tight">₹<?php echo $formatIndian($bSummary['total']); ?></div>
                        <div class="text-slate-400 text-[8px] font-black uppercase tracking-widest">Team Collection</div>
                    </div>

                    <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20 transition">
                        <i class="fas fa-chevron-down text-slate-300 text-xs transition-transform duration-200 branch-chevron" id="<?php echo $branchSlug; ?>_chev"></i>
                    </div>
                </div>
            </div>

            <div id="<?php echo $branchSlug; ?>_body" class="branch-body hidden p-5 bg-slate-50/40 space-y-5">
                <?php // Children teams nested ?>
                <?php if($childrenTeams->count() > 0): ?>
                    <div class="pl-4 md:pl-6 border-l-2 border-dashed border-indigo-200 space-y-4">
                        <div class="text-[9px] font-black text-indigo-500 uppercase tracking-widest ml-1 flex items-center gap-1.5">
                            <i class="fas fa-network-wired"></i> Sub-Regions & Sub-Teams under <?php echo htmlspecialchars($branchName); ?>
                        </div>
                        <?php foreach($childrenTeams as $childTeam):
                            $renderTeamNode($childTeam, $dbTeams, $grouped, $branchSummary, $teamTargets, $agentTargets, $crField, $drField, $partyNameKey, $parseAmt, $groupColors, $colorIndex + 1);
                        endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php // Direct Agents Table ?>
                <?php
                $hasDirectAgents = false;
                foreach ($directAgentNames as $agentName) {
                    if (isset($grouped[$branchName][$agentName])) {
                        $hasDirectAgents = true;
                        break;
                    }
                }
                ?>

                <?php if($hasDirectAgents): ?>
                <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full text-left border-collapse">
                        <thead class="bg-slate-900 border-b border-slate-800 text-[9px] font-black text-slate-300 uppercase tracking-widest">
                            <tr>
                                <th class="py-3 px-4 border-r border-slate-800 text-center w-12">#</th>
                                <th class="py-3 px-4 border-r border-slate-800">Salesman / Agent Name</th>
                                <th class="py-3 px-4 border-r border-slate-800 text-center w-20">Parties</th>
                                <th class="py-3 px-4 border-r border-slate-800 text-right w-36">Actual Collection</th>
                                <th class="py-3 px-4 border-r border-slate-800 text-right w-36">Monthly Target</th>
                                <th class="py-3 px-4 border-r border-slate-800 text-center w-44">Achievement Progress</th>
                                <th class="py-3 px-4 text-center w-24">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <?php $agentIdx = 0;
                        foreach($directAgentNames as $agentName):
                            if(!isset($grouped[$branchName][$agentName])) continue;
                            $agentRows = $grouped[$branchName][$agentName];
                            $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                            $agentParties = count($agentRows);
                            $agentSlug = $branchSlug . '_ag_' . Str::slug($agentName);
                            $agentIdx++;

                            $targetAmt = $agentTargets[$agentName] ?? 0;
                            $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                            $progressColor = 'bg-slate-300';
                            if ($targetAmt > 0) {
                                if ($percent >= 100) $progressColor = 'bg-emerald-500';
                                elseif ($percent >= 50) $progressColor = 'bg-amber-500';
                                else $progressColor = 'bg-rose-550';
                            }
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors cursor-pointer agent-row text-xs"
                                onclick="toggleAgentDetail('<?php echo $agentSlug; ?>', this)">
                                <td class="py-3 px-4 border-r border-gray-100 text-slate-400 font-bold text-center"><?php echo $agentIdx; ?></td>
                                <td class="py-3 px-4 border-r border-gray-100">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-violet-50 border border-violet-100 flex items-center justify-center flex-shrink-0 text-violet-600">
                                            <i class="fas fa-user-tie text-xs"></i>
                                        </div>
                                        <span class="font-black text-slate-800 text-[13px]"><?php echo htmlspecialchars($agentName); ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 border-r border-gray-100 text-center">
                                    <span class="bg-blue-50 text-blue-700 border border-blue-150 font-black text-[10px] px-2 py-0.5 rounded-lg">
                                        <?php echo $agentParties; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 border-r border-gray-100 text-right font-black text-slate-800 text-sm">
                                    ₹<?php echo $formatIndian($agentTotal); ?>
                                </td>
                                <td class="py-3 px-4 border-r border-gray-100 text-right font-bold text-slate-500">
                                    <?php if($targetAmt > 0): ?>
                                        ₹<?php echo $formatIndian($targetAmt); ?>
                                    <?php else: ?>
                                        <span class="text-gray-300 italic text-[10px]">Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 border-r border-gray-100">
                                    <?php if($targetAmt > 0): ?>
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-[9px] font-black">
                                                <span class="text-slate-600"><?php echo round(($agentTotal / $targetAmt) * 100); ?>%</span>
                                                <span class="text-slate-400">₹<?php echo $formatIndian(max(0, $targetAmt - $agentTotal)); ?> left</span>
                                            </div>
                                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                                <div class="<?php echo $progressColor; ?> h-1.5 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-[10px] block text-center">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-indigo-600 hover:text-indigo-800 transition-colors">
                                        <i class="fas fa-chevron-down text-[8px] agent-chev transition-transform duration-200" id="<?php echo $agentSlug; ?>_chev"></i>
                                        <span class="agent-chev-text">Expand</span>
                                    </span>
                                </td>
                            </tr>

                            <tr id="<?php echo $agentSlug; ?>" class="agent-detail hidden">
                                <td colspan="7" class="p-0 bg-slate-50/50">
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                                <i class="fas fa-building-user text-violet-500"></i> Account Listings under <?php echo htmlspecialchars($agentName); ?>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 cursor-pointer select-none bg-white border border-gray-200 px-2.5 py-1 rounded-xl hover:bg-slate-50 transition">
                                                    <input type="checkbox" onchange="toggleZeroCollectionParties(this, '<?php echo $agentSlug; ?>_tbody')" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 text-xs">
                                                    <span>Hide Zero Collection</span>
                                                </label>
                                                <input type="text"
                                                    placeholder="Instant Filter..."
                                                    oninput="filterAgentParties(this, '<?php echo $agentSlug; ?>_tbody')"
                                                    class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-violet-300 outline-none w-44 bg-white">
                                            </div>
                                        </div>

                                        <div class="rounded-2xl overflow-hidden border border-gray-200 shadow-sm bg-white">
                                            <table class="min-w-full text-left border-collapse">
                                                <thead class="bg-indigo-50/60 text-[9px] font-black text-indigo-800 uppercase tracking-widest border-b border-indigo-100">
                                                    <tr>
                                                        <th class="py-2.5 px-3 border-r border-indigo-150/40 text-center w-10">#</th>
                                                        <th class="py-2.5 px-3 border-r border-indigo-150/40 w-28">A/C Code</th>
                                                        <th class="py-2.5 px-3 border-r border-indigo-150/40">Party Name</th>
                                                        <th class="py-2.5 px-3 border-r border-indigo-150/40">Town / Location</th>
                                                        <?php if($crField): ?>
                                                        <th class="py-2.5 px-3 text-right w-36">Collection</th>
                                                        <?php endif; ?>
                                                        <?php if($drField): ?>
                                                        <th class="py-2.5 px-3 text-right border-l border-indigo-150/40 w-36">Debit</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100" id="<?php echo $agentSlug; ?>_tbody">
                                                    <?php foreach($agentRows as $pi => $party):
                                                        $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                                                        $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                                                        $pTown  = $party['_TownName'] ?? '—';
                                                        $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                                                        $pDrAmt = $drField ? $parseAmt($party[$drField] ?? 0) : 0;
                                                        ?>
                                                        <tr class="hover:bg-slate-50 transition-colors party-row text-[11px]" data-collection-amount="<?php echo $pCrAmt; ?>">
                                                            <td class="py-2 px-3 border-r border-gray-100 text-slate-400 font-bold text-center"><?php echo $pi + 1; ?></td>
                                                            <td class="py-2 px-3 border-r border-gray-100 font-mono text-[10px] text-indigo-600 font-black">
                                                                <?php echo $pCode ?: '—'; ?>
                                                            </td>
                                                            <td class="py-2 px-3 border-r border-gray-100 font-bold text-slate-800"><?php echo htmlspecialchars($pName); ?></td>
                                                            <td class="py-2 px-3 border-r border-gray-100 text-slate-500">
                                                                <?php if($pTown && $pTown !== '—'): ?>
                                                                <span class="flex items-center gap-1"><i class="fas fa-location-dot text-rose-500 text-[8px]"></i><?php echo htmlspecialchars($pTown); ?></span>
                                                                <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                                                            </td>
                                                            <?php if($crField): ?>
                                                            <td class="py-2 px-3 text-right font-black text-emerald-700">
                                                                <?php echo $pCrAmt > 0 ? '₹' . $formatIndian($pCrAmt) : '—'; ?>
                                                            </td>
                                                            <?php endif; ?>
                                                            <?php if($drField): ?>
                                                            <td class="py-2 px-3 text-right border-l border-gray-100 font-bold text-rose-600">
                                                                <?php echo $pDrAmt > 0 ? '₹' . $formatIndian($pDrAmt) : '—'; ?>
                                                            </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="bg-indigo-50/80 border-t border-indigo-100 text-[11px] font-black text-indigo-950">
                                                    <tr>
                                                        <td colspan="4" class="py-2 px-3 text-right uppercase tracking-widest text-[9px] border-r border-indigo-100">Sub-total →</td>
                                                        <?php if($crField): ?>
                                                        <td class="py-2 px-3 text-right text-emerald-700">₹<?php echo $formatIndian($agentTotal); ?></td>
                                                        <?php endif; ?>
                                                        <?php if($drField):
                                                            $agentDrTotal = array_sum(array_map(fn($r) => $parseAmt($r[$drField] ?? 0), $agentRows));
                                                            ?>
                                                            <td class="py-2 px-3 text-right border-l border-indigo-100 text-rose-700">₹<?php echo $formatIndian($agentDrTotal); ?></td>
                                                        <?php endif; ?>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    };
@endphp

<style>
    .team-btn.active {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
</style>

<div class="space-y-6">
{{-- ═══ HEADER ═══ --}}
<div class="bg-gradient-to-br from-blue-700 to-indigo-800 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
    <div class="absolute -right-8 -top-8 w-44 h-44 bg-white/10 rounded-full blur-xl"></div>
    <div class="absolute -left-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full blur-lg"></div>
    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center shadow-lg">
                <i class="fas fa-chart-line text-2xl text-blue-200"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Collection Analyzer</h1>
                <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mt-0.5">
                    Teamwise / Agentwise Sales Grouping
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if(Auth::user()->hasPermission('collection_report', 'create') || Auth::user()->role === 'admin')
            <a href="{{ route('reports.agent-targets.index') }}"
               class="bg-indigo-600 hover:bg-indigo-750 border border-indigo-500 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition flex items-center gap-2">
                <i class="fas fa-bullseye"></i>
                <span>Set targets</span>
            </a>
            <a href="{{ route('reports.teams.setup') }}"
               class="bg-emerald-600 hover:bg-emerald-700 border border-emerald-500 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition flex items-center gap-2">
                <i class="fas fa-sitemap"></i>
                <span>Configure Teams Hierarchy</span>
            </a>
            @endif
            <a href="{{ route('reports.collection', array_merge(request()->except('refresh_party_master'), ['refresh_party_master' => 1])) }}"
               class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition">
                <i class="fas fa-arrows-rotate text-blue-300"></i>
            </a>
        </div>
    </div>
</div>

{{-- ═══ MODERN FILTERS LAYOUT ═══ --}}
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-slate-50 px-6 py-4 border-b border-gray-100">
        <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-filter text-blue-500 text-sm"></i>
            Interactive Filtering Controls
        </h3>
    </div>
    
    <form method="GET" action="{{ route('reports.collection') }}" id="collectionFilterForm" class="p-6 space-y-6">
        <input type="hidden" name="fetch" value="1">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            {{-- Month Filter --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">
                    <i class="fas fa-calendar-days text-indigo-500 mr-1.5"></i>Select Month
                </label>
                <div class="relative">
                    <select name="month_filter" id="month_filter_select" onchange="handleMonthChange(this.value)"
                            class="w-full border border-gray-200 rounded-2xl py-3 px-4 pr-9 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-400 outline-none transition appearance-none bg-white">
                        @foreach($monthOptions ?? [] as $mKey => $mLabel)
                            <option value="{{ $mKey }}" {{ ($monthFilter ?? $defaults['month_filter']) === $mKey ? 'selected' : '' }}>{{ $mLabel }}</option>
                        @endforeach
                        <option value="custom" {{ ($monthFilter ?? '') === 'custom' ? 'selected' : '' }}>Custom Date Range...</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            {{-- Date Ranges --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">From Date</label>
                <input type="date" name="from_date"
                    value="{{ $fromDate ?? $defaults['from_date'] }}"
                    class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/50">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">To Date</label>
                <input type="date" name="to_date"
                    value="{{ $toDate ?? $defaults['to_date'] }}"
                    class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/50">
            </div>

            {{-- Agent Filter --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">
                    <i class="fas fa-user-tie text-violet-400 mr-1.5"></i>Agent Filter
                </label>
                <div class="relative">
                    <select name="agent_filter"
                        class="w-full border border-gray-200 rounded-2xl py-3 px-4 pr-9 text-sm focus:ring-2 focus:ring-violet-400 outline-none transition appearance-none bg-white">
                        <option value="">All Agents</option>
                        @foreach($agentOptions ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($agentFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-violet-400">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Group Name / Branch Multiple Select --}}
        <div>
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">
                <i class="fas fa-network-wired text-blue-500 mr-1.5"></i>Group Name / Branch (Hold Ctrl to select multiple)
            </label>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                <div class="lg:col-span-8">
                    <select name="branch_filter[]" multiple size="4"
                        class="w-full border border-gray-200 rounded-2xl p-3 text-xs focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/20">
                        @foreach($branchOptions ?? [] as $opt)
                            <option value="{{ $opt }}" {{ in_array($opt, $branchFilter ?? []) ? 'selected' : '' }} class="p-1 rounded mb-0.5">
                                {{ $opt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-4 text-xs text-slate-400 leading-relaxed">
                    💡 If nothing is highlighted, it will display <strong>All Group Names</strong>. Hold <kbd class="px-1 py-0.5 bg-slate-100 rounded border">Ctrl</kbd> to toggle multiple selections.
                </div>
            </div>
        </div>

        {{-- Hide Zero Collection Toggle --}}
        <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-2xl hover:bg-slate-100 transition">
                <input type="checkbox" name="hide_zero_collection" value="1" {{ request()->boolean('hide_zero_collection') ? 'checked' : '' }} class="rounded border-slate-350 text-blue-600 focus:ring-blue-500">
                <span><i class="fas fa-eye-slash text-slate-400 mr-1"></i> Hide Parties with Zero Collection (Show Only Active Collections)</span>
            </label>
        </div>

        {{-- Team Maker row --}}
        <div class="border-t border-slate-100 pt-5">
            <div class="flex items-center justify-between mb-3 ml-1">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    <i class="fas fa-users-gear text-emerald-500 mr-1.5"></i>Select Teams (Group Filters)
                </label>
                <a href="{{ route('reports.teams.setup') }}" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
                    <i class="fas fa-gear text-[10px]"></i> Manage Teams Hierarchy →
                </a>
            </div>
            <div class="flex flex-wrap gap-2.5 items-center">
                @forelse($dbTeams ?? [] as $team)
                    @php $isActive = in_array($team->id, $selectedTeams ?? []); @endphp
                    <div class="inline-flex items-center bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-slate-350 transition">
                        <button type="button" 
                            onclick="toggleTeamFilter('{{ $team->id }}')"
                            id="btn_team_{{ $team->id }}"
                            class="team-btn px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition {{ $isActive ? 'active' : '' }}">
                            <i class="fas fa-users mr-1.5"></i> {{ $team->name }}
                        </button>
                    </div>

                    {{-- Hidden inputs to submit selected teams --}}
                    @if($isActive)
                        <input type="hidden" name="teams[]" value="{{ $team->id }}" id="input_team_{{ $team->id }}">
                    @endif
                @empty
                    <p class="text-xs text-slate-400 italic">No custom teams created yet. <a href="{{ route('reports.teams.setup') }}" class="text-indigo-600 font-bold underline">Click here to setup teams</a>.</p>
                @endforelse
                
                @if(!empty($selectedTeams))
                    <button type="button" onclick="clearTeams()" class="text-xs text-rose-500 font-bold hover:underline ml-auto flex items-center gap-1">
                        <i class="fas fa-trash-can"></i> Reset Teams
                    </button>
                @endif
            </div>
        </div>

        {{-- Form submission button block --}}
        <div class="border-t border-slate-100 pt-5 flex justify-center">
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-black py-3.5 px-10 rounded-2xl shadow-lg hover:shadow-blue-150 text-sm tracking-wide transition transform active:scale-98">
                <i class="fas fa-table-list mr-2"></i> View Report Teamwise / Agentwise
            </button>
        </div>
    </form>
</div>

{{-- ═══ SUMMARY CARDS ═══ --}}
@if(isset($grouped) && count($grouped) > 0)
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Records</div>
            <div class="text-2xl font-black text-slate-800">{{ number_format($totalParties ?? 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500"><i class="fas fa-list-check"></i></div>
    </div>
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Group Names</div>
            <div class="text-2xl font-black text-blue-600">{{ number_format(count($grouped)) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500"><i class="fas fa-network-wired"></i></div>
    </div>
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Sales Agents</div>
            <div class="text-2xl font-black text-violet-600">{{ number_format($totalAgents ?? 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center text-violet-500"><i class="fas fa-user-tie"></i></div>
    </div>
    <div class="bg-emerald-500 rounded-3xl p-5 text-white flex items-center justify-between shadow-lg shadow-emerald-100">
        <div>
            <div class="text-[9px] font-bold text-emerald-100 uppercase tracking-widest mb-1">Grand Collection</div>
            <div class="text-2xl font-black">₹{{ $formatIndian($grandTotal ?? 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white"><i class="fas fa-circle-dollar-to-slot"></i></div>
    </div>
</div>
@endif

{{-- ═══ REPORT RENDER ═══ --}}
@if(!isset($grouped))
<div class="bg-white rounded-3xl border border-gray-100 shadow-sm py-24 text-center">
    <div class="w-20 h-20 bg-slate-50 border border-dashed border-slate-200 rounded-3xl flex items-center justify-center mx-auto mb-5">
        <i class="fas fa-chart-pie text-3xl text-blue-400/80"></i>
    </div>
    <p class="text-slate-500 font-black text-base font-bold">Filters are set.</p>
    <p class="text-slate-400 text-xs mt-1">Please click the big <strong>View Report</strong> button above to load the analyzer.</p>
</div>
@elseif(count($grouped) === 0)
<div class="bg-white rounded-3xl border border-gray-100 shadow-sm py-16 text-center">
    <i class="fas fa-inbox text-5xl text-slate-200 mb-3 animate-bounce"></i>
    <p class="text-slate-400 font-bold text-sm">No data returned for selected filters.</p>
</div>
@else

{{-- Group Name wise Accordions --}}
@php
    $groupColors = ['blue','indigo','violet','purple','fuchsia','pink','rose','orange','amber','emerald'];
    $colorIndex = 0;
@endphp

@foreach($dbTeams->whereNull('parent_id') as $team)
@php
    if (!isset($branchSummary[$team->name])) continue;
    $renderTeamNode($team, $dbTeams, $grouped, $branchSummary, $teamTargets, $agentTargets, $crField, $drField, $partyNameKey, $parseAmt, $groupColors, 0);
@endphp
@endforeach

@foreach($grouped as $branchName => $agents)
@php
    $isCustomTeam = $dbTeams->contains('name', $branchName);
    if ($isCustomTeam) continue;

    $bColor = $groupColors[$colorIndex % count($groupColors)];
    $bSummary = $branchSummary[$branchName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
    $colorIndex++;
    $branchSlug = 'grp_' . Str::slug($branchName);
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-4" id="{{ $branchSlug }}">
    {{-- Group Header Bar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between px-6 py-5 bg-slate-900 cursor-pointer select-none gap-4"
         onclick="toggleBranch('{{ $branchSlug }}')">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center">
                <i class="fas fa-users-rectangle text-{{ $bColor }}-400 text-sm"></i>
            </div>
            <div>
                <div class="text-white font-black text-base tracking-tight">{{ $branchName }}</div>
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                    {{ $bSummary['agents'] }} agents &nbsp;·&nbsp; {{ number_format($bSummary['parties']) }} parties / accounts
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-5">
            <div class="text-right">
                <div class="text-emerald-400 font-black text-lg">₹{{ $formatIndian($bSummary['total']) }}</div>
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest">Team Collection</div>
            </div>
            <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform duration-200 branch-chevron" id="{{ $branchSlug }}_chev"></i>
        </div>
    </div>

    {{-- Agent rows nested --}}
    <div id="{{ $branchSlug }}_body" class="branch-body hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-gray-100 text-[9px] font-black text-slate-500 uppercase tracking-widest">
                    <tr>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-14">#</th>
                        <th class="py-3 px-6 border-r border-gray-100">Salesman / Agent Name</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-20">Parties</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-right w-36">Actual Collection</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-right w-36">Monthly Target</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-48">Achievement Progress</th>
                        <th class="py-3 px-6 text-center w-24">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                @php $agentIdx = 0; @endphp
                @foreach($agents as $agentName => $agentRows)
                @php
                    $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                    $agentParties = count($agentRows);
                    $agentSlug = $branchSlug . '_ag_' . Str::slug($agentName);
                    $agentIdx++;

                    // Target details
                    $targetAmt = $agentTargets[$agentName] ?? 0;
                    $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                    $progressColor = 'bg-slate-300';
                    if ($targetAmt > 0) {
                        if ($percent >= 100) $progressColor = 'bg-emerald-500';
                        elseif ($percent >= 50) $progressColor = 'bg-amber-500';
                        else $progressColor = 'bg-rose-550';
                    }
                @endphp

                {{-- Agent Summary Row --}}
                <tr class="hover:bg-slate-50/50 transition-colors cursor-pointer agent-row text-xs"
                    onclick="toggleAgentDetail('{{ $agentSlug }}', this)">
                    <td class="py-3 px-6 border-r border-gray-50 text-gray-400 font-bold text-center">{{ $agentIdx }}</td>
                    <td class="py-3 px-6 border-r border-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-violet-50 flex items-center justify-center flex-shrink-0 text-violet-600">
                                <i class="fas fa-user-tie text-xs"></i>
                            </div>
                            <span class="font-bold text-slate-800 text-[13px]">{{ $agentName }}</span>
                        </div>
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-center">
                        <span class="bg-blue-50 text-blue-700 border border-blue-100 font-black text-[10px] px-2 py-0.5 rounded-lg">
                            {{ $agentParties }}
                        </span>
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-right font-black text-slate-800">
                        ₹{{ $formatIndian($agentTotal) }}
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-right font-bold text-slate-500">
                        @if($targetAmt > 0)
                            ₹{{ $formatIndian($targetAmt) }}
                        @else
                            <span class="text-gray-300 italic text-[11px]">Not Set</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50">
                        @if($targetAmt > 0)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-black">
                                    <span class="text-slate-500">{{ round(($agentTotal / $targetAmt) * 100) }}%</span>
                                    <span class="text-slate-400">₹{{ $formatIndian(max(0, $targetAmt - $agentTotal)) }} left</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="{{ $progressColor }} h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @else
                            <span class="text-gray-300 text-[11px] block text-center">—</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 text-center">
                        <span class="inline-flex items-center gap-1 text-[10px] font-black text-slate-400 hover:text-blue-600 transition-colors">
                            <i class="fas fa-chevron-down text-[9px] agent-chev transition-transform duration-200" id="{{ $agentSlug }}_chev"></i>
                            <span class="agent-chev-text">Expand</span>
                        </span>
                    </td>
                </tr>

                {{-- Expanded Party List details --}}
                <tr id="{{ $agentSlug }}" class="agent-detail hidden">
                    <td colspan="5" class="p-0 bg-slate-50/30">
                        <div class="px-8 py-5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                    <i class="fas fa-building-user text-violet-500"></i> Account Listings under {{ $agentName }}
                                </div>
                                <input type="text"
                                    placeholder="Instant Filter accounts..."
                                    oninput="filterAgentParties(this, '{{ $agentSlug }}_tbody')"
                                    class="border border-gray-200 rounded-xl px-4 py-2 text-xs font-semibold focus:ring-2 focus:ring-violet-300 outline-none w-52 bg-white">
                            </div>

                            <div class="rounded-2xl overflow-hidden border border-gray-150 shadow-sm bg-white">
                                <table class="min-w-full text-left border-collapse">
                                    <thead class="bg-indigo-50/50 text-[9px] font-black text-indigo-700 uppercase tracking-widest border-b border-indigo-100">
                                        <tr>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40 text-center w-12">#</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40 w-28">A/C Code</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40">Party Name</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40">Town / Location</th>
                                            @if($crField)
                                            <th class="py-2.5 px-4 text-right w-36">Collection</th>
                                            @endif
                                            @if($drField)
                                            <th class="py-2.5 px-4 text-right border-l border-indigo-150/40 w-36">Debit</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100" id="{{ $agentSlug }}_tbody">
                                        @foreach($agentRows as $pi => $party)
                                        @php
                                            $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                                            $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                                            $pTown  = $party['_TownName'] ?? '—';
                                            $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                                            $pDrAmt = $drField ? $parseAmt($party[$drField] ?? 0) : 0;
                                        @endphp
                                        <tr class="hover:bg-slate-50 transition-colors party-row text-xs">
                                            <td class="py-2 px-4 border-r border-gray-100 text-gray-400 font-bold text-center">{{ $pi + 1 }}</td>
                                            <td class="py-2 px-4 border-r border-gray-100 font-mono text-[10px] text-indigo-600 font-black">
                                                {{ $pCode ?: '—' }}
                                            </td>
                                            <td class="py-2 px-4 border-r border-gray-100 font-bold text-slate-800">{{ $pName }}</td>
                                            <td class="py-2 px-4 border-r border-gray-100 text-slate-500">
                                                @if($pTown && $pTown !== '—')
                                                <span class="flex items-center gap-1.5"><i class="fas fa-location-dot text-rose-500 text-[9px]"></i>{{ $pTown }}</span>
                                                @else<span class="text-gray-300">—</span>@endif
                                            </td>
                                            @if($crField)
                                            <td class="py-2 px-4 text-right font-black text-emerald-700">
                                                {{ $pCrAmt > 0 ? '₹' . $formatIndian($pCrAmt) : '—' }}
                                            </td>
                                            @endif
                                            @if($drField)
                                            <td class="py-2 px-4 text-right border-l border-gray-100 font-bold text-rose-600">
                                                {{ $pDrAmt > 0 ? '₹' . $formatIndian($pDrAmt) : '—' }}
                                            </td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    {{-- Sub totals --}}
                                    <tfoot class="bg-indigo-50 border-t border-indigo-100 text-xs font-black text-indigo-900">
                                        <tr>
                                            <td colspan="4" class="py-2 px-4 text-right uppercase tracking-widest text-[9px] border-r border-indigo-100">Sub-total →</td>
                                            @if($crField)
                                            <td class="py-2 px-4 text-right text-emerald-700">₹{{ $formatIndian($agentTotal) }}</td>
                                            @endif
                                            @if($drField)
                                            @php $agentDrTotal = array_sum(array_map(fn($r) => $parseAmt($r[$drField] ?? 0), $agentRows)); @endphp
                                            <td class="py-2 px-4 text-right border-l border-indigo-100 text-rose-700">₹{{ $formatIndian($agentDrTotal) }}</td>
                                            @endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach

                {{-- Branch total summary row --}}
                <tr class="bg-slate-800 border-t border-slate-700">
                    <td colspan="3" class="py-3 px-6 text-right text-slate-400 text-[10px] font-black uppercase tracking-widest">
                        Group Total ({{ $bSummary['agents'] }} Agents, {{ number_format($bSummary['parties']) }} Accounts) →
                    </td>
                    <td class="py-3 px-6 text-right font-black text-emerald-400 text-base">
                        ₹{{ $formatIndian($bSummary['total']) }}
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

{{-- GRAND TOTAL --}}
<div class="bg-slate-900 rounded-3xl p-6 flex items-center justify-between shadow-xl">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center">
            <i class="fas fa-chart-line text-emerald-400"></i>
        </div>
        <div>
            <div class="text-white font-black text-base">Grand Total</div>
            <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                {{ count($grouped) }} groups · {{ number_format($totalAgents) }} agents · {{ number_format($totalParties) }} accounts
            </div>
        </div>
    </div>
    <div class="text-emerald-400 font-black text-2xl">₹{{ $formatIndian($grandTotal) }}</div>
</div>

@endif
</div>

<script>

// ── Team Maker interaction ──────────────────────────────────────────────────
function toggleTeamFilter(teamId) {
    const btn = document.getElementById('btn_team_' + teamId);
    const form = document.getElementById('collectionFilterForm');
    
    let input = document.getElementById('input_team_' + teamId);
    
    if (btn.classList.contains('active')) {
        btn.classList.remove('active');
        if (input) input.remove();
    } else {
        btn.classList.add('active');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'teams[]';
            input.value = teamId;
            input.id = 'input_team_' + teamId;
            form.appendChild(input);
        }
    }
}

function clearTeams() {
    document.querySelectorAll('.team-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('input[name="teams[]"]').forEach(input => input.remove());
    document.getElementById('collectionFilterForm').submit();
}

// ── Branch Accordion toggle ─────────────────────────────────────────────────
function toggleBranch(branchId) {
    const body = document.getElementById(branchId + '_body');
    const chev = document.getElementById(branchId + '_chev');
    if (!body) return;
    const isOpen = !body.classList.contains('hidden');
    if (isOpen) {
        body.classList.add('hidden');
        if (chev) chev.style.transform = 'rotate(0deg)';
    } else {
        body.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
    }
}

// ── Agent detail accordion toggle ──────────────────────────────────────────
function toggleAgentDetail(agentId, row) {
    const detail = document.getElementById(agentId);
    const chev   = document.getElementById(agentId + '_chev');
    if (!detail) return;

    const isOpen = !detail.classList.contains('hidden');

    const parentContainer = row.closest('.branch-body');
    if (parentContainer) {
        parentContainer.querySelectorAll('.agent-detail').forEach(d => {
            if (d.id !== agentId && !d.classList.contains('hidden')) {
                d.classList.add('hidden');
                const c = document.getElementById(d.id + '_chev');
                if (c) c.style.transform = '';
                const txt = d.previousElementSibling?.querySelector('.agent-chev-text');
                if (txt) txt.textContent = 'Expand';
            }
        });
    }

    if (isOpen) {
        detail.classList.add('hidden');
        if (chev) chev.style.transform = '';
        const txt = row.querySelector('.agent-chev-text');
        if (txt) txt.textContent = 'Expand';
    } else {
        detail.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
        const txt = row.querySelector('.agent-chev-text');
        if (txt) txt.textContent = 'Collapse';
        setTimeout(() => detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);
    }
}

// ── Zero Collection Party Filter ─────────────────────────────────────────────
function toggleZeroCollectionParties(checkbox, tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const hideZero = checkbox.checked;
    const rows = tbody.querySelectorAll('.party-row');
    rows.forEach(row => {
        const amt = parseFloat(row.getAttribute('data-collection-amount') || 0);
        if (hideZero && amt === 0) {
            row.classList.add('hidden-by-zero-filter');
            row.style.display = 'none';
        } else {
            row.classList.remove('hidden-by-zero-filter');
            row.style.display = '';
        }
    });

    const searchInput = checkbox.closest('.flex').querySelector('input[type="text"]');
    if (searchInput && searchInput.value) {
        filterAgentParties(searchInput, tbodyId);
    }
}

// ── Local search filter ─────────────────────────────────────────────────────
function filterAgentParties(input, tbodyId) {
    const q     = input.value.toLowerCase().trim();
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.querySelectorAll('.party-row').forEach(row => {
        if (row.classList.contains('hidden-by-zero-filter')) {
            row.style.display = 'none';
            return;
        }
        row.style.display = q ? (row.textContent.toLowerCase().includes(q) ? '' : 'none') : '';
    });
}

// ── Month Selector Auto-Date Updater ─────────────────────────────────────────
function handleMonthChange(val) {
    if (val === 'custom') return;
    const fromInput = document.querySelector('input[name="from_date"]');
    const toInput   = document.querySelector('input[name="to_date"]');
    if (fromInput && toInput && val) {
        const parts = val.split('-');
        const year  = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        
        const firstDay = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDayObj = new Date(year, month, 0);
        const lastDay  = `${year}-${String(month).padStart(2, '0')}-${String(lastDayObj.getDate()).padStart(2, '0')}`;
        
        fromInput.value = firstDay;
        toInput.value   = lastDay;
    }
}
</script>
@endsection
