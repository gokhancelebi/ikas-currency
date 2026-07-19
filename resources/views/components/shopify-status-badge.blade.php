@props(['deleted' => false])

@if($deleted)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800']) }}>
        {{ __('products.shopify_deleted') }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800']) }}>
        {{ __('common.in_store') }}
    </span>
@endif
