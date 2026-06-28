@extends('layouts.dashboard')
@php
use Carbon\Carbon;
@endphp

@section('css')
<x-styles.commons></x-styles.commons>
<style>
    .max-w-invoice-date {
        width: 25% !important;
        min-width: 25% !important;
        max-width: 25% !important;
    }

    .max-w-counts {
        width: 20% !important;
        min-width: 20% !important;
        max-width: 20% !important;
    }

    .max-w-action {
        width: 25% !important;
        min-width: 25% !important;
        max-width: 25% !important;
    }

    .max-w-serial {
        width: 5% !important;
        min-width: 5% !important;
        max-width: 5% !important;
    }

    .dt-buttons.btn-group.flex-wrap {
        margin-bottom: 5rem !important;
    }

    #DataTables_Table_0_filter {
        display: none !important;
    }

    .dataTables_scrollHeadInner {
        width: 100% !important;
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Renewal Date') }}</x-main-form-title>
@endsection
@section('content')

<div class="row">
    <div class="col-md-12">

        <div class="kt-portlet">


            <div class="kt-portlet__body">

                @php

                $tableId = 'kt_table_1';
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
                @csrf







                @if(isset($model) || $letterOfGuaranteeIssuance->isExpired())
                <div class="row">
                    <div class="col-md-12">
                        <!--begin::Portlet-->


                        <!--begin::Form-->
                        <form method="post" action="{{ isset($model) ? route('update.letter.of.issuance.renewal.date',['company'=>$company->id, 'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id , 'LgRenewalDateHistory'=>$model->id]) :route('store.letter.of.issuance.renewal.date',['company'=>$company->id , 'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]) }}" class="kt-form kt-form--label-right">
                            @csrf
                            @if(isset($model))
                            @method('patch')
                            @endif
							
                            <div class="kt-portlet">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                            {{__('Adjusted Renewal Date Section')}}
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <div class="form-group row">
                                        <div class="col-md-4 mb-4">
                                            <label> {{ __('Transaction Name') }} </label>
                                            <input type="text" class="form-control" disabled value="{{ $letterOfGuaranteeIssuance->getTransactionName() }}">
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <label>{{__('Source')}} </label>
                                            <input type="text" class="form-control" disabled value="{{ $letterOfGuaranteeIssuance->getSourceFormatted() }}">
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <label>{{__('LG Code')}} </label>
                                            <input type="text" class="form-control" disabled value="{{ $letterOfGuaranteeIssuance->getLgCode() }}">
                                        </div>
										
										     <div class="col-md-3 mb-4">
                                            <label>{{__('Issuance Date')}} </label>
                                            <input type="text" class="form-control" disabled value="{{ $letterOfGuaranteeIssuance->getIssuanceDateFormatted() }}">
                                        </div>
                                        <div class="col-md-3 mb-4">
                                            <label>{{__('Expiry Date')}} </label>
											<input type="hidden" name="expiry_date" value="{{ isset($model)  ? $letterOfGuaranteeIssuance->getRenewalDateBefore($letterOfGuaranteeIssuance->getRenewalDate()) :$letterOfGuaranteeIssuance->getRenewalDate() }}">
                                            <input type="text" class="form-control" disabled  value="{{ isset($model)  ? $letterOfGuaranteeIssuance->getRenewalDateBefore($letterOfGuaranteeIssuance->getRenewalDate()) :$letterOfGuaranteeIssuance->getRenewalDate() }}">
                                        </div>
										
										  <div class="col-md-3">
                                            <label>{{__('New Expiry Date')}} @include('star') </label>
                                            <div class="kt-input-icon">
                                                <div class="input-group date">
                                                    <input required type="text" name="renewal_date" value="{{ isset($model) ? $model->getRenewalDateFormattedForDatePicker() : null }}" id="kt_datepicker_2" class="form-control" readonly placeholder="{{ __('Select date') }}" />
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">
                                                            <i class="la la-calendar-check-o"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <div class="col-md-3 mb-4">
                                            <label>{{__('Renewal Fees')}} </label>
                                            <input type="text" class="form-control only-greater-than-or-equal-zero-allowed" name="fees_amount" value="{{ isset($model)  ? $model->getFeesAmount() : 0 }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-submitting />

                        </form>
                    </div>
                </div>
				@endif

                <div class="kt-portlet">

                    <div class="kt-portlet__body with-scroll pt-0">

                        <div class="table-custom-container position-relative  ">


                            <div>




                                <div class="responsive">
                                    <table class="table kt_table_with_no_pagination_no_collapse table-for-currency  table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class-for-currency dataTable no-footer">
                                        <thead>

                                            <tr class="header-tr ">

                                                <th class="view-table-th max-w-serial  header-th  align-middle text-center">
                                                    {{ __('#') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-invoice-date header-th  align-middle text-center">
                                                    {{ __('Date') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-counts header-th  align-middle text-center">
                                                    {{ __('Days Count') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-counts header-th  align-middle text-center">
                                                    {{ __('Fees Amount') }}
                                                </th>


                                                <th class="view-table-th max-w-name max-w-action  header-th  align-middle text-center">
                                                    {{ __('Actions') }}
                                                </th>







                                            </tr>

                                        </thead>
                                        <tbody>
                                            @php
                                            $previousDate = null ;
                                            @endphp
                                            @foreach($renewalDateHistories as $index => $renewalDateHistory)
                                            <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                                                <td class="sub-text-bg max-w-serial text-center   ">{{ ++$index }}</td>
                                                <td class="sub-text-bg max-w-invoice-date  text-center   ">{{ $currentRenewalDate = $renewalDateHistory->getRenewalDateFormatted() }} {{ is_null($previousDate) ? __(' (Original Renewal Date) ') : '' }} </td>
                                                <td class="sub-text-bg  text-center  max-w-counts ">{{ $previousDate ? getDiffBetweenTwoDatesInDays(Carbon::make($previousDate),Carbon::make($currentRenewalDate)) : '-' }}</td>
                                                @php
                                                $previousDate = $renewalDateHistory->getRenewalDate();
                                                @endphp
                                                <td class="sub-text-bg  text-center max-w-counts ">{{ $renewalDateHistory->getFeesAmountFormatted() }}</td>
                                                <td class="sub-text-bg  text-center max-w-action   ">
                                                    @if($loop->last)
                                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{route('edit.letter.of.issuance.renewal.date',[$company,$letterOfGuaranteeIssuance->id,$renewalDateHistory->id])}}"><i class="fa fa-pen-alt"></i></a>


                                                    <a class="btn btn-secondary btn-outline-hover-danger btn-icon  " href="#" data-toggle="modal" data-target="#modal-delete-{{ $renewalDateHistory['id']}}" title="Delete"><i class="fa fa-trash-alt"></i>
                                                    </a>
                                                    @endif

                                                    <div id="modal-delete-{{ $renewalDateHistory['id'] }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h4 class="modal-title">{{ __('Delete Renewal Date History ' .$renewalDateHistory->getRenewalDateFormatted()) }}</h4>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <h3>{{ __('Are You Sure To Delete This Item ? ') }}</h3>
                                                                </div>
                                                                <form action="{{ route('delete.letter.of.issuance.renewal.date',[$company,$letterOfGuaranteeIssuance->id,$renewalDateHistory->id]) }}" method="post" id="delete_form">
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

                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                            @push('js')
                            <script>
                                $('.table-for-currency').DataTable({
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
                                            $('.buttons-html5').addClass('btn border-parent btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                                            $('.buttons-print').addClass('btn border-parent top-0 btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                                        },

                                    }

                                )

                            </script>
                            @endpush

                        </div>

                    </div>
                </div>









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

        am4core.ready(function() {

            // Themes begin



        }); // end am4core.ready()

    </script>
    <script>



    </script>

    @endsection
