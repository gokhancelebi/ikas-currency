<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('products.title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if($product->isDeletedFromIkas())
                <div class="rounded-md bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                    {{ __('products.deleted_banner') }}
                    {{ __('products.deleted_detected') }} {{ $product->ikas_deleted_at->format('d.m.Y H:i') }}.
                    {{ __('products.deleted_restore_hint') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('products.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('products.back_to_list') }}</a>
                        <div class="flex items-start gap-3 mt-2">
                            @if($product->ikas_image)
                                <img src="{{ $product->ikas_image }}" alt="" class="w-14 h-14 rounded object-cover shrink-0">
                            @endif
                            <div class="min-w-0">
                                <h1 class="text-2xl font-bold break-words">{{ $product->name }}</h1>
                                @if($product->skuLine())
                                    <p class="text-sm text-gray-500">{{ $product->skuLine() }}</p>
                                @endif
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <x-variant-badge :count="$product->variations_count" />
                                    <x-ikas-status-badge :deleted="$product->isDeletedFromIkas()" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('products.edit', $product->id) }}"
                       class="inline-flex shrink-0 items-center justify-center whitespace-nowrap px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 self-start">
                        {{ __('products.edit_product') }}
                    </a>
                </div>

                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm border rounded-lg p-4 bg-gray-50">
                    <div>
                        <dt class="text-gray-500">{{ __('common.store_price_full') }}</dt>
                        <dd class="font-medium">{{ $product->ikas_price !== null ? number_format((float) $product->ikas_price, 2) : __('common.dash') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('common.cost') }}</dt>
                        <dd class="font-medium">{{ $product->listCostLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('common.sync') }}</dt>
                        <dd><x-sync-badge :enabled="$product->sync_enabled" /></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('common.multiple_price') }}</dt>
                        <dd class="font-medium">{{ $product->multiple_price === 'yes' ? __('common.yes') : __('common.no') }}</dd>
                    </div>
                    @if($product->multiple_price === 'no')
                    <div>
                        <dt class="text-gray-500">{{ __('common.calculated_price') }}</dt>
                        <dd class="font-medium">{{ number_format((float) $product->total_price, 2) }}</dd>
                    </div>
                    @endif
                </dl>

                @if($product->isVariable())
                    <h2 class="text-lg font-semibold mt-8 mb-3">{{ __('common.variants') }}</h2>
                    <div class="overflow-x-auto border rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('common.variant') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('common.sku') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('common.store_price_full') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('common.cost') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('common.sync') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($product->variations as $variation)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-4 py-2">{{ $variation->name }}</td>
                                    <td class="px-4 py-2">{{ $variation->hasSku() ? $variation->sku : __('common.dash') }}</td>
                                    <td class="px-4 py-2">{{ $variation->ikas_price !== null ? number_format((float) $variation->ikas_price, 2) : __('common.dash') }}</td>
                                    <td class="px-4 py-2">
                                        <x-cost-badge :status="$variation->hasCostConfigured() ? 'set' : 'missing'" />
                                        @if($variation->hasCostConfigured())
                                            <span class="text-gray-600 ml-1">{{ number_format((float) $variation->price, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2"><x-sync-badge :enabled="$variation->sync_enabled" /></td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('products.variations.edit', [$product->id, $variation->id]) }}"
                                           class="text-indigo-600 hover:underline">{{ __('common.edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
