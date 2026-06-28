@extends('layouts.dashboard')
@section('css')
@php
use App\Helpers\HArr;
use Carbon\Carbon ;
@endphp
<x-styles.commons></x-styles.commons>
<style>
.expandable-percentage-input {
    max-width: 75px !important;
    min-width: 75px !important;
    text-align: center !important;
}
.expandable-amount-input {
    max-width: 150px !important;
    min-width: 150px !important;
    text-align: center !important;
}

    .ml-son {
        margin-left: 10px;
        font-weight: 400;
    }

    .bg-lighter,
    .bg-lighter * {
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
<x-main-form-title :id="'main-form-title'" :class="''">{{ $title }}</x-main-form-title>
@endsection
@section('content')
<div class="money-flow-dark">
@php
$moreThan150=\App\ReadyFunctions\InvoiceAgingService::MORE_THAN_150;
@endphp
<script>
    let globalTable = null;

</script>
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

    .money-flow-dark .is-sub-row .ml-son {
        color: #ffffff !important;
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
<div class="row">
    <div class="col-md-12">

        <div class="kt-portlet kt-portlet--tabs">

            <div class="kt-portlet__head">
                <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
                    <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
					@php
						$index = 0 ;
					@endphp
					@foreach($allCurrencies as $currentCurrencyName)
                        <li class="nav-item">
                            <a class="nav-link {{ $index == 0 ?'active':'' }}" data-toggle="tab" href="#{{ $currentCurrencyName }}" role="tab">
                                <i class="fa fa-money-check-alt"></i> {{ $currentCurrencyName }}
                            </a>
                        </li>
						@php
							$index++;
						@endphp
						@endforeach 
						
						 <li class="nav-item">
                            <a class="nav-link"  data-toggle="tab" href="#projection-in" role="tab">
                                <i class="fa fa-money-check-alt"></i> {{ __('Projected Other Cash In Items') }}
                            </a>
                        </li>
						
						   <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#projection-out" role="tab">
                                <i class="fa fa-money-check-alt"></i> {{ __('Projected Other Cash Out Items') }}
                            </a>
                        </li>
						@php
							$index++
						@endphp

                    

                    </ul>

                </div>
            </div>


            <div class="kt-portlet__body " style="padding-top:0 !important">
                <div class="tab-content  ">
				@php
					$index = -1 ;
				@endphp
				
					@foreach($allCurrencies as $currentCurrencyName)
                    @php
                    $currentType =$currentCurrencyName ;
                    $tableId = 'kt_table_'.$currentCurrencyName;
					$index++;
                    @endphp
                    <div class="tab-pane {{ $index == 0 ? 'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                        <div class="kt-portlet kt-portlet--mobile">


                       
                            <div class="table-custom-container position-relative  ">


                                <div class="responsive">
                                    <table class="table kt_table_with_no_pagination_no_collapse{{ $tableId }} table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class{{ $tableId }} dataTable no-footer">
                                        <thead>
                                            <tr class="header-tr ">
                                                <th rowspan="{{ $noRowHeaders }}" class="view-table-th expand-all is-open-parent header-th editable-date max-w-classes-expand align-middle text-center  trigger-child-row-1">
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

                                                @foreach($days as $day)
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
											$allMainRowsTotals = [];
                                            @endphp

                                            @foreach(['customers','suppliers','cash_expenses','lg'] as $mainReportKey)
 
                                            @foreach( $finalResult[$currentCurrencyName][$mainReportKey] ?? [] as $parentKeyName => $subRows)
                                            @php
                                            $customerName = $parentKeyName ;
                                            $hasSubRows = true;
                                            if($parentKeyName == __('Customers Past Due Invoices') || $parentKeyName =='Customers Past Due Invoices'
                                            || $parentKeyName == __('Suppliers Past Due Invoices') || $parentKeyName =='Suppliers Past Due Invoices'
                                            || $parentKeyName == __('Loan Past Due Installments') || $parentKeyName =='Loan Past Due Installments'
                                            || $parentKeyName == __('Net Cash (+/-)')
                                            || $parentKeyName == __('Accumulated Net Cash (+/-)')

                                            || $parentKeyName == __('Total Cash Inflow') || $parentKeyName == __('Total Cash Outflow')
                                            ){
                                            $hasSubRows = false ;
                                            }
                                            $rowIndex = $rowIndex+ 1;
                                            $subRowKeys = HArr::removeKeyFromArrayByValue(array_keys($finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName] ?? []),['total']);
											$isTotalRow = true ;
                                            @endphp
                                            
					 <tr class=" @if($customerName == __('Total Cash Inflow') || $customerName == __('Total Cash Outflow') ||  $customerName == __('Total Cash')) bg-lighter @else  @endif  parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   " data-model-id="{{ $rowIndex }}">
                                    <td class="red reset-table-width text-nowrap @if($hasSubRows) trigger-child-row-1 cursor-pointer @endif sub-text-bg text-capitalize main-tr is-close"> @if($hasSubRows) + @endif  </td>
                                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ $customerName }}</td>
                                    <td class="  sub-numeric-bg text-center editable-date"> 
									
									
										
										@if($customerName == __('Customers Past Due Invoices'))
										<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName"  :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'CustomerInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueCustomerInvoices[$currentCurrencyName]??[]" :id="'test-modal-id'"></x-modal.due-invoices>
										@endif 
										
											@if($customerName == 'Suppliers Past Due Invoices')
												<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'SupplierInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueSupplierInvoices" :id="'test-modal-id'"></x-modal.due-invoices>
										
											@endif 
												@if($customerName == 'Loan Past Due Installments')
												<button   class="btn btn-sm btn-danger text-white js-show-loan-past-due-installment-modal">{{ __('View') }}</button>
                                                <x-modal.loan-installment  :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval"  :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueInstallments" :id="'test-modal-id'"></x-modal.loan-installment>
											@endif 
											
									
									 </td>
									 @php
											$currentMainRowTotal = 0;
									 @endphp
                                    @foreach($weeks as $weekAndYear => $week)
                                    @php
								
									$year = explode('-',$weekAndYear)[1];
									
                                    $currentValue = 0 ;
									
									if($customerName == 'Total Cash Inflow'){
										$currentValue =  array_sum(array_column($allMainRowsTotals,$weekAndYear)) ;
										$finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear]  = $currentValue;
									}
									if($customerName == 'Total Cash Outflow'){
										$currentValue = array_sum(array_column(\App\Helpers\HArr::sliceArrayKeyToEnd($allMainRowsTotals,'Total Cash Inflow'),$weekAndYear)); // هنجيب من بعد الكي دا لحد اخر كي قبل الكاش اوت يبقي دول هما الكاش اوت توتال 
										$finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear]  = $currentValue;
										$totalCashInForWeek = $finalResult[$currentCurrencyName]['customers']['Total Cash Inflow']['weeks'][$weekAndYear]??0;
										$netCashAtWeek = $totalCashInForWeek - $currentValue;
										$finalResult[$currentCurrencyName][$mainReportKey]['Net Cash (+/-)']['weeks'][$weekAndYear] = $netCashAtWeek;
										$finalResult[$currentCurrencyName][$mainReportKey]['Accumulated Net Cash (+/-)']['weeks'][$weekAndYear] = array_sum($finalResult[$currentCurrencyName][$mainReportKey]['Net Cash (+/-)']['weeks']);
									}	
									if($customerName == 'Net Cash (+/-)'){
									}
									
									if(isset($finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear]))
									{
										
										$currentValue = $finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear];
									//	$currentMainRowTotal += $currentValue;
									}
									if(isset($isTotalRow) && isset($finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['total'][$weekAndYear])){
										$currentValue = $finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['total'][$weekAndYear];
										$currentMainRowTotal += $currentValue;
										
									}
									if($customerName == __('Customers Past Due Invoices') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($customerDueInvoices[$currentCurrencyName] ?? [], function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
										
									}
									if($customerName == __('Suppliers Past Due Invoices') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($supplierDueInvoices, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
									}
									if($customerName == __('Loan Past Due Installments') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
											$filtered = array_filter($pastDueLoanInstallments, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
									}
								
										
											$allMainRowsTotals[$customerName][$weekAndYear] = isset($allMainRowsTotals[$customerName][$weekAndYear]) ? $allMainRowsTotals[$customerName][$weekAndYear] + $currentValue :$currentValue ; // important place
									if($customerName == 'Total Cash Inflow'){
											
										}else{
											if($customerName=='Cash & Banks Balance'){
												
											}
										}
										
                                    @endphp
									
                                    <td  data-id="{{ $currentValue }}" class="  sub-numeric-bg text-center editable-date">{{ number_format($currentValue,0) }}
								
										
									</td>
                                    @endforeach
									@php
											
										
									 if($customerName == 'Accumulated Net Cash (+/-)'){
										
										$currentMainRowTotal = 0;
									}
									@endphp
                                   
                                    <td class="  sub-numeric-bg text-center editable-date">
									{{ number_format(  $currentMainRowTotal ) }}
								
									 </td>

                                </tr>
								
				
					
					
											
											
											
											
											
											
											
											
											
                                            @foreach($subRowKeys as $currentSubRowKeyName)
                                            {{-- @foreach(['Outgoing Transfers','Cash Payments','Paid Payable Cheques','Under Payment Payable Cheques','Suppliers Invoices'] as $currentSupplierKeyName) --}}
                                            @include('admin.reports.cash-flow-sub-row',['result'=>$finalResult[$currentCurrencyName]])
                                            @endforeach
                                            @endforeach

                                            @endforeach

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
					
					@push('js')
                    <script>
                        var table = $(".kt_table_with_no_pagination_no_collapse{{ $tableId }}");
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

                            table.DataTable().columns.adjust()

                        });



                        








                       
                        



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
                                        currentTable = $('.main-table-class{{ $tableId }}').DataTable();
                                    }
                                    $('.buttons-html5').addClass('btn border-parent btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                                    $('.buttons-print').addClass('btn border-parent top-0 btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')

                                },





                            }

                        )

                    </script>
					
		


                    @endpush
					
					@endforeach 
				
				

                    @include('projection-out',['currentTitle'=>__('Projected Other Cash In Items'),'currentTabId'=>'projection-in','projectionType'=>'in','repeaterId'=>'m_repeater_6'])
                    @include('projection-out',['currentTitle'=>__('Projected Other Cash Out Items'),'currentTabId'=>'projection-out','projectionType'=>'out','repeaterId'=>'m_repeater_7'])

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
						
window.addEventListener('scroll', function() {
                            const top = window.scrollY > 140 ? window.scrollY : 140;

                            $('.arrow-nav').css('top', top + 'px')
                        })
    function getDateFormatted(yourDate) {
        const offset = yourDate.getTimezoneOffset()
        yourDate = new Date(yourDate.getTime() - (offset * 60 * 1000))
        return yourDate.toISOString().split('T')[0]
    }



$(document).on('click', '.js-show-customer-due-invoices-modal', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.modal-item-js').modal('show')
    })
	
	
	
$(document).on('click', '.js-show-loan-past-due-installment-modal', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.modal-item-js').modal('show')
    })

$(document).on('click', '.repeat-to-right', function () {
	let columnIndex = parseInt($(this).attr('data-column-index'))
	let parent = $(this).closest('tr')
	let name = $(this).attr('data-name')
	let numberFormatDecimalsForCurrentRow = parent.attr('data-repeat-formatting-decimals')
	numberFormatDecimalsForCurrentRow = numberFormatDecimalsForCurrentRow ? numberFormatDecimalsForCurrentRow : 0
	let input = parent.find('.repeat-to-right-input-formatted[data-column-index="' + columnIndex + '"][data-name="' + name + '"]')
	let numberOfDecimalsForCurrentInput = $(input).attr('data-number-of-decimals')
	numberOfDecimalsForCurrentInput = numberOfDecimalsForCurrentInput == undefined ? numberFormatDecimalsForCurrentRow : numberOfDecimalsForCurrentInput
	let inputValue = input.val()
	inputValue = number_unformat(inputValue)
	let totalPerYear = 0
	$(this).closest('tr').find('.repeat-to-right-input-formatted[data-name="' + name + '"]').each(function (index, inputFormatted) {
		let currentColumnIndex = $(inputFormatted).attr('data-column-index')
		if (currentColumnIndex >= columnIndex) {
			totalPerYear += parseFloat(inputValue)
			$(inputFormatted).val(number_format(inputValue, numberOfDecimalsForCurrentInput)).trigger('change')
		}
	})
})
$('.repeat-to-right-input-hidden').on('change', function () {
	const val = $(this).val()
	const columnIndex = $(this).attr('data-column-index')
	const numberOfDecimals = $(this).closest('.input-hidden-parent').find('.copy-value-to-his-input-hidden[data-column-index="' + columnIndex + '"]').attr('data-number-of-decimals')
	$(this).closest('.input-hidden-parent').find('.copy-value-to-his-input-hidden[data-column-index="' + columnIndex + '"]').val(number_format(val, numberOfDecimals))
})
$(document).on('click', '.repeat-select-to-right', function () {
	let columnIndex = parseInt($(this).attr('data-column-index'))
	let parent = $(this).closest('tr')
	let value = parent.find('.repeat-to-right-select[data-column-index="' + columnIndex + '"]').val()
	$(this).closest('tr').find('.repeat-to-right-select').each(function (index, select) {
		if ($(select).attr('data-column-index') >= columnIndex) {
			$(select).val(value).trigger('change')
		}
	})

})

$(document).on('change', '.input-hidden-parent .copy-value-to-his-input-hidden', function () {
	let val = $(this).val()
	$(this).closest('.input-hidden-parent').find('input.input-hidden-with-name').val(number_unformat(val)).trigger('change')
})



function convertDateToDefaultDateFormat(dateStr) {
	const [month, day, year] = dateStr.split("/") // Split the string by "/";
	return `${year}-${month}-${day}` // Rearrange to YYYY-MM-DD
}
function getEndOfMonth(year, month) {
	// قم بإنشاء تاريخ لأول يوم من الشهر التالي
	let date = new Date(year, month + 1, 0)
	return date
}



$(document).on('click', '.collapse-before-me', function () {

	let columnIndex = $(this).attr('data-column-index')
	hide = true
	let counter = 0
	while (hide) {
		if (counter != 0) {

			if ($(this).closest('table').find('th[data-column-index="' + columnIndex + '"]').hasClass('exclude-from-collapse')) {
				hide = false
				return
			}
		}

		$(this).closest('table').find('[data-column-index="' + columnIndex + '"]:not(.exclude-from-collapse)').toggle()

		columnIndex--
		counter++
		if (counter == 12) {
			hide = false
		}
	}
})
$(document).on('change', '.repeater-with-collapse-input', function () {
	let groupIndex = $(this).attr('data-group-index')
	let total = 0
	$(this).closest('tr').find('input[data-group-index="' + groupIndex + '"]').each(function (index, element) {
		total += parseFloat($(element).val())
	})
	$(this).closest('tr').find('.year-repeater-index-' + groupIndex).val(number_format(total)).trigger('change')
})
$('input[type="hidden"].exclude-from-collapse').on('change', function () {
	var total = 0
	$(this).closest('tr').find('.repeat-group-year').each(function (index, element) {
		total += parseFloat(number_unformat($(element).val()))
	})

	$(this).closest('tr').find('.total-td').val(number_format(total)).trigger('change')
})
$(document).on('click', '.add-btn-js', function (e) {
	e.preventDefault()
	$(this).toggleClass('rotate-180')
	$(this).closest('[data-is-main-row]').nextUntil('[data-is-main-row]').toggleClass('hidden')
})
$(document).on('change','.is-fully-funded-checkbox',function(){
	const value = parseInt($(this).val());
	const canViewFundingStructure = parseInt($('#toggleEditBtn').attr('can-show-funding-structure'));


	$('#ffe-funding').hide();
	if(value){
		$('#ffe-funding').hide();
		$('#toggleEditBtn').hide();
		$('#save-and-go-to-next').show();
	
	}else{
		if(canViewFundingStructure){
			$('#ffe-funding').show();
		}
		$('#save-and-go-to-next').hide();
		$('#toggleEditBtn').show();
	
		
	}
	if(canViewFundingStructure){
		$('#save-and-go-to-next').show();
	}
	
});
$('.is-fully-funded-checkbox:checked').trigger('change');
$(document).on('change','.recalculate-monthly-increase-amounts',function(){
	var currentRow = $(this).closest('tr') ;
	var itemCost = currentRow.find('.ffe-item-cost').val();
	// var vat = currentRow.find('dd');
	var costAnnuallyIncreaseRate = currentRow.find('.cost-annually-increase-rate').val() / 100;
	var contingencyRate = currentRow.find('.contingency-rate').val() / 100;
	
	var yearIndex = -1 ; ;
	currentRow.find('.ffe_counts').each(function(index,ffeCountElement){
		var currentYearIndex = parseInt($(ffeCountElement).attr('data-current-year-index'));
		var currentMonthIndex=$(ffeCountElement).attr('data-column-index');
		if(currentYearIndex != yearIndex){
			yearIndex++;
		}
		var currentCount = $(ffeCountElement).val();
		var currentTotalAmount = itemCost * currentCount  * (1+contingencyRate); 
		var currentTotalAmountIncrease = currentTotalAmount * Math.pow(1 + costAnnuallyIncreaseRate, yearIndex)
	
		$(ffeCountElement).closest('td').find('.current-month-amounts').val(currentTotalAmountIncrease);
		var totalForCurrentMonth = 0 ;
		$('.current-month-amounts[data-column-index="'+currentMonthIndex+'"]').each(function(index,amountElement){
			totalForCurrentMonth+= parseFloat($(amountElement).val());
		})
		$('.direct-ffe-amounts[data-column-index="'+currentMonthIndex+'"]').val(number_format(totalForCurrentMonth)).trigger('change');
		
	})
	
})
let calculateBranchIncreaseAmounts = function(){
	var currentRow = $(this).closest('tr') ;
	var itemCost = parseFloat(currentRow.find('.ffe-item-cost').val());
	itemCost = itemCost ? itemCost : 0 ;
	var costAnnuallyIncreaseRate = currentRow.find('.cost-annually-increase-rate').val() / 100;
	costAnnuallyIncreaseRate = costAnnuallyIncreaseRate ? costAnnuallyIncreaseRate : 0 ;
	var contingencyRate = currentRow.find('.contingency-rate').val() / 100;
	contingencyRate = contingencyRate ? contingencyRate : 0;
	var currentItemCount = parseInt(currentRow.find('.current-count').val());
	currentItemCount = currentItemCount ? currentItemCount : 0;
	var yearIndex = -1 ; // will increase every year ;
	var  netBranchOpeningProjections = JSON.parse($('#net-branch-opening-projections').val());
	var counts = {};
	for(var currentDateAsIndex in netBranchOpeningProjections){
		var currentBranchCount = netBranchOpeningProjections[currentDateAsIndex];
		currentCount = currentBranchCount * currentItemCount;
		var currentYearIndex = $('.year-index-month-index[data-month-index="'+ currentDateAsIndex +'"]').attr('data-year-index');
		var currentMonthIndex=currentDateAsIndex;
		if(currentYearIndex != yearIndex){
			yearIndex++;
		}
		counts[currentMonthIndex]=currentCount;
		var currentTotalAmount = itemCost * currentCount  * (1+contingencyRate); 
		var currentTotalAmountIncrease = currentTotalAmount * Math.pow(1 + costAnnuallyIncreaseRate, yearIndex)
		$(currentRow).closest('tr').find('.current-month-amounts[data-column-index="'+currentMonthIndex+'"]').val(currentTotalAmountIncrease);
		var totalForCurrentMonth = 0 ;
		$('.current-month-amounts[data-column-index="'+currentMonthIndex+'"]').each(function(index,amountElement){
			var currentAmount = $(amountElement).val() ;
			currentAmount = currentAmount == undefined ? 0 : currentAmount ;
			totalForCurrentMonth+= parseFloat(currentAmount);
			
		})
		$('.direct-ffe-amounts[data-column-index="'+currentMonthIndex+'"]').val(number_format(totalForCurrentMonth)).trigger('change');
		
	}
	$(currentRow).find('.current-row-counts').val(JSON.stringify(counts));
}
$(document).on('change','.recalculate-monthly-increase-amounts-branches',calculateBranchIncreaseAmounts)
$('.recalculate-monthly-increase-amounts-branches').trigger('change');
$(document).on('change','select.department-class',function(){
	const departmentIds = $(this).val();
	const companyId = $('body').attr('data-current-company-id')
	const lang = $('body').attr('data-lang')
	let studyId = $('#study-id-js').val()
	const url = '/' + lang + '/' + companyId + '/non-banking-financial-services/study/' + studyId + '/get-positions-based-on-departments';
	var data = {
		departmentIds
	}
	$.ajax({
		url,
		data,
		success:(res)=>{
			var positionArr = res.positionIds ;
			var options ='';
			var positionRow = $(this).closest('tr').find('select.position-class');
			var currentSelected = JSON.parse($(positionRow).attr('data-current-selected-items'));
			for(var positionId in positionArr){
				positionId = positionId;
				var selected = currentSelected.includes(positionId);
				options+=`<option ${selected ? 'selected':''} value="${positionId}">${positionArr[positionId]}</option>`
			}
			$(positionRow).empty().append(options).trigger('change');
		}
	})
	
})
$(function(){
	$('select.department-class').trigger('change');
})


$(document).ready(function() {
    // Set table to readonly by default
	var inEditMode = parseInt($('#toggleEditBtn').attr('in-edit-mode'));
	if(inEditMode){
		$('#fixedAssets_repeater').addClass('readonly');
		const table = $('#fixedAssets_repeater');
		table.find('input, select').prop('readonly', true);
	}
    // Toggle editability
    $('#toggleEditBtn').click(function(e) {
		e.preventDefault();
        const table = $('#fixedAssets_repeater');
        const isReadonly = table.hasClass('readonly');
        

        if (isReadonly) {
			table.removeClass('readonly').addClass('editable');
			$(this).text('Disabled Editing');
			$(this).attr('can-show-funding-structure',0);
		//	$(this).attr('is-save-and-continue',1);
            // Enable all inputs and selects
			
				table.find('input, select').prop('readonly', false);
				//table.find('.bootstrap-select').removeClass('disabled');
			
        } else {
			table.removeClass('editable').addClass('readonly');

			$(this).text('Enable Editing');
	//		$(this).attr('is-save-and-continue',0);
			$(this).attr('can-show-funding-structure',1);
            // Disable all inputs and selects
		
				table.find('input, select').prop('readonly', true);
	
		
				
        }
		$('.is-fully-funded-checkbox:checked').trigger('change');
    });
    
    // Initially disable all inputs and selects
    // $('#fixedAssets_repeater').find('input, select').prop('readonly', true);
    // $('#fixedAssets_repeater').find('.bootstrap-select').addClass('readonly');
});

$(function(){
//	$('#toggleEditBtn').click();
})


</script>

@endsection
