@props(['deleted' => false])

@if($deleted)
    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">
        {{ __('products.ikas_deleted') }}
    </span>
@else
    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
        {{ __('common.in_store') }}
    </span>
@endif
