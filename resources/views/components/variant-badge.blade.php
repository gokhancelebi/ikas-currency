@props(['count' => 0])

@if ((int) $count > 0)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800']) }}>
        {{ __('products.variable_count', ['count' => (int) $count]) }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700']) }}>
        {{ __('common.simple') }}
    </span>
@endif
