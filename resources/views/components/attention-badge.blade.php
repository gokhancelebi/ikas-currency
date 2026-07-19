@props(['reasons' => []])

@if(count($reasons) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1 mt-1']) }}>
        @foreach ($reasons as $reason)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-900"
                  title="{{ $reason }}">
                {{ $reason }}
            </span>
        @endforeach
    </div>
@endif
