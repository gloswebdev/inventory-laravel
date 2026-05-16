@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="adjustmentApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Adjust</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Manual Inventory Control</p>
        </div>
        @if(Auth::user()->hasFeature('mobile_adjustments', 'create'))
        <button @click="showModal = true" class="w-14 h-14 grad-emerald rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-emerald-100 border-2 border-white transition-all active:scale-90">
            <i class="fas fa-plus"></i>
        </button>
        @endif
    </div>
</div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-bottom duration-700">
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2.5rem] border border-white/80">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Recent Logs</div>
            <div class="text-xl font-900 text-slate-800 font-900 tracking-tighter">{{ count($adjustments) }}</div>
        </div>
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2.5rem] border border-white/80">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Permitted Items</div>
            <div class="text-xl font-900 text-emerald-600 tracking-tighter">{{ count($products) }}</div>
        </div>
    </div>

    <!-- History Logs -->
    <div class="space-y-5">
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-3">Adjustment History</h3>
        <div class="space-y-4">
            @foreach($adjustments as $adj)
            <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/80 transition-all hover:shadow-lg">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 {{ $adj->adjustment_type == 'add' ? 'bg-emerald-50 text-emerald-500' : 'bg-rose-50 text-rose-500' }} rounded-2xl flex items-center justify-center border border-white shadow-md">
                            <i class="fas {{ $adj->adjustment_type == 'add' ? 'fa-plus' : 'fa-minus' }} text-[10px]"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-900 text-slate-800 font-900 uppercase italic tracking-tighter">{{ $adj->product->name }}</div>
                            <div class="text-[8px] text-slate-400 font-bold uppercase mt-0.5">{{ $adj->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-900 {{ $adj->adjustment_type == 'add' ? 'text-emerald-500' : 'text-rose-500' }} tracking-tighter">
                            {{ $adj->adjustment_type == 'add' ? '+' : '-' }}{{ number_format($adj->quantity, 2) }}
                        </div>
                        <div class="text-[7px] font-black text-slate-300 uppercase tracking-widest">Correction</div>
                    </div>
                </div>
                @if($adj->reason)
                <div class="bg-white/40 backdrop-blur-sm p-4 rounded-2xl border border-white/60/50">
                    <p class="text-[9px] text-slate-500 font-bold leading-relaxed italic">"{{ $adj->reason }}"</p>
                </div>
                @endif
            </div>
            @endforeach
            
            @if(count($adjustments) === 0)
            <div class="py-16 text-center glass-premium rounded-[3rem] border-2 border-dashed border-white/60 opacity-50">
                <i class="fas fa-clipboard-check text-slate-200 text-3xl mb-4"></i>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">No recent adjustments found</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Create Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-[3rem] w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300 border border-white">
            <div class="grad-emerald p-8 text-white relative">
                <h3 class="text-2xl font-900 italic tracking-tighter uppercase">New Adjustment</h3>
                <p class="text-white/70 text-[10px] font-bold uppercase tracking-widest mt-1">Manual Inventory Update</p>
                <button @click="showModal = false" class="absolute top-8 right-8 w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-white border border-white/20 active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-8 space-y-6">
                <!-- Product -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Select Product</label>
                    <select x-model="form.product_id" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500 appearance-none">
                        <option value="">Choose item...</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->item_code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Type & Qty -->
                <div class="flex gap-4">
                    <div class="flex-1 space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Type</label>
                        <select x-model="form.adjustment_type" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500 appearance-none">
                            <option value="add">🟢 ADDITION</option>
                            <option value="deduct">🔴 DEDUCTION</option>
                        </select>
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Quantity</label>
                        <input type="number" step="0.001" x-model="form.quantity" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-black text-slate-700 focus:ring-2 focus:ring-emerald-500 placeholder:text-slate-200" placeholder="0.000">
                    </div>
                </div>

                <!-- Reason -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Reason / Note</label>
                    <textarea x-model="form.reason" rows="3" class="w-full bg-white/60 backdrop-blur-md border-none rounded-[1.5rem] p-6 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500 placeholder:text-slate-200" placeholder="Why are you adjusting this stock?"></textarea>
                </div>

                <button @click="submit" :disabled="loading" class="w-full grad-emerald p-1 rounded-[2rem] shadow-xl shadow-emerald-100 transition-all active:scale-[0.98] disabled:opacity-50">
                    <div class="bg-white/10 p-5 rounded-[1.9rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tighter uppercase text-sm border border-white/20">
                        <span x-show="!loading">Confirm Adjustment</span>
                        <div x-show="loading" class="animate-spin w-4 h-4 border-2 border-white/50 border-t-white rounded-full"></div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Error/Success Toasts can be shared globally, implementing simple alert for now -->
</div>

<script>
    function adjustmentApp() {
        return {
            showModal: false,
            loading: false,
            form: {
                product_id: '',
                adjustment_type: 'add',
                quantity: '',
                reason: ''
            },
            async submit() {
                if (!this.form.product_id || !this.form.quantity) {
                    alert('Please provide product and quantity.');
                    return;
                }
                
                this.loading = true;
                try {
                    const response = await fetch("{{ route('mobile.adjustments.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    });
                    const res = await response.json();
                    if (res.success) {
                        alert(res.message);
                        window.location.reload();
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Synchronisation failure.');
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endsection
