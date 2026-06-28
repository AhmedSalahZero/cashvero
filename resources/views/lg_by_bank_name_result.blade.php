@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
<style>
.max-w-comment{
	width:700px !important;
	max-width:700px !important;
	min-width:700px !important;
	text-wrap:wrap !important;
	
}
#DataTables_Table_0_info{
	margin-bottom:20px;
}
    .max-w-serial {
        width: 5% !important;
        min-width: 5% !important;
        max-width: 5% !important;

    }

    .z-index-6 {
        position: relative;
        z-index: 1;
    }

    .mt--30 {
        margin-top: -30px;
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('LG Report By Bank Name ['  ) . $startDate . ' ]  [ ' . __(touppercase($currency)) . ' ]' }}</x-main-form-title>

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


                <div class="table-custom-container position-relative  ">
                    

                    <div>


                        <div class="responsive mt--30">
                            <table class="table kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                                <thead>

                                    <tr class="header-tr ">

                                        <th class="view-table-th  header-th  align-middle text-center">
                                            {{ __('#') }}
                                        </th>

                               <th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Bank Name') }}
                                        </th> 
                                     
                                        <th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('LG Type') }}
                                        </th>
                                            <th class="view-table-th   header-th  align-middle text-center">
                                            {{ __('Beneficiary Name') }}
                                        </th>
                                        <th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Transaction Name') }}
                                        </th>


                                        <th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('LG Code') }}
                                        </th>

                                        <th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Source') }}
                                        </th>
										     
                                       
										<th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Amount') }}
                                        </th>	
										<th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Renewal Date') }}
                                        </th><th class="view-table-th     header-th  align-middle text-center">
                                            {{ __('Cash Cover') }}
                                        </th>
										<th class="view-table-th     header-th  align-middle text-center">
                                            {!! __('Commission <br> Rate %') !!}
                                        </th>

                                    

                                    



                                    </tr>

                                </thead>
                                <tbody>
                                    <script>
                                        let currentTable = null;

                                    </script>
									@php
										$lgAmountTotal =  0 ;
										$cashCoverAmountTotal =  0 ;
										
									@endphp
                                    @foreach($results as $index=>$modelAsStdClass)
                                    <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                                        <td class="sub-text-bg  ">{{ $index+1 }}</td>
                                        <td class="sub-text-bg  text-left ">{{ $modelAsStdClass->financial_institution_name }}</td>

                                        <td class="sub-text-bg  text-center ">{{ $lgsTypes[$modelAsStdClass->lg_type] }}</td>
										                                        <td class="sub-text-bg  text-left ">{{ $modelAsStdClass->partner_name }}</td>
										
                                        <td class="sub-text-bg  text-left ">{{ $modelAsStdClass->transaction_name }}</td>
                                        <td class="sub-text-bg  text-center ">{{ $modelAsStdClass->lg_code }}</td>
                                        <td class="sub-text-bg  text-center ">{{ $lgsSources[$modelAsStdClass->source] }}</td>
                                        <td class="sub-text-bg  text-center ">{{ number_format($modelAsStdClass->lg_amount) }}</td>
                                        <td class="sub-text-bg  text-center ">{{ $modelAsStdClass->renewal_date }}</td>
                                        <td class="sub-text-bg  text-center ">{{ number_format($modelAsStdClass->cash_cover_amount) }}</td>
                                        <td class="sub-text-bg  text-center ">{{ $modelAsStdClass->lg_commission_rate }}</td>

                                    </tr>
									@php
										$lgAmountTotal += $modelAsStdClass->lg_amount ;
										$cashCoverAmountTotal += $modelAsStdClass->cash_cover_amount ;
									@endphp
                                    @endforeach
									
									 <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                                        <td class="sub-text-bg  ">-</td>
                                        <td class="sub-text-bg  text-center ">{{ __('Total') }}</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">{{ number_format($lgAmountTotal) }}</td>
                                        <td class="sub-text-bg  text-center ">-</td>
                                        <td class="sub-text-bg  text-center ">{{ number_format($cashCoverAmountTotal) }}</td>
                                        <td class="sub-text-bg  text-center ">-</td>

                                    </tr>








                                </tbody>
								
                            </table>
{{ $results->appends(Request()->all())->links() }}
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
  
    @endsection
