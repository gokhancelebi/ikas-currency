@props(['status' => 'missing'])

@if($status === 'set')
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ __('common.set') }}</span>
@else
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">{{ __('common.unset') }}</span>
@endif
