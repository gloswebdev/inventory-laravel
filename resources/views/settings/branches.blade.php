@extends('layouts.app')

@section('header', 'Settings')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-3 flex items-center gap-3 text-sm font-medium">
        <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-3 text-sm">
        <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- ============================
         API CONFIGURATION CARD
         ============================ --}}
    <form action="{{ route('settings.api.update') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Header --}}
            <div class="bg-slate-800 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold flex items-center gap-2 text-sm tracking-wide">
                    <i class="fas fa-plug text-blue-400"></i> API CONFIGURATION
                    <span class="ml-2 px-2 py-0.5 bg-blue-500/20 text-blue-300 text-[9px] font-bold rounded border border-blue-400/30">EDITABLE</span>
                </h3>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition active:scale-95">
                    <i class="fas fa-save"></i> Save API Settings
                </button>
            </div>

            <div class="p-6 space-y-8">

                {{-- ---- SECTION: ERP Base Config ---- --}}
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs font-black">1</span>
                        <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest">ERP Connection (Algebra ERP)</h4>
                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[8px] font-bold rounded">EXTERNAL</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">
                                Base URL <span class="text-blue-400 normal-case font-normal">(shared by all ERP API calls)</span>
                            </label>
                            <input type="url" name="erp_api_base_url"
                                value="{{ $settings['erp_api_base_url']->value ?? 'https://logicapi.algebraerp.com/API/SYNWOOD' }}"
                                class="w-full border border-gray-200 rounded-xl py-2.5 px-4 font-mono text-sm text-blue-700 focus:ring-2 focus:ring-blue-500 outline-none transition bg-blue-50/30">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">
                                API Key <span class="text-red-400 normal-case font-normal">(used in every request)</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="api_key_input" name="erp_api_key"
                                    value="{{ $settings['erp_api_key']->value ?? '' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 font-mono text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition pr-12">
                                <button type="button" onclick="toggleKey()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-600 transition">
                                    <i id="eye_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- ---- SECTION: ProductWiseInventory API ---- --}}
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black">2</span>
                        <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest">ProductWiseInventory API</h4>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-4 ml-8">
                        Used in: <span class="font-semibold text-slate-500">Live Stock, Production, Planning, Indent, Mobile</span>
                        &nbsp;→&nbsp; <code class="bg-slate-100 px-1.5 py-0.5 rounded text-emerald-700 text-[10px]">POST /ProductWiseInventory</code>
                    </p>

                    <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-4 space-y-4">
                        {{-- Parameter rows --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    apikey
                                    <span class="font-normal text-gray-400 normal-case">(auto from above)</span>
                                </label>
                                <div class="bg-white border border-dashed border-gray-200 rounded-xl py-2.5 px-4 text-xs text-gray-400 font-mono">
                                    ••••••••••••••••••
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Branch <span class="font-normal text-gray-400 normal-case">(ALL or specific code)</span>
                                </label>
                                <input type="text" name="inventory_api_branch"
                                    value="{{ $settings['inventory_api_branch']->value ?? 'ALL' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white"
                                    placeholder="ALL">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Item <span class="font-normal text-gray-400 normal-case">(ALL or item code)</span>
                                </label>
                                <input type="text" name="inventory_api_item"
                                    value="{{ $settings['inventory_api_item']->value ?? 'ALL' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white"
                                    placeholder="ALL">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-emerald-100">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Factory Branch Code
                                    <span class="font-normal text-gray-400 normal-case">(for Product Master page)</span>
                                </label>
                                <input type="text" name="factory_stock_branch"
                                    value="{{ $settings['factory_stock_branch']->value ?? '2' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white"
                                    placeholder="2">
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- ---- SECTION: ProductMaster API ---- --}}
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-6 h-6 rounded-full bg-cyan-600 text-white flex items-center justify-center text-xs font-black">3</span>
                        <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest">ProductMaster API</h4>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-4 ml-8">
                        Used in: <span class="font-semibold text-slate-500">Product Sync (Products page → Sync from ERP)</span>
                        &nbsp;→&nbsp; <code class="bg-slate-100 px-1.5 py-0.5 rounded text-cyan-700 text-[10px]">POST /ProductMaster</code>
                    </p>

                    <div class="bg-cyan-50/50 border border-cyan-100 rounded-xl p-4">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @php
                            $pmFields = [
                                'product_master_itemdetcode' => ['label'=>'Itemdetcode', 'placeholder'=>'0'],
                                'product_master_usercode'    => ['label'=>'Usercode',    'placeholder'=>'0'],
                                'product_master_branchcode'  => ['label'=>'Branchcode',  'placeholder'=>'0'],
                                'product_master_page_number' => ['label'=>'PageNumber',  'placeholder'=>'1'],
                                'product_master_rows'        => ['label'=>'RowsOfPage',  'placeholder'=>'10000'],
                                'product_master_txn_type'    => ['label'=>'TxnType',     'placeholder'=>'Old'],
                            ];
                            @endphp
                            @foreach($pmFields as $key => $field)
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    {{ $field['label'] }}
                                </label>
                                <input type="text" name="{{ $key }}"
                                    value="{{ $settings[$key]->value ?? $field['placeholder'] }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition bg-white"
                                    placeholder="{{ $field['placeholder'] }}">
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- ---- SECTION 4: Costing / Purchase Register API ---- --}}
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-6 h-6 rounded-full bg-yellow-500 text-white flex items-center justify-center text-xs font-black">4</span>
                        <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest">Costing API &mdash; Purchase Register</h4>
                        <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 text-[8px] font-bold rounded">COSTING MODULE</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-4 ml-8">
                        Used in: <span class="font-semibold text-slate-500">Costing → Sync Prices from ERP</span>
                        &nbsp;→&nbsp; <code class="bg-slate-100 px-1.5 py-0.5 rounded text-yellow-700 text-[10px]">POST /LogicPurchaseRegisterDetail</code>
                    </p>

                    <div class="bg-yellow-50/60 border border-yellow-100 rounded-xl p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    From Date
                                    <span class="font-normal text-gray-400 normal-case">(leave blank = auto FY start)</span>
                                </label>
                                <input type="text" name="costing_api_from_date"
                                    value="{{ $settings['costing_api_from_date']->value ?? '' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-yellow-400 outline-none transition bg-white"
                                    placeholder="e.g. 2026-04-01 (auto if blank)">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    To Date
                                    <span class="font-normal text-gray-400 normal-case">(leave blank = auto FY end)</span>
                                </label>
                                <input type="text" name="costing_api_to_date"
                                    value="{{ $settings['costing_api_to_date']->value ?? '' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-yellow-400 outline-none transition bg-white"
                                    placeholder="e.g. 2027-03-31 (auto if blank)">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Account
                                </label>
                                <input type="text" name="costing_api_account"
                                    value="{{ $settings['costing_api_account']->value ?? 'all' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-yellow-400 outline-none transition bg-white"
                                    placeholder="all">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-yellow-100">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Item
                                </label>
                                <input type="text" name="costing_api_item"
                                    value="{{ $settings['costing_api_item']->value ?? 'all' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-yellow-400 outline-none transition bg-white"
                                    placeholder="all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    Branch
                                </label>
                                <input type="text" name="costing_api_branch"
                                    value="{{ $settings['costing_api_branch']->value ?? 'all' }}"
                                    class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-yellow-400 outline-none transition bg-white"
                                    placeholder="all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">
                                    apikey
                                    <span class="font-normal text-gray-400 normal-case">(auto from above)</span>
                                </label>
                                <div class="bg-white border border-dashed border-gray-200 rounded-xl py-2.5 px-4 text-xs text-gray-400 font-mono">
                                    ••••••••••••••••••
                                </div>
                            </div>
                        </div>

                        {{-- Info box --}}
                        <div class="flex items-start gap-3 bg-white border border-yellow-100 rounded-xl p-3">
                            <i class="fas fa-circle-info text-yellow-500 text-sm mt-0.5"></i>
                            <div class="text-[11px] text-slate-500 leading-relaxed">
                                <strong class="text-slate-700">Auto FY dates:</strong> If From/To date fields are left blank, the system automatically calculates the current Financial Year
                                (April 1 → March 31). The <strong>latest purchase rate</strong> per item code (<code class="bg-slate-100 px-1 rounded text-yellow-700">User_Code</code> field) is picked from
                                the <code class="bg-slate-100 px-1 rounded text-yellow-700">CaseRate</code> column.
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- ---- SECTION 5: Purchase Report API ---- --}}
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-6 h-6 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs font-black">5</span>
                        <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest">Purchase Report API</h4>
                        <span class="px-1.5 py-0.5 bg-orange-100 text-orange-700 text-[8px] font-bold rounded">REPORTS MODULE</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-4 ml-8">
                        Used in: <span class="font-semibold text-slate-500">Reports → Purchase Report</span>
                        &nbsp;→&nbsp; <code class="bg-slate-100 px-1.5 py-0.5 rounded text-orange-700 text-[10px]">POST /LogicPurchaseRegisterDetail</code>
                    </p>

                    <div class="bg-orange-50/60 border border-orange-100 rounded-xl p-4 space-y-3">
                        {{-- Endpoint row --}}
                        <div class="flex items-start gap-3 bg-white border border-orange-100 rounded-xl p-3">
                            <i class="fas fa-plug text-orange-400 text-sm mt-0.5"></i>
                            <div class="text-[11px] text-slate-600 leading-relaxed font-mono break-all">
                                <span class="text-slate-400 font-sans font-bold">Endpoint:</span>
                                {{ $settings['erp_api_base_url']->value ?? 'https://logicapi.algebraerp.com/API/SYNWOOD' }}/LogicPurchaseRegisterDetail
                            </div>
                        </div>
                        {{-- Parameters table --}}
                        <div class="bg-white border border-orange-100 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-[11px]">
                                <thead class="bg-orange-50 border-b border-orange-100">
                                    <tr>
                                        <th class="px-4 py-2 font-black text-orange-700 uppercase tracking-wider">Parameter</th>
                                        <th class="px-4 py-2 font-black text-orange-700 uppercase tracking-wider">Current Default Value</th>
                                        <th class="px-4 py-2 font-black text-orange-700 uppercase tracking-wider">Source</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orange-50 font-mono text-slate-600">
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">apikey</td>
                                        <td class="px-4 py-2.5 text-slate-400">••••••••••••••••••</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-indigo-500">API Configuration → API Key</td>
                                    </tr>
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">FromDate</td>
                                        <td class="px-4 py-2.5">{{ $settings['costing_api_from_date']->value ?? '(auto FY start)' }}</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-yellow-600">Costing API → From Date</td>
                                    </tr>
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">ToDate</td>
                                        <td class="px-4 py-2.5">{{ $settings['costing_api_to_date']->value ?? '(auto FY end)' }}</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-yellow-600">Costing API → To Date</td>
                                    </tr>
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">Account</td>
                                        <td class="px-4 py-2.5">{{ $settings['costing_api_account']->value ?? 'all' }}</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-yellow-600">Costing API → Account</td>
                                    </tr>
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">Item</td>
                                        <td class="px-4 py-2.5">{{ $settings['costing_api_item']->value ?? 'all' }}</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-yellow-600">Costing API → Item</td>
                                    </tr>
                                    <tr class="hover:bg-orange-50/30">
                                        <td class="px-4 py-2.5 font-bold text-slate-700">Branch</td>
                                        <td class="px-4 py-2.5">{{ $settings['costing_api_branch']->value ?? 'all' }}</td>
                                        <td class="px-4 py-2.5 text-[10px] font-sans font-bold text-yellow-600">Costing API → Branch</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-start gap-3 bg-white border border-orange-100 rounded-xl p-3">
                            <i class="fas fa-circle-info text-orange-400 text-sm mt-0.5"></i>
                            <div class="text-[11px] text-slate-500 leading-relaxed">
                                The Purchase Report page allows <strong>on-the-fly overrides</strong> of all parameters via its filter form. Default values are pulled from
                                the <strong>Costing API</strong> section above. To change defaults permanently, update Section 4 settings above and save.
                                &nbsp;<a href="{{ route('reports.purchase') }}" class="text-orange-600 font-bold underline underline-offset-2">Open Purchase Report →</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /p-6 --}}
        </div>{{-- /card --}}
    </form>

    {{-- ============================
         BRANCH MAPPINGS
         ============================ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold flex items-center">
                <i class="fas fa-building mr-2"></i> Add New Branch Mapping
            </h3>
        </div>
        <form action="{{ route('settings.branches.store') }}" method="POST" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Branch Code</label>
                    <input type="text" name="code" placeholder="e.g. 2" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition" required>
                </div>
                <div class="md:col-span-2 flex gap-3 items-end">
                    <div class="flex-grow">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Branch Name</label>
                        <input type="text" name="name" placeholder="e.g. Main Warehouse" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition" required>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg hover:shadow-indigo-100 transition transform active:scale-95 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
            <h3 class="text-gray-700 font-bold flex items-center">
                <i class="fas fa-list-ul mr-2 text-indigo-500"></i> Existing Branch Mappings
            </h3>
        </div>
        <div class="p-0">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Branch Code</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Display Name</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($branches as $branch)
                    <tr class="hover:bg-indigo-50/30 transition">
                        <td class="px-6 py-4">
                            <span class="bg-indigo-100 text-indigo-700 font-black px-2 py-1 rounded-lg text-xs">{{ $branch->code }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <form action="{{ route('settings.branches.update') }}" method="POST" class="flex items-center gap-2 group">
                                @csrf
                                <input type="hidden" name="branches[0][code]" value="{{ $branch->code }}">
                                <input type="text" name="branches[0][name]" value="{{ $branch->name }}" class="bg-transparent border-none focus:ring-0 font-bold text-gray-700 w-full p-0">
                                <button type="submit" class="opacity-0 group-hover:opacity-100 text-indigo-500 hover:text-indigo-700 transition">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="{{ route('settings.branches.destroy', $branch) }}" method="POST" onsubmit="return confirm('Delete this mapping?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-300 hover:text-red-500 transition">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-gray-400 italic">No branch mappings found. Add one above.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-4">
        <div class="text-amber-500 text-xl"><i class="fas fa-info-circle"></i></div>
        <div class="text-amber-800 text-sm">
            <p class="font-bold mb-1">How API Settings work:</p>
            Changes are saved to the database and take effect immediately. The stock cache is automatically cleared when you save, so live data will refresh on the next page load.
        </div>
    </div>

</div>

<script>
function toggleKey() {
    const input = document.getElementById('api_key_input');
    const icon  = document.getElementById('eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
@endsection
