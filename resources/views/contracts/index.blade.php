@extends('layouts.dashboard')
@php
use App\Models\Contract;
@endphp
@section('css')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.3/r-2.3.0/rg-1.2.0/sl-1.4.0/sr-1.1.1/datatables.min.css" />
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .w-60-percentage {
        width: 60% !important;
    }

    .action-class {
        min-width: 10px !important;
        max-width: 10px !important;
        width: 10px !important;
        background-color: #0742A6 !important;
        color: white !important;

    }

    .w-50-percentage {
        width: 50% !important;
    }

    .w-40-percentage {
        width: 40% !important;
    }

    .w-30-percentage {
        width: 30% !important;
    }

    .w-20-percentage {
        width: 20% !important;
    }

    .w-70-percentage {
        width: 70% !important;
    }

    .w-15-percentage {
        width: 15% !important;
    }

    .w-10-percentage {
        width: 10% !important;
    }

    .flex-tabs {
        display: flex;
        gap: 10px;
    }

    .text-green {
        color: green !important;
    }

    .text-red {
        color: red !important;
    }

    .show-class-js {
        display: block !important;
    }

    .table-condensed th {
        background-color: white !important;
    }

    input,
    select,
    .dropdown-toggle.bs-placeholder {
        border: 1px solid #CCE2FD !important;
    }

    .flex-2 {
        flex: 2 !important;
    }

    .text-main-color {
        color: #0742A6 !important
    }

    ::placeholder {
        color: lightgray !important;
        font-weight: 100;
    }

    .visibility-hidden {
        visibility: hidden !important;
    }

    .income-statement-table {}

    .btn-border-radius {
        border-radius: 10px !important;
    }

    .income-statement-table .main-level-tr td,
    .income-statement-table .main-level-tr th {
        background-color: #9FC9FB !important;
        border: 1px solid #fff;

    }

    .income-statement-table .main-level-tr td:first-of-type,
    .income-statement-table .main-level-tr td:nth-of-type(2),
    .income-statement-table .main-level-tr th:first-of-type,
    .income-statement-table .main-level-tr th:nth-of-type(2) {
        background-color: #9FC9FB !important;
    }

    .income-statement-table .sub-level-tr td,
    .income-statement-table .sub-level-tr th {
        background-color: #fff !important;
    }

    input,
    select,
    .filter-option-inner-inner {
        font-weight: 600 !important;
        color: black !important;
    }

    html body tr.all-td-white td {
        background-color: white !important;
    }

    .font-size-1-25rem {
        font-size: 1.25rem !important;
    }

    .font-size-15px {
        font-size: 15px !important
    }

    .label-clr {
        color: #646c9a !important;
    }

    .installment-section {
        background: #F2F2F2 !important;
        padding-top: 10px;
        margin-bottom: 10px !important;
    }

    .label-size {
        font-size: 1.25rem !important;
    }

    .pr-6rem {
        padding-right: 6rem;
    }

    .pointer-events-none {
        pointer-events: none;
    }

    .dtfh-floatingparent.dtfh-floatingparenthead {
        top: 59px !important;
    }

    .table-for-collection-policy tr:nth-child(odd) {
        background-color: white !important;
    }

    .percentage-weight {
        font-weight: bold;
        margin-right: 10px;
    }





    .small-caps {
        font-variant: small-caps;
    }

    .sharing-sign {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: auto;
    }

    .active-sharing {
        background: #00ff28;
    }

    .inactive-sharing {
        background: #f00;
    }

    .w-full {
        width: 100%;
    }

    .btn.dropdown-toggle {
        height: 100%;
    }

    /* .dropdown-toggle{} */

</style>

<style>
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

    .max-w-25 {
        width: 20% !important;
        min-width: 20% !important;
        max-width: 20% !important;
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ $customerOrSupplierContractsText }}</x-main-form-title>
@endsection

@section('content')
<div class="money-flow-dark">
    <div class="kt-portlet kt-portlet--tabs">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
                <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                    <li class="nav-item">
                        <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == Contract::RUNNING ?'active':'' }}" data-toggle="tab" href="#{{Contract::RUNNING  }}" role="tab">
                            <i class="fa fa-money-check-alt"></i> {{ __('Running') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a onclick="return false" class="nav-link {{ Request('active') == Contract::RUNNING_AND_AGAINST ?'active':'' }}" data-toggle="tab" href="#{{ Contract::RUNNING_AND_AGAINST }}" role="tab">
                            <i class="fa fa-money-check-alt"></i> {{ __('Running And Against') }}
                        </a>
                    </li>


                    <li class="nav-item">
                        <a onclick="return false" class="nav-link {{ Request('active') == Contract::FINISHED ?'active':'' }}" data-toggle="tab" href="#{{ Contract::FINISHED }}" role="tab">
                            <i class="fa fa-check-double"></i> {{ __('Finished') }}
                        </a>
                    </li>



                </ul>
                @if(hasAuthFor('view '. str_plural(strtolower($type)) .' contracts'))
                <div class="flex-tabs">

                    <a href="{{ route('contracts.create',['company'=>$company->id,'type'=>$type]) }}" class="btn  active-style btn-icon-sm align-self-center">
                        <i class="fas fa-plus"></i>
                        {{ __('Create') }}
                    </a>
                </div>
                @endif

            </div>
        </div>


        <div class="kt-portlet__bod">
            <div class="tab-content  kt-margin-t-20">
                @foreach($contractStatues as $contractStatus)
                @php
                $currentType = $contractStatus ;
                @endphp
                <!--Begin:: Tab Content-->
                <div class="tab-pane {{ !Request('active') && $contractStatus == Contract::RUNNING || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                    <div class="kt-portlet2 kt-portlet--mobile">

                        <div class="kt-portlet__bod">


                            <x-table :tableClass="'kt_table_with_no_pagination_no_fixed  removeGlobalStyle ' ">
                                @slot('table_header')


                                <tr class=" text-center second-tr-bg">
                                    <th class="text-center absorbing-column "></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                                @endslot
                                @slot('table_body')
                                <tr class=" text-center first-tr-bg ">
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('Partner Name') }}</b></td>
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('Contract Name') }}</b></td>
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('Contract Code') }}</b></td>
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('Start Date') }}</b></td>
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('End Date') }}</b></td>
                                    <td class=" text-center view-table-th"><b style="color:white !important" class="text-capitalize">{{ __('Amount') }}</b></td>


                                    <td style="color:white !important" class="text-center view-table-th ">
                                        {{ __('Actions') }}
                                    </td>
                                </tr>
                                @php

                                $i=0;
                                @endphp
                                @foreach($items[$currentType]??[] as $mainItemId => $parnetAndSubData )
                                @php

                                $parent =$parnetAndSubData['parent'] ;
                                $subItems =$parnetAndSubData['sub_items'] ?? [];
                                $contract = $parent['contract'] ;

                                @endphp
                                <tr class="group-color main-row-tr closest-parent-tr">



                                    <td class="black-text " style="cursor: pointer;" onclick="toggleRow('{{ $mainItemId }}')">

                                        <div class="d-flex align-items-center ">
                                            @if(count($subItems))
                                            <i class="row_icon{{ $mainItemId }} flaticon2-up  mr-2  "></i>
                                            @endif
                                            <b class="text-capitalize ">
                                                <b class="text-capitalize text-wrap">{{ $parent['client_name'] }}</b>
                                            </b>

                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <b class="text-capitalize ">

                                            <b class="text-capitalize text-wrap">{{ $parent['name'] }}</b>
                                        </b>

                                    </td>
                                    <td class="text-center">
                                        <b class="text-capitalize ">{{ $parent['contract_code'] }}</b>

                                    </td>
                                    <td class="text-center">
                                        <b class="text-capitalize  ">
                                            <b class="text-capitalize ">{{ $parent['start_date'] }}</b>
                                        </b>


                                    </td>
                                    <td class="text-center">
                                        <b class="text-capitalize">
                                            <b class="text-capitalize">{{ $parent['end_date'] }}</b>
                                        </b>


                                    </td>
                                    <td class="text-center">
                                        <b class="text-capitalize  ">
                                            <b class="text-capitalize">{{ $parent['amount'] .' '. $parent['currency'] }}</b>
                                        </b>

                                    </td>


                                    <td class="text-left text-capitalize">





                                        <b class="ml-3">
                                            @if($type == 'Customer')
                                            {{-- <button  type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon"><i class="flaticon2-copy"></i> </button> --}}
                                            {{-- <button data-toggle="modal" data-target="#details_model{{ $contract->id }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon"><i class="fa fa-eye"></i> </button> --}}
                                            {{-- <a href="{{ str_replace('?','#',route('contracts.edit',['company'=>$company->id,'contract'=>$contract->id ,'type'=>$type,'connecting'])) }}" title="{{ __('Connecting With Supplier Contracts') }}" class="btn btn-secondary btn-outline-hover-brand btn-icon"><i class="fa fa-link"></i> </a> --}}
                                            @endif
                                            <span style="overflow: visible; position: relative; width: 110px;">
                                                @php
                                                $currentModelId = 'contract-invoice-details-'.$mainItemId ;
                                                @endphp
                                                @if($hasProjectNameColumn)
                                                <button class="btn btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModelId }}">{{ __('Invoices') }}</button>
                                                @include('contracts.contract-invoice-details',['modalId'=>$currentModelId,'detailItems'=>$parent['invoices']])
                                                @endif

                                                @if($currentType == Contract::RUNNING )
                                                <a data-toggle="modal" data-target="#mark-as-finished-contract-{{ $mainItemId }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Finished') }}" href="#"><i class="fa fa-check-double"></i></a>
                                                <div class="modal fade" id="mark-as-finished-contract-{{ $mainItemId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <form action="{{ route('contract.mark.as.finished', ['company'=>$company->id , 'contract'=>$mainItemId,'type'=>$type]) }}" method="post">
                                                                @csrf
                                                                @method('put')
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Mark This Contract As Finished ?') }}</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                    <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                                                                </div>

                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif
                                                @if($currentType == Contract::RUNNING_AND_AGAINST )
                                                @if(hasAuthFor('update '. str_plural(strtolower($type)) .' contracts'))
                                                <a data-toggle="modal" data-target="#mark-as-finished-contract-{{ $mainItemId }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Finished') }}" href="#"><i class="fa fa-thumbs-up"></i></a>
                                                <div class="modal fade" id="mark-as-finished-contract-{{ $mainItemId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <form action="{{ route('contract.mark.as.finished', ['company'=>$company->id , 'contract'=>$mainItemId,'type'=>$type]) }}" method="post">
                                                                @csrf
                                                                @method('put')
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Mark This Contract As Finished ?') }}</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                    <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                                                                </div>

                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif
                                                @elseif($currentType == Contract::FINISHED)
                                                @if(hasAuthFor('update '. str_plural(strtolower($type)) .' contracts'))
                                                <a data-toggle="modal" data-target="#mark-as-running-and-against-contract-{{ $mainItemId }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Running And Against') }}" href="#"><i class="fa fa-thumbs-up"></i></a>
                                                <div class="modal fade" id="mark-as-running-and-against-contract-{{ $mainItemId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <form action="{{ route('contract.mark.as.running.and.against', ['company'=>$company->id , 'contract'=>$mainItemId,'type'=>$type]) }}" method="post">
                                                                @csrf
                                                                @method('put')
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Mark This Contract As Running And Against ?') }}</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                    <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                                                                </div>

                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif

                                                @endif
                                                @if(hasAuthFor('update '. str_plural(strtolower($type)) .' contracts'))
                                                <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('contracts.edit', ['company'=>$company->id , 'contract'=>$mainItemId,'type'=>$type]) }}"><i class="fa fa-pen-alt"></i></a>
                                                @endif
                                                @if(hasAuthFor('delete '. str_plural(strtolower($type)) .' contracts'))
                                                <a class="btn btn-secondary btn-outline-hover-danger btn-icon  " href="#" data-toggle="modal" data-target="#modal-delete-{{ $mainItemId }}" title="Delete"><i class="fa fa-trash-alt"></i>
                                                </a>

                                                <div id="modal-delete-{{ $mainItemId }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">{{ __('Delete Contract ' .$parent['name']) }}</h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <h3>{{ __('Are You Sure To Delete This Item ? ') }}</h3>
                                                            </div>
                                                            <form action="{{ route('contracts.destroy',['company'=>$company->id , 'contract'=> $mainItemId,'type'=>$type ]) }}" method="post" id="delete_form">
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
                                                @endif

                                            </span>
                                        </b>
                                    </td>






                                </tr>

                                @foreach ($subItems as $subItemId => $titleAndValue)



                                <tr class="row{{ $mainItemId }}  text-center sub-item-row" style="display: none">
                                    <td colspan="5" class="text-left  text-capitalize">
                                        <table class="table ml-3 table-borderless">


                                            <tr class="total-amount" data-value="{{ number_unformat($titleAndValue['amount']) }}">
                                                @if(isset($titleAndValue['so_number'] ))
                                                <td>{{ __('Sales Order Number') }}</td>
                                                <td>{{ $titleAndValue['so_number'] }}</td>
                                                @elseif(isset($titleAndValue['po_number'] ))
                                                <td>{{ __('Purchase Order Number') }}</td>
                                                <td>{{ $titleAndValue['po_number'] }}</td>

                                                @endif

                                                <td>{{ __('Amount') }}</td>
                                                <td>{{ $titleAndValue['amount']  }}</td>


                                            </tr>

                                        </table>




                                    </td>

                                    <td>
                                    </td>
                                    <td class="text-center ">

                                        <form action="{{ route('store.po.allocations',['company'=>$company->id]) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="po_id" value="{{ $titleAndValue['id'] }}">
                                            @if($type == 'Supplier')
                                            <button type="button" class="add-new btn btn-primary d-block" data-toggle="modal" data-target="#allocate-po-{{ $titleAndValue['id'] }}">
                                                {{ __('Allocate') }}

                                            </button>

                                            <div class="modal fade modal-class-js allocate-modal-class" id="allocate-po-{{ $titleAndValue['id'] }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-90 modal-xl" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLabel">{{ __('Allocate') }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">

                                                            <div class="form-group row justify-content-center">
                                                                @php
                                                                $index = 0 ;
                                                                @endphp

                                                                @php
                                                                $tableId = 'poAllocations';

                                                                $repeaterId = 'm_repeater_inner';

                                                                @endphp
                                                                <x-tables.repeater-table :hideAddBtn="true" :initialJs="false" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                                                    <x-slot name="ths">
                                                                        @foreach([
                                                                        __('Customer')=>'th-main-color max-w-25',
                                                                        __('Contract Name')=>'th-main-color max-w-25',
                                                                        __('Code')=>'th-main-color ',
                                                                        __('Amount')=>'th-main-color',
                                                                        __('Allocate <br> Percentage')=>'th-main-color',
                                                                        __('Allocate <br> Amount')=>'th-main-color',
                                                                        ] as $title=>$classes)
                                                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                                                        @endforeach
                                                                    </x-slot>
                                                                    <x-slot name="trs">
                                                                        @php
                                                                        $rows = isset($titleAndValue['allocations']) ? $titleAndValue['allocations'] : [-1] ;
                                                                        @endphp
                                                                        @foreach( count($rows) ? $rows : [-1] as $poAllocation)
                                                                        @php
                                                                        $fullPath = new \App\Models\PoAllocation;
                                                                        if( !($poAllocation instanceof $fullPath) ){
                                                                        unset($poAllocation);
                                                                        }
                                                                        @endphp
                                <tr class="closest-parent-tr" @if($isRepeater) data-repeater-item @endif>

                                    <td class="text-center">

                                        <input type="hidden" name="company_id" value="{{ $company->id }}">

                                        <div class="">
                                            <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                            </i>
                                        </div>
                                    </td>
                                    <td>

                                        <x-form.select :insideModalWithJs="false" :selectedValue="isset($poAllocation) && $poAllocation->partner_id ? $poAllocation->partner_id : ''" :options="formatOptionsForSelect($clientsWithContracts)" :add-new="false" class=" suppliers-or-customers-js " data-filter-type="{{ 'create' }}" :all="false" data-name="partner_id" name="partner_id"></x-form.select>
                                    </td>

                                    <td>
                                        <x-form.select :insideModalWithJs="false" data-current-selected="{{ isset($poAllocation) ? $poAllocation->contract_id : '' }}" :selectedValue="isset($poAllocation) ? $poAllocation->contract_id : ''" :options="[]" :add-new="false" class=" contracts-js   " data-filter-type="{{ 'create' }}" :all="false" data-name="contract_id" name="contract_id"></x-form.select>
                                        {{-- <div class="max-w-25">
																					</div> --}}
                                    </td>

                                    <td>
                                        <div class="kt-input-icon ">
                                            <div class="input-group">
                                                <input disabled type="text" class="form-control contract-code " value="">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="kt-input-icon ">
                                            <div class="input-group">
                                                <input disabled type="text" class="form-control contract-amount" value="0">
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="kt-input-icon ">
                                            <div class="input-group">
                                                <input type="text" data-name="allocation_percentage" name="allocation_percentage" class="form-control allocation-percentage-class" value="{{ isset($poAllocation) ? number_format($poAllocation->getPercentage(),2): 0 }}">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="kt-input-icon ">
                                            <div class="input-group">
                                                <input type="text" readonly data-name="allocation_amount" name="allocation_amount" class="form-control allocation-amount-class" value="{{ isset($poAllocation) ? number_format($poAllocation->getAmount(),2): 0 }}">
                                            </div>
                                        </div>
                                    </td>


                                </tr>



                                @endforeach

                                </x-slot>




                                </x-tables.repeater-table>
                                {{-- end of fixed monthly repeating amount --}}


                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary ">{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>
        @endif


        </form>
        </td>






        </tr>

        @endforeach


        @endforeach





        @endslot
        </x-table>


    </div>
</div>
</div>
@endforeach
</div>
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

<!--begin::Page Scripts(used by this page) -->
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
<script>

</script>

{{-- <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.22/datatables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script> --}}


<script src="{{asset('assets/form-repeater.js')}}" type="text/javascript"></script>

<script>
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })

</script>
<script>
    $(document).on('change', '.allocation-percentage-class', function() {
        let percentage = $(this).val()
        percentage = percentage ? percentage : 0;
        percentage = percentage / 100;
        const parent = $(this).closest('.sub-item-row');
        let amount = number_unformat($(parent).find('.total-amount').attr('data-value'));
        amount = amount ? amount : 0;
        $(this).closest('tr').find('.allocation-amount-class').val(Math.round(percentage * amount, 2))

    });
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency') ? $(this).find('option:selected').data('currency').toUpperCase() : null;
        if (currency) {
            $(parent).find('.contract-code').val(code)
            $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)
        }

    })

    $(document).on('change', 'select.suppliers-or-customers-js', function() {
        const parent = $(this).closest('.closest-parent-tr')
        const partnerId = parseInt($(this).val())
        const model = 'Customer'
        let inEditMode = 0;

        $.ajax({
            url: "{{ route('get.contracts.for.customer.or.supplier',['company'=>$company->id]) }}"
            , data: {
                partnerId
                , model
                , inEditMode
            }
            , type: "get"
            , success: function(res) {
                let contracts = '';
                const currentSelected = $(parent).find('select.contracts-js').data('current-selected')
                for (var contract of res.contracts) {
                    contracts += `<option ${currentSelected ==contract.id ? 'selected' :'' } value="${contract.id}" data-code="${contract.code}" data-amount="${contract.amount}" data-start-date="${contract.start_date}" data-end-date="${contract.end_date}" data-currency="${contract.currency}" >${contract.name}</option>`;
                }
                parent.find('select.contracts-js').empty().append(contracts).trigger('change')
            }
        })
    })
    $(function() {
        $('select.suppliers-or-customers-js').trigger('change')
    })

</script>
@endsection
