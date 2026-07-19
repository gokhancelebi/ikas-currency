@if (! app()->isProduction() || config('app.debug'))
    <div
        role="alert"
        class="bg-amber-500 text-amber-950 text-center text-sm font-semibold py-2 px-4 border-b border-amber-600"
    >
        {{ __('environment.warning') }}
        <span class="font-normal">({{ config('app.env') }}@if (config('app.debug')) · {{ __('environment.debug') }} @endif)</span>
    </div>
@endif
