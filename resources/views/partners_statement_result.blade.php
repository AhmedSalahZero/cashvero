@extends('layouts.dashboard')
@section('css')
<style>
    .DataTables_Table_0_filter {
        float: left;

    }

    .dt-buttons button {
        color: #366cf3 !important;
        border-color: #366cf3 !important;
    }

    .dataTables_wrapper>.row>div.col-sm-6:first-of-type {
        flex-basis: 20% !important;
    }

    .dataTables_wrapper>.row label {
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    .kt-portlet__head-title,
    .fa-layer-group {
        color: #366cf3 !important;
        border-bottom: 2px solid #366cf3;
        padding-bottom: .5rem !important;
    }

    table {
        white-space: nowrap;
        table-layout: auto;
        border-collapse: collapse;
        width: 100%;
    }

    table td {
        border: 1px solid #ccc;
        color: gr
    }

    table .absorbing-column {
        width: 100%;
    }

</style>
{{-- <link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" /> --}}


@endsection

@section('content')
<div class="">
@php
	$totalDebit = 0 ;
	$totalCredit = 0 ;
	$totalEndBalance = 0 ;
@endphp
<div class="kt-portlet kt-portlet--tabs">

    <div class="kt-portlet__body">
        <div class="tab-content kt-margin-t-20">

@php
	$warningMessage = '<span class="text-red"> [ Just a note: partners without any transactions won’t appear in the report. ] </span>'
@endphp
            <!--End:: Tab  EGP FX Rate Table -->

            <!--Begin:: Tab USD FX Rate Table -->
            <div class="tab-pane active" id="kt_apps_contacts_view_tab_2" role="tabpanel">
                <x-table :tableTitle="$title . $warningMessage" :tableClass="'kt_table_with_no_pagination_no_fixed_right'">
				
                    @slot('table_header')
                    <tr class="table-active text-center">
                        <th class="text-center absorbing-column">{{ __('Name') }}</th>

                        <th> {{ __('Date') }} </th>
                        <th> {{ __('Beginning') }} </th>
                        <th> {{ __('Debit') }} </th>
                        <th> {{ __('Credit') }} </th>
                        <th> {{ __('End Balance') }} </th>
                        {{-- <th> {{ __('Reviewed') }} </th> --}}
                        <th> {{ __('Comment') }} </th>

                    </tr>
                    @endslot
                    @slot('table_body')

                    @php $id =1 ;@endphp

                    @foreach ($statements as $partnerId => $statementDataWithPartnerName)



                    <tr class="group-color">

                        <td class="white-text trigger-toggle-row"  style="cursor: pointer;" onclick="toggleRow('{{ $id }}')">
                            <i class="row_icon{{ $id }} flaticon2-up white-text"></i>
                            <b>{{ $statementDataWithPartnerName['name'] }}</b>
                        </td>




                        <td class="sub-text-bg text-center text-white">
                            <b>{{ __('Date') }}</b>
                        </td>
                        <td class="sub-text-bg text-center text-white max-w-invoice-number">
                            <b>{{ __('Beginning') }}</b>
                        </td>
                        <td class="sub-text-bg text-center text-white max-w-invoice-date">
                            <b>{{ __('Debit') }}</b>
                        </td>
                        <td class="sub-text-bg text-center text-white max-w-currency">
                            <b>
                                {{ __('Credit') }}

                            </b>
                        </td>
                        <td class="sub-text-bg text-center text-white max-w-amount">
                            <b>
                                {{ __('End Balance')  }}
                            </b>

                        </td>
{{-- 
                        <td class="sub-text-bg text-center text-white">
                            <b>{{ __('Reviewed') }}</b>
                        </td> --}}
                        <td class="sub-text-bg align-middle text-white text-center max-w-amount">
                   
                            <b>
                                {{ __('Comment') }}
                            </b>

                        </td>

                    </tr>
                    @php
                    $index=0;
                    @endphp

                    @foreach ($statementDataWithPartnerName['statements']??[] as $modelAsStdClass)
				
                    @php
                    $index++;
                    @endphp
                    <tr class="row{{ $id }} text-center" style="display: none">
                        <td class="sub-text-bg max-w-serial">#{{ $index }}</td>
                        <td class="sub-text-bg text-center">{{ \Carbon\Carbon::make($modelAsStdClass->date)->format('d-m-Y') }}</td>
                        <td class="sub-text-bg text-center max-w-invoice-number">{{ number_format($modelAsStdClass->beginning_balance) }}</td>
                        <td class="sub-text-bg text-center max-w-invoice-date">{{ number_format($modelAsStdClass->debit) }}
						@php
							$totalDebit+=$modelAsStdClass->debit;
						@endphp
						</td>
                        <td class="sub-text-bg text-center max-w-currency">{{ number_format($modelAsStdClass->credit) }}
						
							@php
							$totalCredit+=$modelAsStdClass->credit;
						@endphp
						
						</td>
                        <td class="sub-text-bg text-center max-w-amount">{{ number_format($modelAsStdClass->end_balance) }}
						
						@php
							if($loop->last){
								$totalEndBalance+=$modelAsStdClass->end_balance;
							}
						@endphp
						</td>
                        @php
                        $comment = isset($modelAsStdClass->{'comment_'.$lang}) ? $modelAsStdClass->{'comment_'.$lang} : null ;
                     //   $reviewedArr = getBankStatementReviewed($modelAsStdClass) ;
                      //  $reviewedText = getReviewedText($reviewedArr);
                        $userComment = \App\Helpers\HNonBanking::getUserCommentFromModel($modelAsStdClass);
                        @endphp
                        {{-- <td class="sub-text-bg text-left">{{ $reviewedText   }}</td> --}}
                        <td class="sub-text-bg text-left max-w-amount">{{ $comment?:  getBankStatementComment($modelAsStdClass) }}
                            <br>
                            {{ $userComment }}

                        </td>

                    </tr>

                    @endforeach

                    @php $id++ ;@endphp
                    @endforeach


                    <tr class="active-style text-center">
                        <td class="active-style text-center"><b>
							{{ __('Total') }}
						</b></td>

                        <td class="text-center active-style">
                            -- </td>
                        {{-- @endforeach --}}
                        <td class="text-center active-style">--</td>
                        <td class="text-center active-style">{{ number_format($totalDebit) }}</td>
                        <td class="text-center active-style">{{ number_format($totalCredit) }}</td>
                        <td class="text-center active-style">{{ number_format($totalEndBalance) }}</td>
                        {{-- <td class="text-center active-style">--</td> --}}
                        <td class="text-center active-style">--</td>
                    </tr>


                    @endslot
                </x-table>

            </div>
            <!--End:: Tab USD FX Rate Table -->
        </div>
    </div>
</div>

</div>
@endsection

@push('css')


<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.3/r-2.3.0/rg-1.2.0/sl-1.4.0/sr-1.1.1/datatables.min.css" />

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
        background-color: #086691 !important;
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
@endpush
@push('js')
@include('js_datatable')
@endpush

@section('js')
<!-- Resources -->
<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
</script>
<script>
    function toggleRow(rowNum) {
        $(".row" + rowNum).toggle();
        $('.row_icon' + rowNum).toggleClass("flaticon2-down flaticon2-up");
    }
	
</script>
<script>
	$(function(){
		$('.trigger-toggle-row').trigger('click')
	})
</script>
@endsection



{{-- @extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
<style>
    .max-w-serial {
        width: 5% !important;
        min-width: 5% !important;
        max-width: 5% !important;
    }










    .is-sub-row.is-total-row td.sub-numeric-bg,
    .is-sub-row.is-total-row td.sub-text-bg {
        background-color: #087383 !important;
        color: white !important;
    }

    . {
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Partners Statement '  ) .  '[ ' . __($currency) . ' ]' }}</x-main-form-title>
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


                        background-color: #E2EFFE !important;
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


                <div class="table-custom-container position-relative">
                    <div>
                        <div class="responsive">
                            <table class="table kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                                <thead>

                                    <tr class="header-tr">

                                        <th class="view-table-th max-w-serial header-th align-middle text-center">
                                            {{ __('#') }}
                                        </th>

                                        <th class="view-table-th header-th align-middle text-center">
                                            {{ __('Date') }}
                                        </th>

                                        <th class="view-table-th max-w-invoice-number header-th align-middle text-center">
                                            {{ __('Beginning Balance') }}
                                        </th>


                                        <th class="view-table-th max-w-currency header-th align-middle text-center">
                                            {{ __('Debit') }}
                                        </th>

                                        <th class="view-table-th max-w-amount header-th align-middle text-center">
                                            {{ __('Credit') }}
                                        </th>
                                        <th class="view-table-th max-w-invoice-date max-w-report-btn header-th align-middle text-center">
                                            {{ __('End Balance') }}
                                        </th>
                                        <th class="view-table-th header-th align-middle text-center">
                                            {{ __('Reviewed') }}
                                        </th>
                                        <th class="view-table-th max-w-invoice-date max-w-report-btn header-th align-middle text-center">
                                            {{ __('Comment') }}
                                        </th>










                                    </tr>

                                </thead>
                                <tbody>
                                    <script>
                                        let currentTable = null;

                                    </script>

                                    @foreach($results as $index=>$modelAsStdClass)
                                    <tr class="parent-tr reset-table-width text-nowrap cursor-pointer sub-text-bg text-capitalize is-close">
                                        <td class="sub-text-bg max-w-serial">{{ $index+1 }}</td>
                                        <td class="sub-text-bg text-center">{{ \Carbon\Carbon::make($modelAsStdClass->date)->format('d-m-Y') }}</td>
                                        <td class="sub-text-bg text-center max-w-invoice-number">{{ number_format($modelAsStdClass->beginning_balance) }}</td>
                                        <td class="sub-text-bg text-center max-w-invoice-date">{{ number_format($modelAsStdClass->debit) }}</td>
                                        <td class="sub-text-bg text-center max-w-currency">{{ number_format($modelAsStdClass->credit) }}</td>
                                        <td class="sub-text-bg text-center max-w-amount">{{ number_format($modelAsStdClass->end_balance) }}</td>
                                        @php
                                        $comment = isset($modelAsStdClass->{'comment_'.$lang}) ? $modelAsStdClass->{'comment_'.$lang} : null ;
                                        $reviewedArr = getBankStatementReviewed($modelAsStdClass) ;
                                        $reviewedText = getReviewedText($reviewedArr);
                                        $userComment = \App\Helpers\HNonBanking::getUserCommentFromModel($modelAsStdClass);
                                        @endphp
                                        <td class="sub-text-bg text-left">{{ $reviewedText   }}</td>
                                        <td class="sub-text-bg text-left max-w-amount">{{ $comment?:  getBankStatementComment($modelAsStdClass) }}
                                            <br>
                                            {{ $userComment }}

                                        </td>


                                    </tr>

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

    @endsection --}}

