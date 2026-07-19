<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('products.title') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 text-gray-900">
                    @if (session('status'))
                        <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                            {{ session('status') }}
                        </div>
                    @endif
                    @unless($ratesReady)
                        <div class="mb-4 rounded-md bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                            {{ $ratesMessage }}
                        </div>
                    @endunless
                    <div class="flex flex-col gap-4 mb-4">
                        <div class="flex justify-between items-start flex-wrap gap-3">
                            <h1 class="text-2xl font-bold">{{ __('products.title') }}</h1>
                            <button type="button" class="btn bg-blue-600 px-4 py-2 text-white rounded bulk-edit-open">
                                {{ __('products.bulk_edit') }}
                            </button>
                        </div>

                        <div x-data="{ filtersOpen: {{ $filtersActive ? 'true' : 'false' }} }">
                            <button type="button"
                                    @click="filtersOpen = !filtersOpen"
                                    class="flex items-center gap-2 w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-sm font-medium text-gray-800">
                                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200"
                                     :class="{ 'rotate-180': filtersOpen }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                                <span>{{ __('common.filters') }}</span>
                                @if($filtersActive)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ __('common.filter_active') }}
                                    </span>
                                @endif
                                @if($products->total() > 0)
                                    <span class="text-xs text-gray-500 font-normal ml-auto sm:ml-0">
                                        {{ __('common.products_count', ['count' => $products->total()]) }}
                                    </span>
                                @endif
                            </button>

                            <form x-show="filtersOpen"
                                  x-cloak
                                  action="{{ route('products.index') }}" method="GET"
                                  class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                                <div class="xl:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.search') }}</label>
                                    <input type="text" name="search" value="{{ $filters['search'] }}"
                                           class="w-full rounded-md border-gray-300 shadow-sm"
                                           placeholder="{{ __('products.search_placeholder') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.cost') }}</label>
                                    <select name="cost" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['cost'] === 'all')>{{ __('common.all') }}</option>
                                        <option value="missing" @selected($filters['cost'] === 'missing')>{{ __('common.missing') }}</option>
                                        <option value="set" @selected($filters['cost'] === 'set')>{{ __('common.set') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.sync') }}</label>
                                    <select name="sync" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['sync'] === 'all')>{{ __('common.all') }}</option>
                                        <option value="active" @selected($filters['sync'] === 'active')>{{ __('common.active') }}</option>
                                        <option value="inactive" @selected($filters['sync'] === 'inactive')>{{ __('common.inactive') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.product_type') }}</label>
                                    <select name="type" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['type'] === 'all')>{{ __('common.all') }}</option>
                                        <option value="simple" @selected($filters['type'] === 'simple')>{{ __('common.simple') }}</option>
                                        <option value="variable" @selected($filters['type'] === 'variable')>{{ __('common.variable') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.multiple_price') }}</label>
                                    <select name="multiple_price" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['multiple_price'] === 'all')>{{ __('common.all') }}</option>
                                        <option value="no" @selected($filters['multiple_price'] === 'no')>{{ __('common.no') }}</option>
                                        <option value="yes" @selected($filters['multiple_price'] === 'yes')>{{ __('common.yes') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.price_type') }}</label>
                                    <select name="price_type" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['price_type'] === 'all')>{{ __('common.all') }}</option>
                                        @foreach (config('shopify.price_types', ['TL']) as $type)
                                            <option value="{{ $type }}" @selected($filters['price_type'] === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.shopify') }}</label>
                                    <select name="shopify_status" class="w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="all" @selected($filters['shopify_status'] === 'all')>{{ __('common.all') }}</option>
                                        <option value="active" @selected($filters['shopify_status'] === 'active')>{{ __('common.in_store') }}</option>
                                        <option value="deleted" @selected($filters['shopify_status'] === 'deleted')>{{ __('common.deleted') }}</option>
                                    </select>
                                </div>
                                <div class="xl:col-span-2 flex flex-col justify-end pb-2">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input type="checkbox" name="attention" value="1"
                                               @checked($filters['attention'])
                                               class="rounded border-gray-300">
                                        {{ __('products.attention_filter') }}
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('products.attention_filter_hint') }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    {{ __('common.filter') }}
                                </button>
                                <a href="{{ route('products.index') }}"
                                   class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                                    {{ __('common.clear') }}
                                </a>
                            </div>
                            </form>
                        </div>
                    </div>

                    @if($ratesReady)
                    <div class="text-center text-xs text-gray-600 space-y-1 mb-4">
                        <p>
                            @if($last_update > 0)
                                {{ __('products.last_update') }} {{ date('d.m.Y H:i', $last_update_start) }}
                                – {{ date('d.m.Y H:i', $last_update) }}
                                <span class="text-gray-400">·</span>
                            @endif
                            {{ __('products.server_time') }} {{ date('d.m.Y H:i') }}
                        </p>
                        <p class="text-gray-700">
                            @foreach($kurlar->Kur as $kur)
                                @if(in_array($kur->Aciklama,['Amerikan Doları','Avrupa Para Birimi']))
                                    <span class="inline-block mx-2"><strong>{{ $kur->Kod }}</strong> {{ $kur->Satis }}</span>
                                @endif
                            @endforeach
                            @foreach($altin->Kur as $kur)
                                @if(in_array($kur->Aciklama,['Has Toptan','Gram Toptan','22 Ayar Bilezik']))
                                    <span class="inline-block mx-2">{{ $kur->Aciklama }} {{ $kur->Satis }}</span>
                                @endif
                            @endforeach
                        </p>
                    </div>
                    @endif
                    <div class="mt-2 border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full table-auto text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="w-10 px-2 py-2.5 text-left">
                                <input type="checkbox" class="check-all rounded border-gray-300">
                            </th>
                            <th class="w-12 px-2 py-2.5 text-center">{{ __('common.image') }}</th>
                            <th class="px-3 py-2.5 text-left min-w-[10rem]">{{ __('common.product') }}</th>
                            <th class="px-2 py-2.5 text-left whitespace-nowrap">{{ __('common.type') }}</th>
                            <th class="px-2 py-2.5 text-left whitespace-nowrap">{{ __('common.shopify') }}</th>
                            <th class="px-2 py-2.5 text-right whitespace-nowrap">{{ __('common.store_price') }}</th>
                            <th class="px-2 py-2.5 text-left whitespace-nowrap">{{ __('common.cost') }}</th>
                            <th class="px-2 py-2.5 text-left whitespace-nowrap">{{ __('common.sync') }}</th>
                            <th class="px-2 py-2.5 text-right whitespace-nowrap">{{ __('common.calculated') }}</th>
                            <th class="w-12 px-2 py-2.5"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($products as $product)
                            <tr class="border-t border-gray-100 {{ $product->isDeletedFromShopify() ? 'bg-rose-50 hover:bg-rose-100/80' : 'hover:bg-gray-50' }}">
                                <td class="px-2 py-2 align-top">
                                    <input type="checkbox" name="bulk-edit[]" value="{{ $product->id }}"
                                           class="rounded border-gray-300">
                                </td>
                                <td class="px-2 py-2 text-center align-top">
                                    @if($product->shopify_image)
                                        <img src="{{ $product->shopify_image }}" alt=""
                                             class="w-10 h-10 rounded object-cover inline-block border border-gray-200">
                                    @else
                                        <span class="inline-block w-10 h-10 rounded bg-gray-100 border border-gray-200"></span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top max-w-xs sm:max-w-sm">
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $product->id) }}"
                                           class="font-medium text-indigo-600 hover:underline line-clamp-2">
                                            {{ $product->name }}
                                        </a>
                                        <p class="text-xs text-gray-500 truncate">{{ $product->sku }}</p>
                                        <x-attention-badge :reasons="$product->attentionReasons()" />
                                    </div>
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap">
                                    <x-variant-badge :count="$product->variations_count" />
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap">
                                    <x-shopify-status-badge :deleted="$product->isDeletedFromShopify()" />
                                    @if($product->isDeletedFromShopify())
                                        <p class="text-xs text-rose-600 mt-0.5">
                                            {{ $product->shopify_deleted_at->format('d.m.Y H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right align-top tabular-nums text-gray-700 whitespace-nowrap">
                                    {{ $product->shopify_price !== null ? number_format((float) $product->shopify_price, 2) : __('common.dash') }}
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap">
                                    <x-cost-badge :status="$product->listCostStatus()" />
                                    <span class="text-xs text-gray-500 block mt-0.5 max-w-[8rem] truncate" title="{{ $product->listCostLabel() }}">{{ $product->listCostLabel() }}</span>
                                </td>
                                <td class="px-2 py-2 align-top whitespace-nowrap">
                                    <x-sync-badge :enabled="$product->sync_enabled" />
                                </td>
                                <td class="px-2 py-2 text-right align-top tabular-nums whitespace-nowrap">
                                    @if($product->multiple_price === 'no' && $product->hasCostConfigured())
                                        {{ number_format((float) $product->total_price, 2) }}
                                    @else
                                        {{ __('common.dash') }}
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right align-top whitespace-nowrap">
                                    <a href="{{ route('products.show', $product->id) }}"
                                       class="text-indigo-600 hover:underline text-sm">{{ __('common.open') }}</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $products->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert-container">

    </div>

    <div class="bulk-edit-form-container flex justify-center items-center hidden fixed top-0 left-0 w-full h-screen bg-black bg-opacity-25 z-50 p-4">
        <div class="bulk-edit-form w-full max-w-lg">
            <form action="" method="post" class="flex flex-col gap-3 bg-white p-6 rounded-lg shadow-lg bulk-form max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('products.bulk_edit_title') }}</h3>
                <p class="text-sm text-gray-500">{{ __('products.bulk_edit_hint') }}</p>

                <div>
                    <label for="bulk-multiple-price" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.multiple_price') }}</label>
                    <select id="bulk-multiple-price" name="multiple_price" class="border px-4 py-2 rounded w-full">
                        <option value="">{{ __('common.no_change') }}</option>
                        <option value="no">{{ __('common.no') }}</option>
                        <option value="yes">{{ __('common.yes') }}</option>
                    </select>
                </div>
                <div>
                    <label for="bulk-sync-enabled" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.sync') }}</label>
                    <select id="bulk-sync-enabled" name="sync_enabled" class="border px-4 py-2 rounded w-full">
                        <option value="">{{ __('common.no_change') }}</option>
                        <option value="1">{{ __('common.active') }}</option>
                        <option value="0">{{ __('common.inactive') }}</option>
                    </select>
                </div>
                <div>
                    <label for="bulk-price-type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.price_type') }}</label>
                    <x-price-type-select id="bulk-price-type" :empty-option="__('common.no_change')" class="border px-4 py-2 rounded w-full" />
                </div>
                <div class="w-full flex flex-col">
                    <label for="bulk-price" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.cost') }}</label>
                    <input type="text" class="w-full rounded border-gray-300" id="bulk-price" name="price"
                           placeholder="{{ __('common.empty_no_change') }}">
                </div>
                <div class="w-full flex flex-col">
                    <label for="bulk-discount" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.discount_rate') }}</label>
                    <input type="text" class="w-full rounded border-gray-300" id="bulk-discount" name="discount"
                           placeholder="{{ __('common.empty_no_change') }}">
                </div>
                <div class="w-full flex flex-col">
                    <label for="bulk-profit" class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.profit_rate') }}</label>
                    <input type="text" class="w-full rounded border-gray-300" id="bulk-profit" name="profit"
                           placeholder="{{ __('common.empty_no_change') }}">
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t">
                    <button type="button"
                            class="px-4 py-2 rounded bg-gray-200 text-gray-800 bulk-edit-close">
                        {{ __('common.close') }}
                    </button>
                    <button type="button"
                            class="px-4 py-2 rounded bg-green-600 text-white bulk-edit-update">
                        {{ __('common.update') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .alert-container {
            position: fixed;
            bottom: 0;
            left: 20px;
            z-index: 999;
        }
        .alert-container div.success { background: rgb(22 163 74); color: #fff; padding: 10px 16px; border-radius: 6px; margin-bottom: 8px; }
        .alert-container div.error { background: rgb(220 38 38); color: #fff; padding: 10px 16px; border-radius: 6px; margin-bottom: 8px; }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script>
        $(function () {
            $('.check-all').on('click', function () {
                $('input[name="bulk-edit[]"]').prop('checked', $(this).prop('checked'));
            });
            $('.bulk-form').submit(function (e) { e.preventDefault(); });
            $('.bulk-edit-open').click(function (e) {
                e.preventDefault();
                $('.bulk-edit-form-container').removeClass('hidden');
            });
            $('.bulk-edit-close').click(function (e) {
                e.preventDefault();
                $('.bulk-edit-form-container').addClass('hidden');
            });
            $('.bulk-edit-update').click(function (e) {
                e.preventDefault();
                let product_ids = [];
                $('input[name="bulk-edit[]"]:checked').each(function () {
                    product_ids.push($(this).val());
                });
                if (product_ids.length === 0) {
                    alert(@json(__('products.messages.select_at_least_one')));
                    return;
                }
                $.ajax({
                    url: '{{ route('products.bulk_update') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'PUT',
                        product_ids: product_ids,
                        price_type: $('#bulk-price-type').val(),
                        multiple_price: $('#bulk-multiple-price').val(),
                        sync_enabled: $('#bulk-sync-enabled').val(),
                        price: $('#bulk-price').val(),
                        discount: $('#bulk-discount').val(),
                        profit: $('#bulk-profit').val(),
                    },
                    success: function (response) {
                        let cls = response.status === 'success' ? 'success' : 'error';
                        let el = $('<div class="'+cls+'">'+response.msg+'</div>');
                        $('.alert-container').append(el);
                        if (response.status === 'success') {
                            $('.bulk-edit-form-container').addClass('hidden');
                            setTimeout(() => location.reload(), 1200);
                        }
                        setTimeout(() => el.remove(), 2500);
                    }
                });
            });
        });
    </script>
</x-app-layout>
