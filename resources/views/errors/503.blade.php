<x-public-layout :title="__('errors.503.title')">
    @include('errors.partials.body', [
        'code' => '503',
        'heading' => __('errors.503.heading'),
        'message' => __('errors.503.message'),
    ])
</x-public-layout>
