<x-public-layout :title="__('errors.500.title')">
    @include('errors.partials.body', [
        'code' => '500',
        'heading' => __('errors.500.heading'),
        'message' => __('errors.500.message'),
    ])
</x-public-layout>
