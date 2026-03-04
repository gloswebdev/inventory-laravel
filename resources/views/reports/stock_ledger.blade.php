@extends('layouts.app')

@section('header', 'Stock Ledger Report')

@section('content')
<div class="bg-white rounded shadow-md p-6">
    <div class="mb-6">
        <form method="GET" action="{{ url('reports/stock-ledger') }}" class="flex items-end gap-4">
            <div class="flex-grow max-w-xs">
                <label class="block text-gray-700 text-sm font-bold mb-2">Filter by Product</label>
                <select name="product_id" class="shadow border rounded w-full py-2 px-3 text-gray-700">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Filter
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Date</th>
                    <th class="py-3 px-6 text-left">Product</th>
                    <th class="py-3 px-6 text-left">Transaction</th>
                    <th class="py-3 px-6 text-right">Change</th>
                    <th class="py-3 px-6 text-right">New Stock</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @forelse($ledger as $entry)
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-6 text-left whitespace-nowrap">{{ $entry->created_at->format('d-M-Y H:i:s') }}</td>
                    <td class="py-3 px-6 text-left">{{ $entry->product->name }}</td>
                    <td class="py-3 px-6 text-left uppercase text-xs">{{ str_replace('_', ' ', $entry->transaction_type) }}</td>
                    <td class="py-3 px-6 text-right font-bold {{ $entry->change_quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $entry->change_quantity >= 0 ? '+' : '' }}{{ $entry->change_quantity }}
                    </td>
                    <td class="py-3 px-6 text-right font-bold">{{ $entry->new_stock }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-4 text-center">No transactions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
