<x-public-layout :title="__('errors.419.title')">
    @include('errors.partials.body', [
        'code' => '419',
        'heading' => __('errors.419.heading'),
        'message' => __('errors.419.message'),
        'showRefresh' => true,
    ])
</x-public-layout>
