<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('products.variant_edit_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <a href="{{ route('products.show', $product->id) }}" class="text-sm text-indigo-600 hover:underline">← {{ $product->name }}</a>
                <h1 class="text-2xl font-bold mt-2">{{ $variation->name }}</h1>
                @if($variation->skuLine())
                    <p class="text-sm text-gray-500 mb-6">{{ $variation->skuLine() }}</p>
                @endif

                <form action="{{ route('products.update', $product->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="variation_id" value="{{ $variation->id }}">

                    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg text-sm">
                        <div>
                            <span class="text-gray-500">{{ __('common.store_price_full') }}</span>
                            <p class="font-medium">{{ $variation->ikas_price !== null ? number_format((float) $variation->ikas_price, 2) : __('common.dash') }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('common.calculated_price') }}</span>
                            <p class="font-medium">{{ number_format((float) $variation->total_price, 2) }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.sync') }}</label>
                        <select name="sync_enabled" class="w-full rounded-md border-gray-300">
                            <option value="1" @selected($variation->sync_enabled)>{{ __('common.active') }}</option>
                            <option value="0" @selected(! $variation->sync_enabled)>{{ __('common.inactive') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.price_type') }}</label>
                        <x-price-type-select :value="$variation->price_type" class="w-full rounded-md border-gray-300" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.cost') }}</label>
                        <input type="text" name="price" class="w-full rounded-md border-gray-300"
                               placeholder="{{ __('products.cost_placeholder') }}"
                               value="{{ $variation->hasCostConfigured() ? $variation->price : '' }}">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.discount_pct') }}</label>
                            <input type="text" name="discount" value="{{ $variation->discount }}" class="w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.profit_pct') }}</label>
                            <input type="text" name="profit" value="{{ $variation->profit }}" class="w-full rounded-md border-gray-300">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">{{ __('common.save') }}</button>
                        <a href="{{ route('products.show', $product->id) }}" class="px-4 py-2 bg-gray-200 rounded-md">{{ __('common.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
