@props(['enabled' => true])

@if($enabled)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ __('common.active') }}</span>
@else
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">{{ __('common.inactive') }}</span>
@endif
