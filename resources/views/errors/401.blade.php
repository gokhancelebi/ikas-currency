<x-public-layout :title="__('errors.401.title')">
    @include('errors.partials.body', [
        'code' => '401',
        'heading' => __('errors.401.heading'),
        'message' => __('errors.401.message'),
    ])
</x-public-layout>
