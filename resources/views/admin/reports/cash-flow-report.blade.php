@extends('layouts.dashboard')
@section('css')
@php
	use Carbon\Carbon ;
@endphp
<x-styles.commons></x-styles.commons>
<style>
    .bg-lighter ,
    .bg-lighter * 
	{
        background-color: #00b4c8 !important;
        color: black !important;
    }

    .max-w-weeks {
        max-width: 100px !important;
        min-width: 100px !important;
        width: 100px !important;
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Cash Flow Report') }}</x-main-form-title>
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

                    .money-flow-dark .is-sub-row td.sub-numeric-bg,
                    .money-flow-dark .is-sub-row td.sub-text-bg {
                        border: 1.5px solid rgba(255, 255, 255, 0.25) !important;
                        background-color: #1490a8 !important;
                        color: #ffffff !important;
                        font-weight: 400 !important;
                    }

                    .is-sub-row td.sub-numeric-bg,
                    .is-sub-row td.sub-text-bg {
                        border: 1.5px solid white !important;
                        background-color: #1490a8 !important;
                        color: #ffffff !important;
                        font-weight: 400 !important;
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

                    .money-flow-dark .dataTables_wrapper .dataTable .is-sub-row td.sub-numeric-bg,
                    .money-flow-dark .dataTables_wrapper .dataTable .is-sub-row td.sub-text-bg {
                        color: #ffffff !important;
                        background-color: #1490a8 !important;
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
			

                    <div class="responsive">
                        <table class="table kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                            <thead>
                                <tr class="header-tr ">
                                    <th rowspan="{{ $noRowHeaders }}" class="view-table-th expand-all is-open-parent header-th editable-date max-w-classes-expand align-middle text-center trigger-child-row-1">
                                        {{ __('Expand All' ) }}
                                        <span>+</span>
                                    </th>
                                    <th rowspan="{{ $noRowHeaders }}" class="view-table-th header-th max-w-classes-name align-middle text-center">
                                        {{ __('Item') }}
                                    </th>
                                    <th class="view-table-th @if($reportInterval == 'weekly') bg-lighter @endif max-w-weeks header-th  align-middle text-center">
                                        @if($reportInterval == 'weekly')
										{{ __('Week Num') }}
										@elseif($reportInterval == 'monthly')
										{{ __('Months') }}
										@elseif($reportInterval == 'daily')
										{{ __('Days') }}
										
										@endif 
										
                                    </th>
									@if($reportInterval == 'weekly')
                                    @foreach($weeks as $weekAndYear => $week)
									@php
										$year = explode('-',$weekAndYear)[1];
									@endphp
                                    <th class="view-table-th bg-lighter header-th max-w-weeks align-middle text-center">
										<span class="d-block">{{ __('Week ' .  $week ) }}</span>
										<span class="d-block">{{ '[ ' . $year . ' ]' }}</span>
									</th>
                                    @endforeach
									@elseif($reportInterval == 'monthly')
									
									@foreach($months as $month)
									 <th class="view-table-th  header-th max-w-weeks align-middle text-center">
									 	@if($loop->first || $loop->last)
										<span class="d-block">{{ Carbon::make($month)->format('d-m-Y') }}</span>
										@else 
										<span class="d-block">{{ Carbon::make($month)->format('m-Y') }}</span>
										@endif 
									</th>
									@endforeach 
									
									
										@elseif($reportInterval == 'daily')
									
										@foreach($days as   $day)
										<th class="view-table-th  header-th max-w-weeks align-middle text-center">
											<span class="d-block">{{ Carbon::make($day)->format('d-m-Y') }}</span>
										</th>
										@endforeach 
									
									@endif 
                                    <th rowspan="{{ $noRowHeaders }}" class="view-table-th editable-date align-middle text-center header-th max-w-grand-total">
                                        {{ __('Total') }}
                                    </th>

                                </tr>
				@if($reportInterval == 'weekly')
                                <tr class="header-tr ">


                                    <th class="view-table-th header-th max-w-weeks  align-middle text-center" class="header-th">
                                        {{ __('Start Date') }}
                                    </th>
                                    @foreach($dates as $index=>$startAndEndDate)
                                    <th class="view-table-th header-th max-w-weeks text-nowrap  align-middle text-center">{{ $startAndEndDate['start_date'] }}</th>
                                    @endforeach


                                </tr>


                                <tr class="header-tr ">

                                    <th class="view-table-th header-th max-w-weeks  align-middle text-center" class="header-th">
                                        {{ __('End Date') }}
                                    </th>
                                      @foreach($dates as $index=>$startAndEndDate)
                                    <th class="view-table-th header-th text-nowrap max-w-weeks  align-middle text-center">{{ $startAndEndDate['end_date'] }}</th>
                                    @endforeach


                                </tr>

					@endif


                            </thead>
                            <tbody>
                                <script>
                                    let currentTable = null;

                                </script>
                                @php
                                $rowIndex = 0 ;
                                @endphp
                                @foreach(array_merge(['Cash & Banks Begining Balance','Checks Collected','Incoming Transfers','Bank Deposits','Cash Collections','Customers Invoices','Customers Past Due Invoices','Cheques In Safe','Cheques Under Collection','Sales Forecast Collections',__('Total Cash Inflow'),'Outgoing Transfers','Cash Payments','Paid Payable Cheques','Under Payment Payable Cheques','Suppliers Invoices','Suppliers Past Due Invoices'
								// ,'Operational Expenses Payments','Wages & Salaries Payments','Taxes & Social Insurance Payments','Forecasted Suppliers Payments','Total Cash Outflow','Cash Flow From Operations'
								
								],$cashExpenseCategoryNamesArr,['Total Cash Outflow','Net Cash (+/-)','Accumulated Net Cash (+/-)']) as $customerName)
                                @if($customerName == 'total' || $customerName =='grand_total' || $customerName =='total_of_due' || $customerName =='total_customers_due')
                                @continue ;
                                @endif
                                @php
                                $hasSubRows = count($customerAging['invoices']??[]) ;
                                $currentTotal = $customerAging['total'] ?? 0 ;
                                @endphp
                                <tr class=" @if($customerName == 'Total Cash Inflow' || $customerName == 'Total Cash Outflow') bg-lighter @else  @endif  parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   " data-model-id="{{ $rowIndex }}">
                                    <td class="red reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize main-tr is-close"> @if($hasSubRows) + @endif  </td>
                                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ $customerName }}</td>
                                    <td class="  sub-numeric-bg text-center editable-date"> 
										@if($customerName == 'Customers Past Due Invoices')
										<button   class="btn btn-sm btn-warning text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'CustomerInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueCustomerInvoices" :id="'test-modal-id'"></x-modal.due-invoices>
										
										@endif 
										
											@if($customerName == 'Suppliers Past Due Invoices')
												<button   class="btn btn-sm btn-warning text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'SupplierInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueSupplierInvoices" :id="'test-modal-id'"></x-modal.due-invoices>
										
											@endif 
											@if($customerName == 'Loan Past Due Installments')

												<button   class="btn btn-sm btn-warning text-white js-show-loan-past-due-installment-modal">{{ __('View') }}</button>
                                                {{-- <x-modal.due-invoices :report-interval="$reportInterval" :currentInvoiceType="'SupplierInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueSupplierInvoices" :id="'test-modal-id'"></x-modal.due-invoices> --}}
												  <x-modal.loan-installment :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport : null" :report-interval="$reportInterval"  :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueInstallments" :id="'test-modal-id'"></x-modal.loan-installment>
										
											@endif 
											
											
										
										
									
									 </td>
                                    @foreach($weeks as $weekAndYear => $week)
									
                                    @php
									$year = explode('-',$weekAndYear)[1];
                                    $currentValue = $result[$customerName][$weekAndYear] ?? 0 ;
									if($customerName == 'Customers Past Due Invoices' )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										
										$filtered = array_filter($customerDueInvoices, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										
									}
									if($customerName == 'Suppliers Past Due Invoices' )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($supplierDueInvoices, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
									}	
									if($customerName == 'Loan Past Due Installments' )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($pastDueLoanInstallments, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
									}
                                    $currentPercentage = $currentValue && $currentTotal ? $currentValue/ $currentTotal * 100 : 0 ;
                                    @endphp
                                    <td class="  sub-numeric-bg text-center editable-date">{{ number_format($currentValue,0) }}</td>
                                    @endforeach
                                    <td class="  sub-numeric-bg text-center editable-date">
									{{ number_format($result[$customerName]['total'][$year] ?? 0 ) }}
									
									</td>

                                </tr>



                                @php
                                $rowIndex = $rowIndex+ 1;
                                @endphp

                                @endforeach


                            </tbody>
                        </table>
                    </div>

                </div>

                @push('js')
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





                    var table = $(".kt_table_with_no_pagination_no_collapse");


                    window.addEventListener('scroll', function() {
                        const top = window.scrollY > 140 ? window.scrollY : 140;

                        $('.arrow-nav').css('top', top + 'px')
                    })
                    if ($('.kt-portlet__body').length) {
                        $('.kt-portlet__body').append(`<i class="cursor-pointer text-dark arrow-nav  arrow-left fa fa-arrow-left"></i> <i class="cursor-pointer text-dark arrow-nav arrow-right fa  fa-arrow-right"></i>`)
                        $(document).on('click', '.arrow-nav', function() {
                            const scrollLeftOfTableBody = document.querySelector('.kt-portlet__body').scrollLeft
                            const scrollByUnit = 50
                            if (this.classList.contains('arrow-right')) {
                                document.querySelector('.dataTables_scrollBody').scrollLeft += scrollByUnit

                            } else {
                                document.querySelector('.dataTables_scrollBody').scrollLeft -= scrollByUnit

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
 $(document).on('click', '.js-show-customer-due-invoices-modal', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.modal-item-js').modal('show')
    })
</script>

@endsection
