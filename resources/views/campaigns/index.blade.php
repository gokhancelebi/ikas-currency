<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('campaigns.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between">
                        <h1 class="text-2xl font-bold">{{ __('campaigns.title') }}</h1>
                        <div class="flex">
                            {{--                            <a href="{{ route('campaigns.create') }}"--}}
                            {{--                               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Kampanya Oluştur</a>--}}
                        </div>
                    </div>

                    <table class="products-table table-auto w-full mt-4">
                        <thead>
                        <tr>
                            <th class="px-4 py-2">
                                {{ __('common.is_active') }}
                            </th>
                            <th class="px-4 py-2">{{ __('common.collection_name') }}</th>
                            <th class="px-4 py-2">{{ __('common.discount_rate') }}</th>
                            <th class="px-4 py-2">{{ __('common.profit_rate') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($collections as $collection)
                            <tr data-collection-id="{{$collection->shopify_collection_id}}" class="hover:bg-gray-200 px-4 py-2">
                                <td class="border px-4 py-2">
                                    <select name="active" id="active" class="active w-full">
                                        <option value="active" @if($collection->active == 'actice') selected @endif>
                                            {{ __('common.active') }}
                                        </option>
                                        <option value="passive" @if($collection->active == 'passive') selected @endif>
                                            {{ __('common.inactive') }}
                                        </option>
                                    </select>
                                </td>
                                <td class="border px-4 py-2">
                                    {{$collection->name}}
                                </td>
                                <td class="border px-4 py-2">
                                    <input class="px-4 py-2 border text-center" value="{{$collection->discount}}"
                                           name="discount">
                                </td>
                                <td class="border px-4 py-2">
                                    <input class="px-4 py-2 border text-center" value="{{$collection->profit}}"
                                           name="profit">
                                </td>
                                <td>
                                    <button
                                        data-update-url="{{route('campaigns.update', $collection->id)}}"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded update-collection">
                                        {{ __('common.update') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="alert-container">

    </div>

    <style>

        tr td {
            text-align: center;
        }

        .alert-container {
            position: fixed;
            bottom: 0;
            left: 20px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
        }

        .alert-container div.success {
            background: rgb(22 163 74);
        }

        .alert-container div.error {
            background: rgb(255 0 0);
        }

        .alert-container div {
            border-radius: 5px;
            padding: 10px;
            width: 500px;
            color: #fff;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script>
        $(function () {
            $('.update-collection').click(function () {
                let collectionId = $(this).closest('tr').data('collection-id');
                let discount = $(this).closest('tr').find('input[name="discount"]').val();
                let profit = $(this).closest('tr').find('input[name="profit"]').val();
                let active = $(this).closest('tr').find('select[name="active"]').val();

                $.ajax({
                    url: $(this).data('update-url'),
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'PUT',
                        shopify_collection_id: collectionId,
                        discount: discount,
                        profit: profit,
                        active: active
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            let alertElement = document.createElement('div');
                            alertElement.classList.add('alert', 'success');
                            alertElement.innerHTML = response.msg;
                            document.querySelector('.alert-container').appendChild(alertElement);
                            setTimeout(function () {
                                alertElement.remove();
                            }, 1800);
                        } else {
                            let alertElement = document.createElement('div');
                            alertElement.classList.add('alert', 'error');
                            alertElement.innerHTML = response.msg;
                            document.querySelector('.alert-container').appendChild(alertElement);
                            setTimeout(function () {
                                alertElement.remove();
                            }, 1800);
                        }
                    }
                });
            });
        });
    </script>
</x-app-layout>
