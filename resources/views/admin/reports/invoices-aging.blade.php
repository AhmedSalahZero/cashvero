@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>

<style>
    [data-chart-name="Total-Aging-Analysis-Chart"] {
        max-height: 340px !important;
    }

    [data-chart-name="Total-Coming-Dues-Aging-Analysis-Chart"],
    [data-chart-name="Total-Past-Dues-Aging-Analysis-Chart"] {
        max-height: 580px !important;
    }

    .is-sub-row.is-total-row td.sub-numeric-bg,
    .is-sub-row.is-total-row td.sub-text-bg {
        background-color: #087383 !important;
        color: white !important;
    }

    .is-name-cell {
        white-space: normal !important;
    }

    .top-0 {
        top: 0 !important;
    }

    .parent-tr td {
        border: 1px solid #E2EFFE !important;
    }

    .dataTables_filter {
        width: 30% !important;
        text-align: left !important;

    }

    .border-parent {
        border: 2px solid #E2EFFE;
    }

    .dt-buttons.btn-group,
    .buttons-print {
        max-width: 30%;
        margin-left: auto;
        position: relative;
        top: 45px;
    }

    .details-btn {
        display: block;
        margin-top: 10px;
        margin-left: auto;
        margin-right: auto;
        font-weight: 600;

    }

    .expand-all {
        cursor: pointer;
    }

    td.editable-date.max-w-fixed,
    th.editable-date.max-w-fixed,
    input.editable-date.max-w-fixed {
        width: 1050px !important;
        max-width: 1050px !important;
        min-width: 1050px !important;

    }

    td.editable-date.max-w-classes-expand,
    th.editable-date.max-w-classes-expand,
    input.editable-date.max-w-classes-expand {
        width: 70px !important;
        max-width: 70px !important;
        min-width: 70px !important;

    }

    td.max-w-classes-name,
    th.max-w-classes-name,
    input.max-w-classes-name {
        width: 350px !important;
        max-width: 350px !important;
        min-width: 350px !important;

    }

    td.max-w-grand-total,
    th.max-w-grand-total,
    input.max-w-grand-total {
        width: 100px !important;
        max-width: 100px !important;
        min-width: 100px !important;

    }

    * {
        box-sizing: border-box !important;
    }

</style>
@endsection
@section('sub-header')
<x-main-form-title :id="'main-form-title'" :class="''">{{ $customersOrSupplierAgingText }}</x-main-form-title>
@endsection
@section('content')
<div class="money-flow-dark">
@php
$moreThan150=\App\ReadyFunctions\InvoiceAgingService::MORE_THAN_150;
@endphp
<style>
    td.editable-date,
    th.editable-date,
    input.editable-date {
        width: 100px !important;
        min-width: 100px !important;
        max-width: 100px !important;
        overflow: hidden;
    }

    .width-66 {


        width: 66% !important;
    }

    .border-bottom-popup {
        border-bottom: 1px solid #d6d6d6;
        padding-bottom: 20px;
    }

    .flex-self-start {
        align-self: flex-start;
    }

    .flex-checkboxes {
        margin-top: 1rem;
        flex: 1;
        width: 100% !important;
    }


    .flex-checkboxes>div {
        width: 100%;
        width: 100% !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .custom-divs-class {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
    }


    .modal-backdrop {
        display: none !important;
    }

    .modal-content {
        min-width: 600px !important;
    }

    .form-check {
        padding-left: 0 !important;

    }

    .main-with-no-child,
    .main-with-no-child td,
    .main-with-no-child th {
        background-color: #046187 !important;
        color: white !important;
        font-weight: bold;
    }

    .is-sub-row td.sub-numeric-bg,
    .is-sub-row td.sub-text-bg {
        border: 1.5px solid white !important;
        background-color: #0e96cd !important;
        color: white !important;


        color: black !important
    }



    .sub-numeric-bg {
        text-align: center;

    }



    th.dtfc-fixed-left {
        background-color: #074FA4 !important;
        color: white !important;
    }

    .header-tr,
        {
        background-color: #046187 !important;
    }

    .dt-buttons.btn-group {
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
        margin-bottom: 1rem;
    }

    .is-sales-rate,
    .is-sales-rate td,
    .is-sales-growth-rate,
    .is-sales-growth-rate td {
        background-color: #046187 !important;
        color: white !important;
    }

    .dataTables_wrapper .dataTable th,
    .dataTables_wrapper .dataTable td {
        font-weight: bold;
        color: black;
    }

    a[data-toggle="modal"] {
        color: #046187 !important;
    }

    a[data-toggle="modal"].text-white {
        color: white !important;
    }

    .btn-border-radius {
        border-radius: 10px !important;
    }

</style>
<div class="row">
    <div class="col-md-12">

        <div class="kt-portlet kt-portlet--tabs">

            <div class="kt-portlet__head">
                <div class="kt-portlet__head-toolbar">
                    <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" onclick="return false;" data-toggle="tab" href="#kt_apps_contacts_view_tab_1" role="tab">
                                <i class="flaticon2-checking"></i> &nbsp; {{ __('Report Table') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link " onclick="return false;" data-toggle="tab" href="#kt_apps_contacts_view_tab_2" role="tab">
                                <i class="flaticon-line-graph"></i>{{ __('Charts') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>



        {{-- <div class="kt-portlet__body with-scroll"> --}}

        @php

        $tableId = 'kt_table_1';
        @endphp



        @csrf


        @php
        $grandTotal = $agings['grand_total'] ??0
        @endphp
        <div class="tab-content">
            <div class="tab-pane active" id="kt_apps_contacts_view_tab_1" role="tabpanel">
                <div class="kt-portlet">

                    <div class="kt-portlet__body with-scroll">
                        <div class="table-custom-container position-relative  ">



                            <div class="responsive">
                                <table class="table qqq kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                                    <thead>

                                        <tr class="header-tr ">
                                            <th class="view-table-th expand-all is-open-parent header-th editable-date max-w-classes-expand align-middle text-center trigger-child-row-1" rowspan="2">
                                                {{ __('Expand All' ) }}
                                                <span>+</span>
                                            </th>
                                            <th class="view-table-th header-th max-w-classes-name align-middle text-center" class="header-th" rowspan="2">
                                                {{ __('Customer Name') }}

                                            </th>
                                            <th class="view-table-th editable-date header-th max-w-fixed" style="" colspan="{{ count(getInvoiceDayIntervals() )+1 }}"> {{ __('Past Due') }} </th>
                                            <th class="view-table-th align-middle text-center editable-date header-th" rowspan="2">
                                                {{ __('Total Past Due') }}
                                                <button class="btn btn-sm btn-light d-block details-btn" id="show-past-due-detail">{{ __('Show Details') }}</button>
                                            </th>
                                            <th class="view-table-th editable-date header-th">
                                                {{ __('Current Due') }}
                                            </th>
                                            <th colspan="{{ count(getInvoiceDayIntervals() )+1 }}" class="view-table-th header-th">
                                                {{ __('Coming Due ') }}
                                            </th>
                                            <th class="view-table-th align-middle text-center editable-date header-th" rowspan="2">
                                                {{ __('Total Coming Due') }}
                                                <button class="btn btn-sm btn-light d-block details-btn" id="show-coming-due-detail">{{ __('Show Details') }}</button>

                                            </th>

                                            <th class="view-table-th editable-date align-middle text-center header-th max-w-grand-total" rowspan="2">
                                                {{ __('Grand Total') }}
                                            </th>


                                        </tr>


                                        <tr class="header-tr ">


                                            <th class="view-table-th editable-date header-th">{{ $moreThan150 }}
                                                <span class="d-block">{{ __('Days') }}</span>

                                            </th>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder)
                                            <th class="view-table-th editable-date header-th">
                                                <span style="white-space:nowrap !important">[{{ $daysIntervalInInverseOrder }}] {{ __('Days') }}</span>

                                                {{-- @foreach(getDatesFromTwoIndexes($daysIntervalInInverseOrder,$aginDate,'past') as $dateFormatted) --}}
                                                @if(isset($weeksDates['past_due'][$daysIntervalInInverseOrder]['start_date']))
                                                <span class="d-block">{{ $weeksDates['past_due'][$daysIntervalInInverseOrder]['start_date']  }} <br></span>
                                                <span class="d-block">{{ $weeksDates['past_due'][$daysIntervalInInverseOrder]['end_date']  }} <br></span>
                                                @endif
                                                {{-- <span class="d-block">{{ $dateFormatted  }} <br></span> --}}
                                                {{-- @endforeach --}}
                                            </th>
                                            @endforeach

                                            <th class="view-table-th editable-date header-th">
                                                {{ __('At Date') }}
                                                {{ \Carbon\Carbon::make($aginDate)->format('d-m-Y') }}
                                            </th>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval)


                                            <th class="view-table-th header-th">
                                                <span style="white-space:nowrap !important">[{{ $daysInterval }}] {{ __('Days') }}</span>
                                                {{-- @foreach(getDatesFromTwoIndexes($daysIntervalInInverseOrder,$aginDate,'coming') as $dateFormatted) --}}
                                                @if(isset($weeksDates['coming_due'][$daysInterval]['start_date']))
                                                <span class="d-block">{{ $weeksDates['coming_due'][$daysInterval]['start_date']  }} <br></span>
                                                <span class="d-block">{{ $weeksDates['coming_due'][$daysInterval]['end_date']  }} <br></span>
                                                @endif
                                                {{-- @endforeach --}}

                                            </th>

                                            @endforeach
                                            <th class="view-table-th editable-date header-th">
                                                <span class="d-block">{{ $moreThan150 }}</span>
                                                <span class="d-block">{{ __('Days') }}</span>
                                            </th>



                                        </tr>

                                    </thead>
                                    <tbody class="">
                                        <script>
                                            let currentTable = null;

                                        </script>
                                        @php
                                        $rowIndex = 0 ;
                                        @endphp
                                        @foreach($agings as $clientName=> $aging)
                                        @if($clientName == 'total' || $clientName =='grand_total' || $clientName =='total_of_due' || $clientName =='invoice_count' || $clientName=='total_clients_due' || $clientName=='grand_clients_total' || $clientName=='charts')
                                        @continue ;
                                        @endif
                                        @php
                                        $hasSubRows = count($aging['invoices']??[]) ;
                                        $currentTotal = $aging['total'] ?? 0 ;
                                        @endphp
                                        <tr class=" parent-tr  reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   " data-model-id="{{ $rowIndex }}">
                                            <td class="red reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize main-tr is-close"> @if($hasSubRows) + @endif</td>
                                            <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ $clientName }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['past_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            @php
                                            $currentValue = $aging['past_due'][$daysIntervalInInverseOrder] ?? 0 ;
                                            $currentPercentage = $currentValue && $currentTotal ? $currentValue/ $currentTotal * 100 : 0 ;
                                            @endphp
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($currentValue  ,0) }} @if($currentPercentage) @endif </td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['past_due']['total'] ?? 0 ,0) }}</td>

                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['current_due'][0] ?? 0 ,0) }}</td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['coming_due'][$daysInterval] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['coming_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($aging['coming_due']['total'] ?? 0 ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($currentTotal ,0) }}</td>
                                        </tr>




                                        @foreach($aging['invoices'] as $invoiceNumber=>$invoiceDetailArr)
                                        <tr class="edit-info-row add-sub maintable-1-row-class{{ $rowIndex }} is-sub-row d-none">
                                            <td class=" reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize is-close "></td>
                                            <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{ $invoiceNumber }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['past_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['past_due'][$daysIntervalInInverseOrder] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['past_due']['total'] ?? 0 ,0) }}</td>

                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['current_due'][0] ?? 0 ,0) }}</td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['coming_due'][$daysInterval] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['coming_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['coming_due']['total'] ?? 0 ,0) }}</td>
                                            <td class="sub-numeric-bg text-center editable-date">{{ number_format($invoiceDetailArr['total']??0 , 0) }}</td>

                                        </tr>
                                        @endforeach




                                        @php
                                        $rowIndex = $rowIndex+ 1;
                                        @endphp

                                        @endforeach


                                        <tr class="edit-info-row add-sub is-total-row is-sub-row ">
                                            <td class=" reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close "></td>

                                            <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{ __('Total') }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['total']['past_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['total']['past_due'][$daysIntervalInInverseOrder] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                {{ number_format($agings['total_of_due']['past_due']??0) }}
                                            </td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['total']['current_due'][0] ?? 0 ,0) }}</td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['total']['coming_due'][$daysInterval] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['total']['coming_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                {{ number_format($agings['total_of_due']['coming_due']??0) }}
                                            </td>
                                            <td class="sub-numeric-bg text-center editable-date">{{ number_format($grandTotal ,0) }}</td>

                                        </tr>





                                        <tr class="edit-info-row add-sub is-total-row is-sub-row ">
                                            <td class=" reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close "></td>

                                            <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{ __('Percentage From Grand Total %') }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{$grandTotal && isset($agings['total']['past_due'][$moreThan150]) ?  number_format($agings['total']['past_due'][$moreThan150] / $grandTotal *100 ,2) . ' %' : 0 }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ $grandTotal && isset($agings['total']['past_due'][$daysIntervalInInverseOrder]) ? number_format($agings['total']['past_due'][$daysIntervalInInverseOrder] /$grandTotal * 100 ,2) . ' %' : 0 }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                {{ $grandTotal && isset($agings['total_of_due']['past_due']) ?   number_format($agings['total_of_due']['past_due']/ $grandTotal * 100 ,2) . ' %' : 0 }}
                                            </td>

                                            <td class="  sub-numeric-bg text-center editable-date">{{ $grandTotal && isset($agings['total']['current_due'][0]) ?  number_format($agings['total']['current_due'][0]  / $grandTotal * 100 ,2). ' %' : 0 }} </td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ $grandTotal && isset($agings['total']['coming_due'][$daysInterval])?  number_format($agings['total']['coming_due'][$daysInterval]  / $grandTotal *100 ,2) . ' %' : 0 }} </td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ $grandTotal && isset($agings['total']['coming_due'][$moreThan150]) ? number_format($agings['total']['coming_due'][$moreThan150]  / $grandTotal *100  ,2) . ' %':0 }} </td>
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                {{ $grandTotal && isset($agings['total_of_due']['coming_due']) ?  number_format($agings['total_of_due']['coming_due'] / $grandTotal * 100 ,2) . ' %' : 0 }}
                                            </td>
                                            <td class="sub-numeric-bg text-center editable-date">{{ $grandTotal ? number_format($grandTotal / $grandTotal * 100 ) . ' %' : 0 }}</td>

                                        </tr>



                                        <tr class="edit-info-row add-sub is-total-row is-sub-row ">
                                            <td class=" reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close "></td>

                                            <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{ __('Invoice Count') }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['invoice_count']['past_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['invoice_count']['past_due'][$daysIntervalInInverseOrder] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                @php

                                                $pastDues = $agings['invoice_count']['past_due'] ?? [];
                                                $totalInvoiceForPastDue = array_sum(array_filter($pastDues, 'is_numeric'));

                                                @endphp
                                                {{ number_format($totalInvoiceForPastDue) }}
                                            </td>
                                            @php
                                            $totalInvoiceForCurrentDue = $agings['invoice_count']['current_due'][0] ?? 0;
                                            @endphp
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format( $totalInvoiceForCurrentDue,0) }}</td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['invoice_count']['coming_due'][$daysInterval] ?? 0 ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format($agings['invoice_count']['coming_due'][$moreThan150] ?? 0 ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                @php
                                                $incomingDues = $agings['invoice_count']['coming_due'] ?? [];
                                                $totalInvoiceForComingDue = array_sum(array_filter($incomingDues, 'is_numeric'));

                                                @endphp
                                                {{ number_format($totalInvoiceForComingDue) }}
                                            </td>
                                            <td class="sub-numeric-bg text-center editable-date">{{ number_format($totalInvoiceForPastDue+$totalInvoiceForComingDue+$totalInvoiceForCurrentDue) }}</td>

                                        </tr>





                                        <tr class="edit-info-row add-sub is-total-row is-sub-row ">
                                            <td class=" reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close "></td>

                                            <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{ __('Customers Count') }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format(count($agings['invoice_count']['past_due']['clients'][$moreThan150] ?? [])) }}</td>
                                            @foreach(array_reverse(getInvoiceDayIntervals()) as $daysIntervalInInverseOrder )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format( count($agings['invoice_count']['past_due']['clients'][$daysIntervalInInverseOrder]?? [])  ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">

                                                {{ number_format(count($agings['total_clients_due']['past_due'] ?? [])) }}
                                            </td>
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format(count($agings['invoice_count']['current_due']['clients'][0] ?? []) ,0) }}</td>
                                            @foreach(getInvoiceDayIntervals() as $daysInterval )
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format(count($agings['invoice_count']['coming_due']['clients'][$daysInterval] ?? []) ,0) }}</td>
                                            @endforeach
                                            <td class="  sub-numeric-bg text-center editable-date">{{ number_format(count($agings['invoice_count']['coming_due']['clients'][$moreThan150] ?? []) ,0) }}</td>
                                            <td class="  sub-numeric-bg text-center editable-date">
                                                {{ number_format(count($agings['total_clients_due']['coming_due'] ?? [])) }}
                                            </td>
                                            <td class="sub-numeric-bg text-center editable-date">
                                                {{ number_format(count($agings['grand_clients_total']??[])) }}</td>
                                        </tr>


















                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>

                </div>

            </div>
            <div class="tab-pane" id="kt_apps_contacts_view_tab_2" role="tabpanel">

                @foreach($agings['charts']??[] as $chartName =>$chartArr)
                {{-- <div class="kt-portlet"> --}}

                <div class="row">

                    <x-title :title="$chartName"></x-title>

                    <div class="col-md-6">
                        <div class="kt-portlet kt-portlet--mobile">

                            <div class="kt-portlet__body" data-chart-name="{{ convertStringToClass($chartName) }}">

                                <!--begin: Datatable -->

                                <!-- HTML -->
                                <div id="chartdiv_{{ convertStringToClass($chartName) }}" class="chartDiv"></div>

                                <!--end: Datatable -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="kt-portlet kt-portlet--mobile">



                            <div class="kt-portlet__body">

                                <!--begin: Datatable -->

                                @php
                                $order = 1 ;
                                @endphp

                                <x-table :tableClass="'kt_table_with_no_pagination_no_scroll_no_info'">
                                    @slot('table_header')
                                    <tr class="table-active remove-max-class text-center">
                                        <th>#</th>
                                        <th>{{ __('Item') }}</th>
                                        <th>{{ __('Value') }}</th>
                                        <th>{{ __('%') }}</th>

                                    </tr>
                                    @endslot
                                    @slot('table_body')

                                    @foreach ($chartArr as $key => $item)
                                    <tr>
                                        <th>{{++$key}}</th>
                                        <th style="white-space: normal !important">{{ $item['item'] }}</th>
                                        <td class="text-center">{{ number_format($item['value']) }}</td>
                                        <td class="text-center">{{ number_format($item['percentage'],1) }} %</td>
                                    </tr>
                                    @endforeach

                                    <tr class="table-active remove-max-class text-center">
                                        <th colspan="2">{{__('Total')}}</th>
                                        <td>{{ number_format($item['total_for_all_values'] , 0) }}</td>
                                        <td>{{ number_format($item['total_for_all_percentages'] , 1) }} %</td>
                                    </tr>
                                    @endslot
                                </x-table>




                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                @foreach($agings['charts']??[] as $chartName => $chartArr)
                <input type="hidden" id="total_{{ convertStringToClass($chartName) }}" data-total="{{ json_encode(
      							      $chartArr
        					) }}">
                @endforeach

                {{-- </div> --}}


            </div>
        </div>

        @push('js')
        {{-- <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script> --}}

        <script>
            $(document).on('click', '.trigger-child-row-1', function(e) {
                const parentId = $(e.target.closest('tr')).data('model-id');
                var parentRow = $(e.target).parent();
                var subRows = parentRow.nextAll('tr.add-sub.maintable-1-row-class' + parentId);

                subRows.toggleClass('d-none');
                if (subRows.hasClass('d-none')) {
                    parentRow.find('td.trigger-child-row-1').removeClass('is-open').addClass('is-close').html('+');
                    var closedId = parentRow.attr('data-index')


                } else if (!subRows.length) {
                    // if parent row has no sub rows then remove + or - 
                    parentRow.find('td.trigger-child-row-1').html('×');
                } else {
                    parentRow.find('td.trigger-child-row-1').addClass('is-open').removeClass('is-close').html('-');



                }

            });



            $(document).on('click', '.expand-all', function(e) {
                e.preventDefault();
                if ($(this).hasClass('is-open-parent')) {
                    $(this).addClass('is-close-parent').removeClass('is-open-parent')
                    $(this).find('span').html('-')

                    $('.main-tr.is-close').trigger('click')
                } else {
                    $(this).addClass('is-open-parent').removeClass('is-close-parent')
                    $(this).find('span').html('+')

                    $('.main-tr.is-open').trigger('click')
                }

            })





            var table = $(".kt_table_with_no_pagination_no_collapse.qqq");


            window.addEventListener('scroll', function() {
                const top = window.scrollY > 140 ? window.scrollY : 140;

                $('.arrow-nav').css('top', top + 'px')
            })
            if ($('.kt-portlet__body.with-scroll').length) {
                $('.kt-portlet__body.with-scroll').append(`<i class="cursor-pointer text-dark arrow-nav  arrow-left fa fa-arrow-left"></i> <i class="cursor-pointer text-dark arrow-nav arrow-right fa  fa-arrow-right"></i>`)
                $(document).on('click', '.arrow-nav', function() {
                    const scrollLeftOfTableBody = document.querySelector('.kt-portlet__body.with-scroll').scrollLeft
                    const scrollByUnit = 50
                    if (this.classList.contains('arrow-right')) {
                        document.querySelector('.with-scroll .dataTables_scrollBody').scrollLeft += scrollByUnit

                    } else {
                        document.querySelector('.with-scroll .dataTables_scrollBody').scrollLeft -= scrollByUnit

                    }
                })

            }



            table.DataTable({




                    dom: 'Bfrtip'

                    , "processing": false
                    , "scrollX": true
                    , "scrollY": true
                    , "ordering": false
                    , 'paging': false
                    , "fixedColumns": {
                        left: 2
                    }
                    , "fixedHeader": {
                        headerOffset: 60
                    }
                    , "serverSide": false
                    , "responsive": false
                    , "pageLength": 25
                    , drawCallback: function(setting) {
                        if (!currentTable) {
                            currentTable = $('.main-table-class.qqq').DataTable();
                        }
                        currentTable.columns([2, 3, 4, 5, 6, 7, 8, 9, 10]).visible(false);
                        currentTable.columns([13, 14, 15, 16, 17, 18, 19, 20, 21]).visible(false);
                        $('.buttons-html5').addClass('btn border-parent btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                        $('.buttons-print').addClass('btn border-parent top-0 btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')

                    },





                }

            )

        </script>
        @endpush

        {{-- </div> --}}
    </div>
</div>
</div>
@endsection
@section('js')
<x-js.commons></x-js.commons>

<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>

<script>
    function getDateFormatted(yourDate) {
        const offset = yourDate.getTimezoneOffset()
        yourDate = new Date(yourDate.getTime() - (offset * 60 * 1000))
        return yourDate.toISOString().split('T')[0]
    }

</script>
<script>
    $(document).on('click', '#show-past-due-detail', function() {
        if (!currentTable) {
            currentTable = $('.main-table-class.qqq').DataTable()
        }
        if (currentTable.column(2).visible()) {
            $(this).html("{{ __('Show Details') }}")
            currentTable.columns([2, 3, 4, 5, 6, 7, 8, 9, 10]).visible(false);
        } else {
            $(this).html("{{ __('Hide Details') }}")
            currentTable.columns([2, 3, 4, 5, 6, 7, 8, 9, 10]).visible(true);
        }
    })

    $(document).on('click', '#show-coming-due-detail', function() {
        if (!currentTable) {
            currentTable = $('.main-table-class.qqq').DataTable()
        }
        if (currentTable.column(13).visible()) {
            $(this).html("{{ __('Show Details') }}")
            currentTable.columns([13, 14, 15, 16, 17, 18, 19, 20, 21]).visible(false);
        } else {
            $(this).html("{{ __('Hide Details') }}")
            currentTable.columns([13, 14, 15, 16, 17, 18, 19, 20, 21]).visible(true);
        }
    })

</script>




<!-- Chart code -->

@foreach($agings['charts']??[] as $chartName => $chartArr)
<script>
    am4core.ready(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("chartdiv_{{ convertStringToClass($chartName) }}", am4charts.PieChart);

        // Add data
        chart.data = $('#total_{{ convertStringToClass($chartName) }}').data('total');
        // Add and configure Series
        var pieSeries = chart.series.push(new am4charts.PieSeries());
        pieSeries.dataFields.value = "value";
        pieSeries.dataFields.category = "item";
        pieSeries.innerRadius = am4core.percent(50);
        // arrow
        pieSeries.ticks.template.disabled = true;
        //number
        pieSeries.labels.template.disabled = true;

        var rgm = new am4core.RadialGradientModifier();
        rgm.brightnesses.push(-0.8, -0.8, -0.5, 0, -0.5);
        pieSeries.slices.template.fillModifier = rgm;
        pieSeries.slices.template.strokeModifier = rgm;
        pieSeries.slices.template.strokeOpacity = 0.4;
        pieSeries.slices.template.strokeWidth = 0;

        chart.legend = new am4charts.Legend();
        chart.legend.position = "right";
        chart.legend.scrollable = true;

    });

</script>
@endforeach

@endsection
