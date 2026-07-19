<x-public-layout :title="__('errors.429.title')">
    @include('errors.partials.body', [
        'code' => '429',
        'heading' => __('errors.429.heading'),
        'message' => __('errors.429.message'),
    ])
</x-public-layout>
