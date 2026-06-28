@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }
</style>
@endsection
@section('content')
<div class="money-flow-dark">
{{-- Table Component ['href of Add Button'] & ['The Title Of The Table'] --}}
<x-table :href="$company ? route('user.create', [$company->id]) : route('user.create') " :tableTitle="__('Users Table')">
    {{-- Head Of The Table --}}
    @slot('table_header')
    <tr class="table-standard-color">
        <th>{{ __('Avatar') }}</th>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Role') }}</th>
        <th>{{ __('Companies') }}</th>
        <th>{{ __('Controll') }}</th>
    </tr>
    @endslot
    {{-- Body Of The Table --}}
    @slot('table_body')
    @foreach ($users as $item)

    <tr class="text-center">
        <td><img class="index-img" width="100" height="100" src="{{ $item->getFirstMediaUrl() !== '' ? $item->getFirstMediaUrl() :  url('images/user.png') }}" alt="image"></td>
        <td>{{ $item->name }}</td>
        <td>{{ $item->roles[0]->name ?? '-' }}</td>
        <td>
            @foreach ($item->companies as $userCompany)
            {{ $userCompany->name[$lang] }} ,
            @endforeach
        </td>
        <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
            <span style="overflow: visible; position: relative; width: 110px;">
	
                <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ $company ? route('user.edit', [$item, $company->id]) : route('user.edit', $item) }}"><i class="fa fa-pen-alt"></i></a>
                <a class="btn btn-secondary btn-outline-hover-danger btn-icon remove-item-class remove-user-class" data-id="{{ $item->id }}" title="Delete"><i class="fa fa-trash-alt"></i>
                </a>

                <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ $company ? route('user.permissions.edit', [$item->id, $company->id]) : route('user.permissions.edit', $item->id) }}"><i class="fa fa-eye"></i></a>
         
                {{-- <a href="#" type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon"></a> --}}

            </span>
        </td>
    </tr>
    @endforeach
    @endslot
</x-table>
</div>
@endsection

@section('js')
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
</script>
<script>
    $('.remove-user-class').on('click', function(e) {
        e.preventDefault();

        Swal.fire({
            icon: 'warning'
            , title: '{{ __("Warning") }}'
            , showConfirmButton: true
            , showCancelButton: true
            , cancelButtonText: '{{ __("Cancel") }}'
            , text: '{{ __("Are You Sure To Delete This Users ") }}'
        , }).then(() => {
            let user_id = $(this).data('id');
            $.ajax({
                type: 'post'
                , url: "{{ route('remove.user') }}"
                , data: {
                    '_token': "{{csrf_token()}}"
                    , 'user_id': user_id
                , }
                , success: function(data) {
                    if (data.status) {
                        Swal.fire({
                            position: 'top-end'
                            , icon: 'success'
                            , title: "{{ __('User Has Been Removed Successfully') }}"
                            , showConfirmButton: false
                            , timer: 1500
                        }).then(function() {
                            window.location.reload();

                        })
                    }
                }
                , error: function(reject) {}
            });

        })


    });

</script>
@endsection
