@extends('layouts.app')

@section('header', 'Stock Adjustments')

@section('content')
<div class="grid grid-cols-1 {{ Auth::user()->hasPermission('adjustments', 'create') ? 'md:grid-cols-2' : '' }} gap-6">
    <!-- Adjustment Form -->
    @if(Auth::user()->hasPermission('adjustments', 'create'))
    <div class="bg-white rounded shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-700 mb-4">New Adjustment</h3>
        <form method="POST" action="{{ route('adjustments.store') }}">
            @csrf
            {{-- ... form content ... --}}
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Product</label>
                <select name="product_id" id="product_id" class="shadow border rounded w-full py-2 px-3 text-gray-700" required onchange="updateCurrentStock(this)">
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" data-stock="{{ $product->current_stock }}">{{ $product->name }} ({{ $product->uom }})</option>
                    @endforeach
                </select>
                <p id="currentStockDisplay" class="text-sm text-gray-500 mt-1 hidden">Current Stock: <span id="stockValue" class="font-bold"></span></p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Adjustment Type</label>
                <select name="adjustment_type" class="shadow border rounded w-full py-2 px-3 text-gray-700" required>
                    <option value="add">Add Stock (+)</option>
                    <option value="deduct">Deduct Stock (-)</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                <input type="number" step="0.001" name="quantity" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Reason (Optional)</label>
                <textarea name="reason" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Save Adjustment
                </button>
            </div>
        </form>
    </div>
    @endif

    <!-- Recent Adjustments -->
    <div class="bg-white rounded shadow-md p-6 h-fit">
        <h3 class="text-lg font-bold text-gray-700 mb-4">Recent Adjustments</h3>
        {{-- ... table content ... --}}
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-xs leading-normal">
                        <th class="py-3 px-4 text-left">Date</th>
                        <th class="py-3 px-4 text-left">Product</th>
                        <th class="py-3 px-4 text-left">Type</th>
                        <th class="py-3 px-4 text-right">Qty</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    @forelse($adjustments as $adj)
                    <tr class="border-b border-gray-200">
                        <td class="py-2 px-4 text-left whitespace-nowrap">{{ $adj->created_at->format('d-M-Y H:i') }}</td>
                        <td class="py-2 px-4 text-left">{{ $adj->product->name }}</td>
                        <td class="py-2 px-4 text-left">
                            <span class="{{ $adj->adjustment_type == 'add' ? 'text-green-600' : 'text-red-600' }} font-bold uppercase text-xs">
                                {{ $adj->adjustment_type }}
                            </span>
                        </td>
                        <td class="py-2 px-4 text-right font-bold">{{ $adj->quantity }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-4 text-center">No recent adjustments.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function updateCurrentStock(select) {
        const option = select.options[select.selectedIndex];
        const stock = option.getAttribute('data-stock');
        const display = document.getElementById('currentStockDisplay');
        const valueSpan = document.getElementById('stockValue');

        if (stock) {
            valueSpan.textContent = stock;
            display.classList.remove('hidden');
        } else {
            display.classList.add('hidden');
        }
    }
</script>
@endsection
