<x-public-layout :title="__('errors.404.title')">
    @include('errors.partials.body', [
        'code' => '404',
        'heading' => __('errors.404.heading'),
        'message' => __('errors.404.message'),
    ])
</x-public-layout>
