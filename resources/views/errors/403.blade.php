<x-public-layout :title="__('errors.403.title')">
    @include('errors.partials.body', [
        'code' => '403',
        'heading' => __('errors.403.heading'),
        'message' => __('errors.403.message'),
    ])
</x-public-layout>
