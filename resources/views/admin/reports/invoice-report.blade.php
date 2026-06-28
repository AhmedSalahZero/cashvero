@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
<style>
    .custom-w-25 {
        width: 23% !important;
    }

    .custom-w-50 {
        width: 50% !important;
    }

    .max-w-name {
        width: 45% !important;
        min-width: 45% !important;
        max-width: 45% !important;
    }

    .max-w-currency {
        width: 5% !important;
        min-width: 5% !important;
        max-width: 5% !important;
    }

    .max-w-serial {
        width: 3% !important;
        min-width: 3% !important;
        max-width: 3% !important;
    }

    .max-w-amount {
        width: 15% !important;
        min-width: 15% !important;
        max-width: 15% !important;
    }

    .max-w-report-btn {
        width: 15% !important;
        min-width: 15% !important;
        max-width: 15% !important;
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Invoices Table') . '[ '. $partnerName .' ] '.'[ '. $currency .' ]' }}</x-main-form-title>
@endsection
@section('content')
<div class="money-flow-dark">
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
                <div class="text-right">

                    {{-- <a 
					href="{{ route('view.unapplied.amounts',['company'=>$company->id,'partnerId'=>$partnerId,'modelType'=>$modelType]) }}"

                    class="btn active-style btn-icon-sm align-self-center">
                    <i class="fas fa-money-bill"></i>
                    {{ __('Unapplied Amount Settlement') }}
                    </a> --}}

                    <a href="{{ route('view.contracts.down.payments',['company'=>$company->id,'partnerId'=>$partnerId,'modelType'=>$modelType,'currency'=>$currency]) }}" class="btn active-style btn-icon-sm align-self-center">
                        <i class="fas fa-money-bill"></i>
                        {{ __('Down Payment Amount Settlement') }}
                    </a>

                </div>

                <div class="table-custom-container position-relative  ">


                    <div>




                        <div class="responsive">
                            <table class="table kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                                <thead>

                                    <tr class="header-tr ">

                                        <th class="view-table-th max-w-serial bg-lighter header-th  align-middle text-center">
                                            {{ __('#') }}
                                        </th>

                                        @if($hasProjectNameColumn)
                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Project Name') }}
                                        </th>
                                        @endif

                                        @include('admin.reports.invoice-report-th',['totalCollectionOrPaidText'=>$totalCollectionOrPaidText])
                                      

                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Adjust Due Date') }}
                                        </th>

                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Deductions') }}
                                        </th>


                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Actions') }}
                                        </th>
                                    </tr>

                                </thead>
                                <tbody>
                                    <script>
                                        let currentTable = null;

                                    </script>


                                    @foreach($invoices as $index=>$invoice)
                                    <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                                        <td class="sub-text-bg max-w-serial   ">{{ $index+1 }}</td>
                                        @if($hasProjectNameColumn)
                                        <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getProjectName() }}</td>
                                        @endif
                                         @include('admin.reports.invoice-report-td',['company'=>$company,'currency'=>$currency,'invoice'=>$invoice])
                                        <td class="sub-text-bg  text-center">
                                            @if(!$invoice->$isCollectedOrPaid())
                                            <a href="{{ route('adjust.due.dates',['company'=>$company->id,'modelId'=>$invoice->id ,'modelType'=>getModelNameWithoutNamespace($invoice) ]) }}" title="{{ __('Adjust Due Date') }}" class="btn btn-sm btn-success" @if($invoice->dueDateHistories->count())
                                                style="background-color:orange !important;color:black !important;border-color:white !important;"
                                                @else
                                                style="background-color:green !important; border-color:white !important;"
                                                @endif
                                                >{{ $invoice->dueDateHistories->count() ? __('Adjusted') : __('Adjust Due Date') }}</a>
                                            @endif
                                        </td>
                                        <td class="sub-text-bg  text-center">
                                            @if(!$invoice->$isCollectedOrPaid())
                                            <button type="button" class="add-new btn btn-primary d-block" data-toggle="modal" data-target="#add-new-customer-modal-{{ $invoice->id }}">
                                                {{ __('Deduct') }}
                                            </button>
                                            @endif
                                            <div class="modal fade modal-class-js allocate-modal-class" id="add-new-customer-modal-{{ $invoice->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLabel">{{ __('Deduct') }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">

                                                            <form action="{{ route('update.invoice.deductions',['company'=>$company->id,'modelId'=>$invoice->id , 'modelType'=>$modelType]) }}" method="post">
                                                                @method('patch')
                                                                @csrf
                                                                <div class="form-group row justify-content-center">
                                                                    @php
                                                                    $index = 0 ;
                                                                    @endphp

                                                                    {{-- start of fixed monthly repeating amount --}}
                                                                    @php
                                                                    $tableId = 'deductions';

                                                                    $repeaterId = 'model_repeater';

                                                                    @endphp
                                                                    {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                                                                    <x-tables.repeater-table :initialJs="false" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                                                        <x-slot name="ths">
                                                                            @foreach([
                                                                            __('Deduction')=>'th-main-color custom-w-50',
                                                                            __('Date')=>'th-main-color custom-w-25',
                                                                            __('Deduction Amount')=>'th-main-color custom-w-25',
                                                                            ] as $title=>$classes)
                                                                            <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                                                            @endforeach
                                                                        </x-slot>
                                                                        <x-slot name="trs">
                                                                            @php
                                                                            $rows = isset($invoice) ? $invoice->deductions :[-1] ;

                                                                            @endphp
                                                                            @foreach( count($rows) ? $rows : [-1] as $deductionWithPivot)
                                                                            @php
                                                                            $fullPath = new \App\Models\Deduction;
                                                                            if( !($deductionWithPivot instanceof $fullPath) ){
                                                                            unset($deductionWithPivot);
                                                                            }
                                                                            @endphp

                                    <tr @if($isRepeater) data-repeater-item @endif>

                                        <td class="text-center">
                                            <input type="hidden" name="company_id" value="{{ $company->id }}">
                                            <div class="custom-w-50">
                                                <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                </i>
                                            </div>
                                        </td>
                                        <td>

                                            <x-form.select :insideModalWithJs="false" :selectedValue="isset($deductionWithPivot) && $deductionWithPivot->pivot->deduction_id ? $deductionWithPivot->pivot->deduction_id : ''" :options="formatOptionsForSelect($deductions)" :add-new="false" class="select2-select repeater-select form-control custom-w-100" data-filter-type="{{ 'create' }}" :all="false" name="deduction_id"></x-form.select>
                                        </td>



                                        <td>

                                            <div class="kt-input-icon ">
                                                <div class="input-group date custom-w-100">
                                                    <input type="text" name="date" value="{{ isset($deductionWithPivot) ? formatDateForDatePicker($deductionWithPivot->pivot->date) : formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control is-date-css refresh-datepicker-js  kt_datepicker_max_date_is_today" readonly placeholder="Select date" />
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">
                                                            <i class="la la-calendar-check-o"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>



                                        <td>
                                            <div class="kt-input-icon custom-w-100">
                                                <div class="input-group">
                                                    <input type="text" name="amount" class="form-control only-greater-than-or-equal-zero-allowed" value="{{ isset($deductionWithPivot) ? $deductionWithPivot->pivot->amount: 0 }}">
                                                </div>
                                            </div>
                                        </td>


                                    </tr>



                                    @endforeach

                                    </x-slot>




                                    </x-tables.repeater-table>
                                    {{-- end of fixed monthly repeating amount --}}


                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-primary submit-form-btn ">{{ __('Save') }}</button>
                        </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        {{-- @endif --}}
        </td>

        <td class="sub-text-bg  text-center">
            @if(!$invoice->$isCollectedOrPaid())
            <a href="{{ route($moneyReceivedOrPaidUrlName,['company'=>$company->id,'model'=>$invoice->id ]) }}" title="{{ $moneyReceivedOrPaidText }}" class="btn btn-sm btn-primary">{{ $moneyReceivedOrPaidText }}</a>
            @endif
        </td>


        {{-- <td class="sub-text-bg  text-center">
                                            @if(!$invoice->$isCollectedOrPaid())
                                            <a href="{{ route('create.settlement.by.unapplied.amounts',['company'=>$company->id,'customerInvoiceId'=>$invoice->id,'modelType'=>$modelType ]) }}" title="{{ __('Settlement') }}" class="btn btn-sm btn-primary">{{ __('Settlement') }}</a>
        @endif
        </td> --}}

        {{-- <td class="  sub-numeric-bg text-center editable-date"></td> --}}


        {{-- <td class="  sub-numeric-bg text-center editable-date">{{ number_format($result[$customerName]['total'][$year] ?? 0 ) }}</td> --}}

        </tr>






        @push('js_end')
        <script>
            $(function() {
                $('.kt_datepicker_max_date_is_today').datepicker({
                    autoclose: true
                    , todayHighlight: true
                    , orientation: "bottom left",
                    // format: 'mm/dd/yyyy',
                    endDate: new Date(),

                    rtl: false
                });
            })

        </script>

        @endpush


        @endforeach

        </tbody>
        </table>
    </div>

</div>

@push('js')
<script>
    var table = $(".kt_table_with_no_pagination_no_collapse");

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
                    currentTable = $('.main-table-class').DataTable();
                }
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
    $(document).on('click', '#show-past-due-detail', function() {
        if (!currentTable) {
            currentTable = $('.main-table-class').DataTable()
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
            currentTable = $('.main-table-class').DataTable()
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
<script>
    $('.model_repeater').repeater({
        initEmpty: false
        , initEmpty: false
        , isFirstItemUndeletable: true
        , defaultValues: {
            'text-input': 'foo'
        },

        show: function() {
            $(this).slideDown();
            $('input.trigger-change-repeater').trigger('change')
            $(document).find('.datepicker-input').datepicker({
                dateFormat: 'mm-dd-yy'
                , autoclose: true
            })
            $(this).find('.only-month-year-picker').each(function(index, dateInput) {
                reinitalizeMonthYearInput(dateInput)
            });
            $('input:not([type="hidden"])').trigger('change');
            $(this).find('.dropdown-toggle').remove();
            $(this).find('select.repeater-select').selectpicker("refresh");
            $(this).find('.refresh-datepicker-js').datepicker('update', '{{ now()->format("m/d/Y") }}')
        },

        hide: function(deleteElement) {
            if ($('#first-loading').length) {
                $(this).slideUp(deleteElement, function() {

                    deleteElement();
                    //   $('select.main-service-item').trigger('change');
                });
            } else {
                if (confirm('Are you sure you want to delete this element?')) {
                    $(this).slideUp(deleteElement, function() {

                        deleteElement();
                        $('input.trigger-change-repeater').trigger('change')

                    });
                }
            }
        }
    });

</script>
@endsection
