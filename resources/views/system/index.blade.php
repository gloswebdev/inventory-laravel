@extends('layouts.app')

@section('header', 'System Management')

@section('content')

{{-- Flash Messages --}}
@if(session('system_success'))
<div class="flex items-center gap-3 px-5 py-3.5 mb-6 rounded-2xl text-sm font-semibold shadow-sm"
     style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #6ee7b7;color:#065f46">
    <i class="fas fa-circle-check text-emerald-500 text-lg"></i>
    {{ session('system_success') }}
</div>
@endif

@if(session('system_error'))
<div class="flex items-center gap-3 px-5 py-3.5 mb-6 rounded-2xl text-sm font-semibold shadow-sm"
     style="background:linear-gradient(135deg,#fef2f2,#fee2e2);border:1px solid #fca5a5;color:#991b1b">
    <i class="fas fa-circle-xmark text-red-500 text-lg"></i>
    {{ session('system_error') }}
</div>
@endif

{{-- Page Header --}}
<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">System Management</h1>
        <p class="text-sm text-slate-500 mt-1">Backup, Restore, Automated Daily Email &amp; Updates &mdash; Admin Only</p>
    </div>
    <div class="flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-2xl">
        <i class="fas fa-crown text-amber-500 text-xs"></i>
        <span class="text-xs font-black text-amber-700 uppercase tracking-wider">Admin Only</span>
    </div>
</div>

{{-- VERSION INFO CARD --}}
<div class="rounded-3xl border border-slate-100 bg-white shadow-sm mb-6 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                 style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
                <i class="fas fa-tag text-white text-sm"></i>
            </div>
            <div>
                <div class="text-sm font-black text-slate-800">Current Version</div>
                <div class="text-xs text-slate-400">Application info</div>
            </div>
        </div>
        <span class="px-4 py-1.5 rounded-full text-sm font-black text-white"
              style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
            v{{ $version['version'] ?? 'unknown' }}
        </span>
    </div>
    <div class="px-6 py-5 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Version</div>
            <div class="text-sm font-bold text-slate-700">v{{ $version['version'] ?? '?' }}</div>
        </div>
        <div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Release Date</div>
            <div class="text-sm font-bold text-slate-700">{{ $version['release_date'] ?? '?' }}</div>
        </div>
        <div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Codename</div>
            <div class="text-sm font-bold text-slate-700">{{ $version['codename'] ?? '?' }}</div>
        </div>
        <div>
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Database</div>
            <div class="text-sm font-bold text-slate-700">
                {{ $tableCount }} tables &middot;
                @if($dbSizeBytes > 1048576)
                    {{ round($dbSizeBytes / 1048576, 1) }} MB
                @else
                    {{ round($dbSizeBytes / 1024, 0) }} KB
                @endif
            </div>
        </div>
    </div>
    @if(!empty($version['changelog']))
    <div class="px-6 pb-5">
        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Changelog</div>
        <div class="flex flex-wrap gap-2">
            @foreach($version['changelog'] as $item)
            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-semibold border border-indigo-100">
                {{ $item }}
            </span>
            @endforeach
        </div>
    </div>
    @endif
</div>

{{-- 🛡️ AUTOMATED DAILY DATABASE BACKUP & EMAIL CARD (NEW) --}}
<div class="rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-900 text-white shadow-xl shadow-indigo-950/10 mb-6 overflow-hidden relative">
    <div class="absolute -right-16 -top-16 w-56 h-56 bg-indigo-500/10 rounded-full blur-2xl"></div>
    <div class="absolute -left-12 -bottom-12 w-44 h-44 bg-emerald-500/10 rounded-full blur-2xl"></div>
    
    <div class="relative z-10 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-indigo-800/60 pb-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-emerald-400 shadow-inner">
                    <i class="fas fa-shield-cat text-2xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-black text-white tracking-tight">Automated Daily Database Backup &amp; Mail</h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $backupAutoEnabled == '1' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-300' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $backupAutoEnabled == '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-400' }}"></span>
                            {{ $backupAutoEnabled == '1' ? 'Active & Scheduled' : 'Disabled' }}
                        </span>
                    </div>
                    <p class="text-indigo-200/70 text-xs font-semibold mt-0.5">
                        Database ka daily full backup (.zip) automatic generate hoke aapki email par send hota hai
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('system.backup.send-email') }}" class="flex items-center gap-2" id="testEmailForm">
                @csrf
                <input type="hidden" name="target_email" value="{{ $backupEmail }}">
                <button type="submit" id="testEmailBtn"
                    class="bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold py-2.5 px-5 rounded-2xl text-xs flex items-center gap-2 transition shadow-lg shadow-emerald-600/20">
                    <i class="fas fa-paper-plane" id="testEmailIcon"></i>
                    <span>Send Backup to Email Now (Test)</span>
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Settings Form --}}
            <form method="POST" action="{{ route('system.backup.settings') }}" class="space-y-4 bg-slate-900/60 p-5 rounded-2xl border border-indigo-500/20">
                @csrf
                <div class="text-xs font-black uppercase tracking-wider text-indigo-300 flex items-center gap-2">
                    <i class="fas fa-gear text-indigo-400"></i> Backup Configuration
                </div>

                <div>
                    <label class="block text-[10px] font-black text-indigo-200 uppercase tracking-widest mb-1.5">
                        Recipient Email Address (Jahan backup aayega)
                    </label>
                    <div class="relative">
                        <input type="email" name="backup_email" value="{{ $backupEmail }}" required
                            class="w-full bg-slate-950/80 border border-indigo-500/30 rounded-xl py-2.5 pl-10 pr-4 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-400 font-mono">
                        <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-indigo-400 text-xs"></i>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="block text-[10px] font-black text-indigo-200 uppercase tracking-widest mb-1.5">
                            Cron Security Token
                        </label>
                        <input type="text" name="backup_cron_token" value="{{ $backupCronToken }}" required
                            class="w-full bg-slate-950/80 border border-indigo-500/30 rounded-xl py-2.5 px-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-400 font-mono">
                    </div>
                    <div class="flex items-center gap-2 pb-2">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="backup_auto_enabled" value="1" {{ $backupAutoEnabled == '1' ? 'checked' : '' }}
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-400 bg-slate-950 border-slate-700">
                            <span class="text-xs font-bold text-slate-200">Enable Daily Auto Backup</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-6 rounded-xl text-xs flex items-center gap-2 transition active:scale-95">
                        <i class="fas fa-floppy-disk"></i> Save Backup Settings
                    </button>
                </div>
            </form>

            {{-- Hostinger Cron Job Setup Guide --}}
            <div class="bg-slate-900/60 p-5 rounded-2xl border border-indigo-500/20 space-y-3 text-xs">
                <div class="text-xs font-black uppercase tracking-wider text-indigo-300 flex items-center gap-2">
                    <i class="fas fa-clock text-amber-400"></i> Hostinger Cron Job Setup (Daily Automatic)
                </div>

                <p class="text-indigo-200/80 text-[11px] leading-relaxed">
                    Hostinger hPanel me <strong>Advanced ➔ Cron Jobs</strong> me jakar daily schedule bana sakte hain:
                </p>

                <div class="space-y-2 font-mono text-[11px]">
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-slate-300 flex items-center justify-between gap-2">
                        <span class="truncate" id="cronUrlText">{{ url('/cron/database-backup?token=' . $backupCronToken) }}</span>
                        <button type="button" onclick="copyText('cronUrlText')" class="text-indigo-400 hover:text-indigo-300 shrink-0 font-sans font-bold text-[10px] bg-slate-800 px-2 py-1 rounded">
                            Copy URL
                        </button>
                    </div>
                    <div class="text-[10px] text-slate-400">
                        <span class="text-amber-400 font-bold">Recommended Schedule:</span> Every Day at 23:50 (11:50 PM)
                    </div>
                </div>

                {{-- Recent Backups on Server --}}
                <div class="pt-2">
                    <div class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-1.5">
                        Available Backups on Server (Last 14 Days)
                    </div>
                    @if(empty($recentBackups))
                    <div class="text-slate-500 text-[11px]">No local backup files created yet. Click "Send Backup to Email Now" to create one.</div>
                    @else
                    <div class="max-h-28 overflow-y-auto space-y-1.5 pr-1 content-scroll">
                        @foreach(array_slice($recentBackups, 0, 5) as $b)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-800 text-[11px]">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-file-zipper text-emerald-400"></i>
                                <span class="font-mono text-slate-300">{{ $b['filename'] }}</span>
                                <span class="text-[10px] text-slate-500">({{ $b['size'] }})</span>
                            </div>
                            <a href="{{ route('system.backup.download-file', $b['filename']) }}" class="text-indigo-400 hover:text-indigo-300 font-bold text-[10px] flex items-center gap-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- THREE CARDS --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- MANUAL BACKUP CARD --}}
    <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#10b981,#059669)">
                    <i class="fas fa-database text-white"></i>
                </div>
                <div>
                    <div class="text-base font-black text-slate-800">Manual DB Backup</div>
                    <div class="text-xs text-slate-400">SQL file download karo</div>
                </div>
            </div>
        </div>
        <div class="px-6 py-6">
            <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100 mb-5">
                <div class="text-xs font-bold text-emerald-700 mb-1">Backup includes:</div>
                <ul class="text-xs text-emerald-600 space-y-1">
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-emerald-500 text-[10px]"></i> Sari tables ka structure
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-emerald-500 text-[10px]"></i> Sari data rows (INSERT)
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-check text-emerald-500 text-[10px]"></i> .sql format (import-ready)
                    </li>
                </ul>
            </div>
            <div class="text-xs text-slate-500 mb-4">
                <span class="font-bold text-slate-700">{{ $tableCount }}</span> tables &middot;
                <span class="font-bold text-slate-700">
                    @if($dbSizeBytes > 1048576)
                        {{ round($dbSizeBytes / 1048576, 1) }} MB
                    @else
                        {{ round($dbSizeBytes / 1024, 0) }} KB
                    @endif
                </span> data
            </div>
            <a href="{{ route('system.backup.download') }}"
               class="w-full flex items-center justify-center gap-2.5 py-3 rounded-2xl font-black text-sm text-white transition-all hover:opacity-90 active:scale-95"
               style="background:linear-gradient(135deg,#10b981,#059669)">
                <i class="fas fa-download"></i>
                Download Instant SQL (.sql)
            </a>
        </div>
    </div>

    {{-- RESTORE CARD --}}
    <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                    <i class="fas fa-rotate-left text-white"></i>
                </div>
                <div>
                    <div class="text-base font-black text-slate-800">Database Restore</div>
                    <div class="text-xs text-slate-400">SQL backup se restore karo</div>
                </div>
            </div>
        </div>
        <div class="px-6 py-6">
            <div class="p-4 bg-red-50 rounded-2xl border border-red-100 mb-5">
                <div class="flex items-center gap-2 text-xs font-black text-red-700 mb-1">
                    <i class="fas fa-triangle-exclamation"></i> Warning!
                </div>
                <div class="text-xs text-red-600">
                    Restore karne se <strong>current data overwrite</strong> ho jayega. Pehle backup download karo!
                </div>
            </div>
            <form method="POST" action="{{ route('system.restore.upload') }}" enctype="multipart/form-data"
                  onsubmit="return confirm('Are you sure? Current database data will be overwritten!')">
                @csrf
                <label class="block mb-3">
                    <div class="text-xs font-bold text-slate-600 mb-2">SQL Backup File Select Karo:</div>
                    <div class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center cursor-pointer hover:border-amber-300 transition-colors"
                         onclick="document.getElementById('sqlFile').click()">
                        <i class="fas fa-file-code text-2xl text-slate-300 mb-2 block"></i>
                        <div class="text-xs font-bold text-slate-500" id="sqlFileName">Click to select .sql file</div>
                        <div class="text-[10px] text-slate-400 mt-1">Max: 50MB</div>
                    </div>
                    <input type="file" id="sqlFile" name="sql_file" accept=".sql,.txt" class="hidden"
                           onchange="document.getElementById('sqlFileName').textContent = this.files[0]?.name || 'No file selected'">
                </label>
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2.5 py-3 rounded-2xl font-black text-sm text-white transition-all hover:opacity-90 active:scale-95"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                    <i class="fas fa-upload"></i>
                    Restore Database
                </button>
            </form>
        </div>
    </div>

    {{-- UPDATE CARD --}}
    <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
                    <i class="fas fa-cloud-arrow-up text-white"></i>
                </div>
                <div>
                    <div class="text-base font-black text-slate-800">Upload Update</div>
                    <div class="text-xs text-slate-400">New version ZIP apply karo</div>
                </div>
            </div>
        </div>
        <div class="px-6 py-6">
            <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 mb-5">
                <div class="text-xs font-bold text-indigo-700 mb-1">Update kaise kaam karta hai:</div>
                <ul class="text-xs text-indigo-600 space-y-1">
                    <li class="flex items-center gap-2">
                        <i class="fas fa-shield-halved text-indigo-500 text-[10px]"></i> .env kabhi overwrite nahi hoga
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-shield-halved text-indigo-500 text-[10px]"></i> storage/ folder safe rahega
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-bolt text-indigo-500 text-[10px]"></i> Cache auto-clear ho jayega
                    </li>
                </ul>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 mb-4 text-xs text-slate-500">
                <strong class="text-slate-700">ZIP banane ke liye:</strong> Local PC par<br>
                <code class="text-indigo-600">.\make_deploy_zips.ps1</code> run karo<br>
                <span class="text-emerald-600">invoflow-app_*.zip</span> upload karo yahan
            </div>
            <form method="POST" action="{{ route('system.update.apply') }}" enctype="multipart/form-data"
                  id="updateForm">
                @csrf
                <label class="block mb-3">
                    <div class="text-xs font-bold text-slate-600 mb-2">invoflow-app ZIP Select Karo:</div>
                    <div class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center cursor-pointer hover:border-indigo-300 transition-colors"
                         onclick="document.getElementById('zipFile').click()">
                        <i class="fas fa-file-zipper text-2xl text-slate-300 mb-2 block"></i>
                        <div class="text-xs font-bold text-slate-500" id="zipFileName">Click to select .zip file</div>
                        <div class="text-[10px] text-slate-400 mt-1">Max: 100MB</div>
                    </div>
                    <input type="file" id="zipFile" name="update_zip" accept=".zip" class="hidden"
                           onchange="document.getElementById('zipFileName').textContent = this.files[0]?.name || 'No file selected'">
                </label>
                <button type="submit" id="updateBtn"
                    class="w-full flex items-center justify-center gap-2.5 py-3 rounded-2xl font-black text-sm text-white transition-all hover:opacity-90 active:scale-95"
                    style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
                    <i class="fas fa-rocket"></i>
                    Apply Update
                </button>
            </form>
        </div>
    </div>

</div>

{{-- CACHE MANAGEMENT --}}
<div class="mt-6 rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                 style="background:linear-gradient(135deg,#ef4444,#dc2626)">
                <i class="fas fa-broom text-white text-sm"></i>
            </div>
            <div>
                <div class="text-sm font-black text-slate-800">Cache Management</div>
                <div class="text-xs text-slate-400">Update ke baad ya koi issue ho to cache clear karo</div>
            </div>
        </div>
        <form method="POST" action="{{ route('system.cache.clear') }}"
              onsubmit="return confirm('Cache clear karna chahte ho?')">
            @csrf
            <button type="submit"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-black text-sm text-white transition-all hover:opacity-90 active:scale-95"
                style="background:linear-gradient(135deg,#ef4444,#dc2626)">
                <i class="fas fa-broom text-sm"></i>
                Clear All Cache
            </button>
        </form>
    </div>
    <div class="px-6 py-4 grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach($cacheStats as $stat)
        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
            <div class="flex items-center gap-2 mb-2">
                <i class="{{ $stat['icon'] }} text-xs" style="color:{{ $stat['color'] }}"></i>
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider">{{ $stat['label'] }}</span>
            </div>
            <div class="text-xl font-black text-slate-800">{{ $stat['count'] }}</div>
            <div class="text-[10px] text-slate-400">{{ $stat['unit'] }}</div>
        </div>
        @endforeach
    </div>
</div>

<script>
document.getElementById('updateForm')?.addEventListener('submit', function() {
    var btn = document.getElementById('updateBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    btn.disabled = true;
});

document.getElementById('testEmailForm')?.addEventListener('submit', function() {
    var btn = document.getElementById('testEmailBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating & Sending Backup...';
    btn.disabled = true;
});

function copyText(elementId) {
    var text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(function() {
        alert('Copied to clipboard: ' + text);
    });
}
</script>

@endsection
