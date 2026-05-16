@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="productionApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Production</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Record daily yield data</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="px-3 py-1 bg-white/60 border border-white rounded-full text-[8px] font-black uppercase tracking-widest text-slate-500 shadow-md">Post-Entry</div>
            <div class="w-3 h-3 bg-rose-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(244,63,94,0.5)]"></div>
        </div>
    </div>
</div>
    
    @if(Auth::user()->hasFeature('mobile_production', 'management'))
    <!-- Entry Form -->
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-8 rounded-[3rem] space-y-8 border border-white/80">
        <!-- Branch Context -->
        <div class="space-y-3">
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 block">Operation Branch</label>
            <div class="relative group">
                <select x-model="form.branch_code" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-[1.5rem] py-4 px-6 text-sm font-bold text-slate-800 font-900 focus:ring-2 focus:ring-indigo-500 appearance-none transition-all">
                    <option value="">Select Production site...</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
                <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-location-arrow text-[10px]"></i>
                </div>
            </div>
        </div>

        <!-- Product Select -->
        <div class="space-y-3">
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 block">Finished Goods</label>
            <div class="relative group">
                <select x-model="form.product_id" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-[1.5rem] py-4 px-6 text-sm font-bold text-slate-800 font-900 focus:ring-2 focus:ring-indigo-500 appearance-none transition-all">
                    <option value="">Choose specific product...</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->pack_name }})</option>
                    @endforeach
                </select>
                <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-box-open text-[10px]"></i>
                </div>
            </div>
        </div>

        <!-- Quantity Input -->
        <div class="space-y-3">
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 block">Batch Quantity</label>
            <div class="relative">
                <input type="number" step="0.001" x-model="form.quantity" placeholder="0.000" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-[1.5rem] py-5 px-8 text-3xl font-900 text-slate-800 font-900 focus:ring-2 focus:ring-rose-500 focus:border-rose-300 placeholder:text-slate-200 transition-all shadow-inner">
                <div class="absolute right-8 top-1/2 -translate-y-1/2 text-[10px] font-black text-rose-500 uppercase tracking-[0.2em]">BOX UNITS</div>
            </div>
        </div>

        <!-- Submit Button -->
        <button 
            @click="submit" 
            :disabled="loading"
            class="w-full grad-rose p-1 rounded-[2rem] shadow-xl shadow-rose-200 transition-all active:scale-[0.97] group disabled:opacity-50 disabled:scale-100"
        >
            <div class="bg-white/10 p-5 rounded-[1.9rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tighter uppercase text-sm border border-white/20">
                <template x-if="!loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-paper-plane group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform"></i>
                        <span>Publish Entry</span>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-atom fa-spin"></i>
                        <span>Validating...</span>
                    </div>
                </template>
            </div>
        </button>
    </div>
    @else
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-10 rounded-[3rem] text-center border-2 border-dashed border-white/60">
        <div class="w-16 h-16 bg-white/60 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-lock text-slate-200 text-2xl"></i>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Recording Restricted</p>
    </div>
    @endif

    @if(Auth::user()->hasFeature('mobile_production', 'history'))
    <!-- History Section -->
    <div class="space-y-6">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Transaction History</h3>
            <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Last 10 Logs</span>
        </div>

        <div class="space-y-4">
            @foreach($history as $item)
            <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2rem] border border-white/80 flex items-center justify-between group active:scale-[0.98] transition-all">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 border border-indigo-100/50">
                        <i class="fas fa-box-open text-xs"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-900 text-slate-800 font-900 truncate max-w-[150px] uppercase tracking-tighter">{{ $item->product_name ?? ($item->product ? $item->product->name : 'Unknown') }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[8px] font-black text-indigo-400 uppercase">{{ $item->production->branch_code ?? 'N/A' }}</span>
                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                            <span class="text-[8px] text-slate-400 font-bold">{{ $item->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-900 text-slate-800 font-900 tracking-tighter">+{{ number_format($item->quantity_box, 2) }}</div>
                    <div class="text-[7px] font-black text-emerald-500 uppercase tracking-widest">Yield Posted</div>
                </div>
            </div>
            @endforeach

            @if(count($history) === 0)
            <div class="p-10 text-center opacity-40 italic text-[10px] font-bold text-slate-400">
                No recent production logs found.
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Feedback Toast -->
    <div 
        x-show="toast.show" 
        x-cloak 
        x-transition:enter="transition ease-out duration-500"
        x-transition:enter-start="opacity-0 translate-y-20 scale-90"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-10 scale-95"
        class="fixed bottom-28 left-6 right-6 z-50 p-1 rounded-[2rem] shadow-2xl"
        :class="toast.success ? 'grad-emerald' : 'grad-rose'"
    >
        <div class="bg-white/95 backdrop-blur-md rounded-[1.9rem] p-5 flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-lg" :class="toast.success ? 'grad-emerald' : 'grad-rose'">
                <i class="fas" :class="toast.success ? 'fa-check' : 'fa-bolt'"></i>
            </div>
            <div>
                <div class="text-[9px] font-black uppercase tracking-widest" :class="toast.success ? 'text-emerald-500' : 'text-rose-500'" x-text="toast.success ? 'Success Confirmed' : 'System Error'"></div>
                <p class="text-[13px] font-bold text-slate-800 font-900 leading-tight mt-0.5" x-text="toast.message"></p>
            </div>
        </div>
    </div>

    <!-- Quick Info -->
    <div class="grad-cyan p-0.5 rounded-[2.5rem] opacity-80">
        <div class="bg-white/90 backdrop-blur-sm p-6 rounded-[2.4rem] flex items-start gap-5">
            <div class="w-12 h-12 bg-cyan-50 rounded-2xl flex items-center justify-center text-cyan-600 shrink-0 border border-cyan-100 shadow-inner">
                <i class="fas fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <h4 class="text-[11px] font-900 text-slate-700 uppercase tracking-tighter italic">Automatic Sync</h4>
                <p class="text-[10px] text-slate-500 leading-relaxed mt-1 font-bold">Yield submission triggers real-time stock deduction for raw materials based on the master recipe profile.</p>
            </div>
        </div>
    </div>
</div>

<script>
    function productionApp() {
        return {
            loading: false,
            form: {
                branch_code: '',
                product_id: '',
                quantity: ''
            },
            toast: {
                show: false,
                success: true,
                message: ''
            },
            showToast(message, success = true) {
                this.toast.message = message;
                this.toast.success = success;
                this.toast.show = true;
                setTimeout(() => this.toast.show = false, 4000);
            },
            async submit() {
                if (!this.form.branch_code || !this.form.product_id || !this.form.quantity) {
                    this.showToast('Kindly ensure all fields are properly filled.', false);
                    return;
                }

                this.loading = true;
                try {
                    const response = await fetch("{{ route('mobile.production.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.showToast(data.message, true);
                        this.form.product_id = '';
                        this.form.quantity = '';
                    } else {
                        this.showToast(data.message, false);
                    }
                } catch (e) {
                    this.showToast('System connectivity error. Try again.', false);
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endsection
