@props([
    'name' => 'price_type',
    'value' => '',
    'id' => null,
    'class' => 'border px-4 py-2 rounded',
    'emptyOption' => null,
])

<select
    name="{{ $name }}"
    @if($id) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => $class]) }}
>
    @if($emptyOption)
        <option value="">{{ $emptyOption }}</option>
    @endif
    @foreach (config('ikas.price_types', ['TL']) as $type)
        <option value="{{ $type }}" @selected($value == $type)>{{ $type }}</option>
    @endforeach
</select>
