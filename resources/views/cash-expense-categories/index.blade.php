@extends('layouts.dashboard')

@section('css')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.3/r-2.3.0/rg-1.2.0/sl-1.4.0/sr-1.1.1/datatables.min.css" />

<style>
    .max-w-20 {
        width: 200px !important;
        min-width: 200px !important;
        max-width: 200px !important;
    }

    .main-td-background {
        background-color: #0742A6;
        color: white;
    }

    #test_filter {
        display: none !important;
    }



    .kt-portlet__body {
        padding-top: 0 !important;
    }

    .sub-item-row,
    table.dataTable tbody tr.second-tr-bg>.dtfc-fixed-left,
    table.dataTable tbody tr.second-tr-bg.sub-item-row>.dtfc-fixed-right {}

    .bg-last-row,
    .bg-last-row td,
    .bg-last-row th {
        background-color: #F2F2F2 !important;
        color: black !important;
        border: 1px solid white !important;
    }

    .first-tr,
    .first-tr td,
    .first-tr th {
        background-color: #9FC9FB !important;
    }

    .sub-item-row,
    table.dataTable tbody tr.second-tr-bg>.dtfc-fixed-left,
    table.dataTable tbody tr.second-tr-bg.sub-item-row>.dtfc-fixed-right {
        color: black !important;
    }

    .sub-item-row td {
        color: black !important;
        border: 1px solid white !important;
    }

  

    .main-row-tr td {
        border: 1px solid #CCE2FD !important;

    }

    .first-tr-bg,
    .first-tr-bg td,
    .first-tr-bg th {
        background-color: #074FA4 !important;
        color: white !important;
    }

    .second-tr-bg,
    .second-tr-bg td,
    .second-tr-bg th {
        background-color: white !important;
        color: black !important;
        padding: 3px !important;
        border: none !important;
    }

    .second-tr-bg.second-tr-bg-more-padding,
    .second-tr-bg.second-tr-bg-more-padding td,
    .second-tr-bg.second-tr-bg-more-padding th {
        padding: 7px !important;

    }



    body .table-active,
    .table-active>th,
    .table-active>td {
        background-color: white !important
    }

    #DataTables_Table_0_filter {
        float: left !important;
    }

    div.dt-buttons {
        float: right !important;
    }

    body table.dataTable tbody tr.group-color>.dtfc-fixed-left,
    table.dataTable tbody tr.group-color>.dtfc-fixed-right {
        background-color: white !important;
    }

    .text-capitalize {
        text-transform: capitalize;
    }

    .placeholder-light-gray::placeholder {
        color: lightgrey;
    }

    .kt-header-menu-wrapper {
        margin-left: 0 !important;
    }

    .kt-header-menu-wrapper .kt-header-menu .kt-menu__nav>.kt-menu__item>.kt-menu__link {
        padding: 0.60rem 1.25rem !important;
    }

    .max-w-22 {
        max-width: 22%;
    }

    .form-label {
        white-space: nowrap !important;
    }

    .visibility-hidden {
        visibility: hidden !important;
    }

    input.form-control[readonly] {
        background-color: #F7F8FA !important;
        font-weight: bold !important;

    }

    .three-dots-parent {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 0 !important;
        margin-top: 10px;

    }

    .blue-select {
        border-color: #7096f6 !important;
    }

    .div-for-percentage {
        flex-wrap: nowrap !important;
    }

    b {
        white-space: nowrap;
    }

    i.target_last_value {
        margin-left: -60px;
    }

    .total-tr {
        background-color: #074FA4 !important
    }

    .table-striped th,
    .table-striped2 th {
        background-color: #074FA4 !important
    }

    .total-tr td {
        color: white !important;
    }

    .total-tr .three-dots-parent {
        margin-top: 0 !important;
    }

</style>


<style>
    table.dataTable thead tr>.dtfc-fixed-left,
    table.dataTable thead tr>.dtfc-fixed-right {
        background-color: #086691;
    }

    .dataTables_wrapper .dataTable th,
    .dataTables_wrapper .dataTable td {
        font-weight: bold;
        color: black;
    }

    table.dataTable tbody tr.group-color>.dtfc-fixed-left,
    table.dataTable tbody tr.group-color>.dtfc-fixed-right {
        background-color: white !important;
    }


    .dataTables_wrapper .dataTable th,
    .dataTables_wrapper .dataTable td {
        color: black;
        font-weight: bold;
    }

    thead * {
        text-align: center !important;
    }

</style>
<style>
    td.details-control {
        background: url('{{asset('tables_imgs/details_open.png')}}') no-repeat center center;
        cursor: pointer;
    }

    tr.shown td.details-control {
        background: url('{{asset('tables_imgs/details_close.png')}}') no-repeat center center;
    }

</style>
@endsection

@section('sub-header')
<x-main-form-title :id="'main-form-title'" :class="''"> {{ __('Cash Expense Categories') }} </x-main-form-title>
@endsection

@section('content')
<div class="money-flow-dark">


<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !Request('active')  ?'active':'' }}" data-toggle="tab" href="#running" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Cash Expense Categories') }}
                    </a>
                </li>


            </ul>

            <div class="flex-tabs">

                <a href="{{ route('cash.expense.category.create',['company'=>$company->id]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Create') }}
                </a>
            </div>

        </div>
    </div>


 

            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') ?'active':'' }}" id="{{ 'running' }}" role="tabpanel">
               


                        <x-table :tableClass="'kt_table_with_no_pagination_no_fixed  removeGlobalStyle ' ">
                            @slot('table_header')


                            <tr class=" text-center second-tr-bg">
                                <th class="text-center absorbing-column "></th>
                                <th></th>
                            </tr>
                            @endslot
                            @slot('table_body')
                            <tr class=" text-center first-tr-bg ">
                                <td class=" text-center view-table-th max-w-20"><b style="color:white !important" class="text-capitalize">{{ __('Name') }}</b></td>


                                <td style="color:white !important" class="text-center view-table-th ">
                                    {{ __('Actions') }}
                                </td>
                            </tr>
                            @php
                            $id = 0 ;
                            @endphp
                            @foreach($items as $mainItemId => $parnetAndSubData )
                            @php
                            $parent =$parnetAndSubData['parent'] ;
                            $subItems =$parnetAndSubData['sub_items'] ?? [];

                            @endphp
                            <tr class="group-color main-row-tr">



                                <td class="black-text " style="cursor: pointer;" onclick="toggleRow('{{ $mainItemId }}')">

                                    <div class="d-flex align-items-center ">
                                        @if(count($subItems))
                                        <i class="row_icon{{ $mainItemId }} flaticon2-up  mr-2  "></i>
                                        @endif
                                        <b class="text-capitalize ">{{ $parent['name'] }}</b>
                                    </div>
                                </td>






                                <td class="text-left text-capitalize">





                                    <b class="ml-3">

                                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('cash.expense.category.edit', ['company'=>$company->id , 'cashExpenseCategory'=>$mainItemId]) }}"><i class="fa fa-pen-alt"></i></a>
                                        <a class="btn btn-secondary btn-outline-hover-danger btn-icon  " href="#" data-toggle="modal" data-target="#modal-delete-{{ $mainItemId }}" title="Delete"><i class="fa fa-trash-alt"></i>
                                        </a>

                                        <div id="modal-delete-{{ $mainItemId }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">{{ __('Delete Cash Expense Category ' .$parent['name']) }}</h4>
                                                    </div>
                                                    <div class="modal-body">
                                                        <h3>{{ __('Are You Sure To Delete This Item ? ') }}</h3>
                                                    </div>
                                                    <form action="{{ route('cash.expense.category.destroy',['company'=>$company->id , 'cashExpenseCategory'=> $mainItemId ]) }}" method="post" id="delete_form">
                                                        {{ csrf_field() }}
                                                        {{ method_field('DELETE') }}
                                                        <div class="modal-footer">
                                                            <button class="btn btn-danger">
                                                                {{ __('Delete') }}
                                                            </button>
                                                            <button class="btn btn-secondary" data-dismiss="modal" aria-hidden="true">
                                                                {{ __('Close') }}
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        </span>
                                    </b>
                                </td>








                            </tr>

                            @foreach ($subItems as $subItemId => $titleAndValue)



                            <tr class="row{{ $mainItemId }}  text-center sub-item-row" style="display: none">
                                <td colspan="5" class="text-left  text-capitalize">
                                    <table class="table ml-3 table-borderless">

                                        <tr>

                                            <td class="max-w-20">{{ __('Name') }}</td>
                                            <td>{{ $titleAndValue['name'] }}</td>

                                        </tr>

                                    </table>
                                </td>









                            </tr>

                            @endforeach


                            @php $id++ ;@endphp
                            @endforeach





                            @endslot
                        </x-table>


            </div>
      



</div>






</div>
@endsection

@section('js')
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
</script>

<script>
    $(document).on('click', '#delete-btn', function() {
        $('#delete_form').attr('action', $(this).data('url'));
    });

    function toggleRow(rowNum) {
        $(".row" + rowNum).toggle();
        $('.row_icon' + rowNum).toggleClass("flaticon2-down flaticon2-up");
        $(".row2" + rowNum).hide();
    }

</script>
<script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.22/datatables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>

@endsection
