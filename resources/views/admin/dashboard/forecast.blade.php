@extends('layouts.dashboard')
@section('dash_nav')
<style>
    .chartdiv {
        width: 100%;
        height: 250px;
    }

    .chartdivdonut {
        width: 100%;
        height: 500px;
    }

    .chartdivchart {
        width: 100%;
        height: 500px;
    }

</style>

@endsection
@section('css')
<link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
<style>
    table {
        white-space: nowrap;
    }

</style>
@endsection
@section('content')
<div class="money-flow-dark">

{{-- Title --}}
<div class="row">
    <div class="kt-portlet ">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{ __('Cash Flow') }}
                </h3>





            </div>

        </div>
        <div class="kt-portlet__body">
            <form action="">
                <div class="row ">
				
				<div class="col-md-2 mb-3">
                            <label>{{__('Report Interval')}} @include('star')</label>

                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="report_interval" class="form-control " required>
									     <option value="">{{ __('Select') }}</option>
                                        <option value="daily" @if($selectedReportInterval == 'daily' )  selected @endif>{{__('Daily')}}</option>
                                        <option value="weekly"  @if($selectedReportInterval == 'weekly' )  selected @endif>{{__('Weekly')}}</option>
                                        <option value="monthly" @if($selectedReportInterval == 'monthly' )  selected @endif>{{__('Monthly')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>



                        <div class="col-md-3">
                            <x-form.select :required="true" :label="__('Select')" :pleaseSelect="false" :selectedValue="$selectedPartnerId" :options="array_merge([['title'=>__('Company Cash Flow'),'value'=>'0']],formatOptionsForSelect($clientsWithContracts))" :add-new="false" class="select2-select suppliers-or-customers-js repeater-select  " data-filter-type="{{ 'create' }}" :all="false" name="partner_id"></x-form.select>
                        </div>
                        <div class="col-md-3">
                            <x-form.select :required="false" :label="__('Contract')" :pleaseSelect="false" data-current-selected="{{ $selectedContractId }}" :selectedValue="$selectedContractId" :options="[]" :add-new="false" class="select2-select  contracts-js repeater-select  " data-filter-type="{{ 'create' }}" :all="false" name="contract_id"></x-form.select>
                        </div>



                        <div class="col-md-2">
                            <label>{{__('Contract Code')}} @include('star')</label>
                            <div class="input-group">
                                <input disabled type="text" class="form-control contract-code" value="">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label>{{__('Contract Amount')}} @include('star')</label>
                            <div class="input-group">
                                <input disabled type="text" class="form-control contract-amount" value="0">
                            </div>
                        </div>
						
                    <div class="col-md-2 ">
                        <label>{{ __('Start Date') }} <span class="multi_selection"></span> </label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <input required type="date" class="form-control" name="cash_start_date" value="{{ $cashStartDate }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label>{{ __('End Date') }} <span class="multi_selection"></span> </label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <input required type="date" class="form-control" name="cash_end_date" value="{{ $cashEndDate }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="visibility-hidden"> {{__('dd')}}
                            @include('star')
                        </label>
                        <div class="input-group">
                            <button type="submit" class="btn active-style save-form">{{__('Save')}}</button>
                        </div>
                    </div>

                </div>
            </form>


            <div class="kt-portlet__body" style="padding-bottom:0 !important;">
                <ul style="margin-bottom:0 ;" class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                    @php
                    $index = 0 ;
                    // $selectedCurrencies = ['USD'=>'USD'];
                    @endphp


                    @foreach($selectedCurrencies as $currencyUpper=>$currency)

                    <li class="nav-item @if($index ==0 ) active @endif">
                        <a class="nav-link @if($index ==0 ) active @endif" data-toggle="tab" href="#kt_apps_contacts_view_tab_main{{ $index }}" role="tab">
                            <i class="flaticon2-checking icon-lg"></i>
                            <span style="font-size:18px !important;">{{ $currency }}</span>
                        </a>
                    </li>

                    @php
                    $index++;
                    @endphp
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</div>
{{-- Multi Line Chart --}}

<div class="tab-content  kt-margin-t-20">
    @php
    $index = 0 ;
    @endphp
    @foreach($selectedCurrencies as $name=>$currency)
    <div class="tab-pane  @if($index == 0) active @endif" id="kt_apps_contacts_view_tab_main{{ $index }}" role="tabpanel">
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __('Monthly Cash Flow') }}
                        </h3>
                    </div>
                    <div class="kt-portlet__head-label ">
                        <div class="kt-align-right">
						
						<div class="parent-item d-inline-block">
											<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('Customer Past Dues INV') }}</button>
                                                <x-modal.due-invoices :flowReportId="-1" :contractCode="$contractCode" :currencyName="$currencyName"  :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'CustomerInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueCustomerInvoices[$currentCurrencyName]??[]" :id="'test-modal-id'"></x-modal.due-invoices>
						</div>
						<div class="parent-item d-inline-block">
												<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('Supplier Past Dues INV') }}</button>
                                                <x-modal.due-invoices :flowReportId="-1" :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'SupplierInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueSupplierInvoices" :id="'test-modal-id'"></x-modal.due-invoices>
						
						</div>
						{{-- <div class="parent-item d-inline-block">
												<button   class="btn btn-sm btn-danger text-white js-show-loan-past-due-installment-modal">{{ __('View') }}</button>
                                                <x-modal.loan-installment :flowReportId="-1"  :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval"  :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueInstallments" :id="'test-modal-id'"></x-modal.loan-installment>
						</div> --}}



                            <button type="button" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>
							

                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="chartdivchart" id="chartdivmulti{{ $currency }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Single Line Chart --}}
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __('Accumulated Cash Flow') }}
                        </h3>
                    </div>
                    <div class="kt-portlet__head-label ">
                        <div class="kt-align-right">
                            <button type="button" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    {{-- Chart --}}
                    <div class="row">
                        <div class="col-md-12">
                            <div class="chartdivchart" id="chartdivline1{{ $currency }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Title --}}
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __("Receivables & Payables Aging ") }}
							{{ __('Date As Of ') }} [ {{ now()->format('d-m-Y') }} ] 
                        </h3>
                    </div>
                </div>
            </div>
        </div>


        @foreach($invoiceTypesModels as $modelType)
		@php
			$modelTypeAsText = [
				'SupplierInvoice'=>__('Suppliers Invoices'),
				'CustomerInvoice'=>__('Customers Invoices')
			][$modelType] ;
		@endphp
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{$modelTypeAsText. __(' Aging') }}
                        </h3>
                    </div>
                    <div class="kt-portlet__head-label ">
                        <div class="kt-align-right">
							<form action="{{ route('result.aging.analysis',['company'=>$company->id ,'modelType'=>$modelType ]) }}" method="post">
								@csrf
								<input type="hidden" name="currency" value="{{  $currency }}" >
								
	                           	 <button target="_blank" type="submit" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>
							</form>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    {{-- Chart --}}
                    <div class="row">
                        <div class="col-md-4">
                            <table class="table table-sm  table-head-bg-brand ">
                                <thead class="thead-inverse">
                                    <tr>
                                        <th>{{ __('Invoices Aging') }}</th>
                                        <th class="text-center">{{ __('Invoices Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $total = 0 ;
                                    @endphp
                                    @foreach ($dashboardResult['invoices_aging'][$modelType][$currency]['table'] ?? [] as $dueType => $dueWithValue)
                                    @foreach($dueWithValue as $daysInternal => $totalForDaysInterval)
                                    <tr>
                                        <td>{{ camelizeWithSpace($dueType,'_') }} {{ $daysInternal }} {{ __('Days') }} </td>
                                        <td class="text-center">{{ number_format($totalForDaysInterval,0) }}</td>
                                    </tr>
                                    @php
                                    $total += $totalForDaysInterval ;
                                    @endphp
                                    @endforeach
                                    @endforeach

                                    <tr>
                                        <td>{{ __('Total') }}</td>
                                        <td class="text-center">{{ number_format($total,0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-8">
                            <div class="chartdivchart" id="chartdiv__{{ $modelType.$currency }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        {{-- Customers Cheques Aging --}}
		@php
			$currentItems = $dashboardResult['cheques_aging_for_table'][$modelType][$currency]['table'] ?? [];
		@endphp
		@if(count($currentItems))
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            @if($modelType == 'CustomerInvoice')
                            {{ __('Customers Cheques Aging') }}
                            @else
                            {{ __('Suppliers Cheques Aging') }}
                            @endif
                        </h3>
                    </div>
                    <div class="kt-portlet__head-label ">
                        <div class="kt-align-right">
                            <button type="button" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>
                            <button type="button" class="btn btn-sm btn-pill color-rose"><i class="fa fa-chart-line"></i> {{ __('Rejected Cheques Report') }} </button>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    {{-- Chart --}}
                    <div class="row">
                        <div class="col-md-4">
                            <table class="table table-sm  table-head-bg-brand ">
                                <thead class="thead-inverse">
                                    <tr>
                                        <th>{{ __('Cheques Aging') }}</th>
                                        <th class="text-center">{{ __('Cheques Amount') }}</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $total = 0 ;
                                    @endphp
									
                                    @foreach ($currentItems as $dueType => $dueWithValue)
                                    @if($dueType == 'coming_due' || $dueType =='current_due')
                                    @foreach($dueWithValue as $daysInternal => $totalForDaysInterval)
                                    <tr>
                                        <td>{{ camelizeWithSpace($dueType,'_') }} {{ $daysInternal }} {{ __('Days') }} </td>
                                        <td class="text-center">{{ number_format($totalForDaysInterval,0) }}</td>
                                    </tr>
                                    @php
                                    $total += $totalForDaysInterval ;
                                    @endphp
                                    @endforeach
                                    @endif
                                    @endforeach
                                    <tr>
                                        <td>{{ __('Total') }}</td>
                                        <td class="text-center">{{ number_format($total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-8">
			
                            <div class="chartdivchart" id="chartdivline2_{{ $modelType.$currency }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		@endif

        @endforeach














        {{-- Title --}}
        <div class="row">
            <div class="kt-portlet ">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __("Long & Short Term Facilities Comming Dues ") }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <form method="post" action="{{ route('result.withdrawals.settlement.report',['company'=>$company->id ]) }}">
            <div class="row">
                {{-- Withdrawal dues --}}
                <div class="col-md-6">
                    <div class="kt-portlet ">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{ __('Withdrawal dues') }}
                                </h3>
								
                            </div>
							<div class="kt-portlet__head-label" style="gap:25px;margin-top:10px;">
									<div class="form-group">
										<label for="" class="label">{{ __('Start') }}</label>
										<input js-refresh-withdrawal-due-data-and-chart type="date" data-currency="{{ $currency }}" class="form-control withdrawal-start-date" name="withdrawal_start_date" value="{{ $withdrawalStartDate }}">
									</div>
									<div class="form-group">
										<label for="" class="label">{{ __('End') }}</label>
                                    <input js-refresh-withdrawal-due-data-and-chart type="date" data-currency="{{ $currency }}" class="form-control withdrawal-end-date" name="withdrawal_end_date" value="{{ $withdrawalEndDate }}">
									</div>
								</div>
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">

                                    @csrf
	
                                    <input type="hidden" name="currency" value="{{ $currency }}">
                                    @foreach($allFinancialInstitutionIds as $allFinancialInstitutionId)
                                    <input type="hidden" name="financial_institution_ids[]" value="{{ $allFinancialInstitutionId }}">
                                    @endforeach

                                    <button type="submit" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>

                                </div>



                            </div>


                        </div>


                        <div class="kt-portlet__body">
                            {{-- Chart --}}
                            <div class="row">
                                <div class="col-md-10 mb-3">
                                    <select name="account_type" data-currency="{{ $currency }}" js-refresh-withdrawal-due-data-and-chart class="form-control withdrawal-account-type-js">
                                        @foreach($overdraftAccountTypes as $overdraftAccountType)
                                        <option @if($overdraftAccountType->isCleanOverdraftAccount() ) selected @endif value="{{ $overdraftAccountType->id }}">{{ $overdraftAccountType->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-sm  table-head-bg-brand ">
                                                <thead class="thead-inverse">
                                                    <tr>
                                                        <th>{{ __('Date') }}</th>
                                                        <th class="text-center">{{ __('Amount') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="append-withdrawal-due-{{ $currency }}">
                                                    
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="chartdivchart" id="withdrawal-dues-chart-{{ $currency }}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
                {{-- Long Term Facilities Comming Dues --}}
                <div class="col-md-6">
                    <div class="kt-portlet ">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{ __('Long Term Facilities Comming Dues') }}
                                </h3>
                            </div>
							<div class="kt-portlet__head-label" style="gap:25px;margin-top:10px;">
									<div class="form-group">
										<label for="" class="label">{{ __('Start') }}</label>
										<input data-currency="{{ $currency }}" js-refresh-medium-term-loan-chart type="date" class="form-control loan-start-date" name="loan_start_date" value="{{ $loanStartDate }}">
									</div>
									<div class="form-group">
										<label for="" class="label">{{ __('End') }}</label>
                                    <input data-currency="{{ $currency }}" type="date" js-refresh-medium-term-loan-chart class="form-control loan-end-date" name="loan_end_date" value="{{ $loanEndDate }}">
									</div>
								</div>
								
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">
                                    <button type="button" class="btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </button>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            {{-- Chart --}}
                            <div class="row">
 								<div class="col-md-8 mb-3">
                                    <select name="financial_institution_id" data-currency="{{ $currency }}" js-refresh-medium-term-loan-chart class="form-control financial-instutiton-js">
									<option value="0"> {{ __('All') }} </option>
                                        @foreach(($financialInstitutionsByCurrency[$currency] ?? collect()) as $financialInstitutionsThatHaveMediumTermLoan)
                                        <option value="{{ $financialInstitutionsThatHaveMediumTermLoan->id }}">{{ $financialInstitutionsThatHaveMediumTermLoan->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
								
								 <div class="col-md-4 mb-3">
                                    <select name="medium_term_loan_id" data-currency="{{ $currency }}" js-refresh-medium-term-loan-chart class="form-control medium-term-loan-js">
									<option value="0"> {{ __('All') }} </option>
                                    </select>
                                </div>
								
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-sm  table-head-bg-brand ">
                                                <thead class="thead-inverse">
                                                    <tr>
                                                        <th>{{ __('Date') }}</th>
                                                        <th class="text-center">{{ __('Amount') }}</th>

                                                    </tr>
                                                </thead>
                                                <tbody id="append-loan-{{ $currency }}">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="row">
									{{-- <div class="chartdivchart" id="withdrawal-dues-chart-{{ $currency }}"></div> --}}
                                        <div class="chartdivchart" id="loan-chart-{{ $currency }}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
              
            </div>
        </form>
    </div>

    @php
    $index++;
    @endphp
    @endforeach

</div>
</div>
@endsection
@section('js')
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<!-- Resources: loaded asynchronously so amCharts never blocks HTML parsing/paint -->
<script>
    (function () {
        window.__forecastChartQueue = [];
        window.__amChartsReady = false;
        // Chart data that arrives (via ajax) before its chart sprite has been
        // created yet (since amCharts now loads/initializes asynchronously).
        window.__forecastPendingChartData = window.__forecastPendingChartData || {};

        // Chart-creation blocks below call this instead of am4core.ready() so
        // (a) they never block on the CDN fetch and (b) creation is staggered
        // across idle time-slices instead of all running on one tick.
        window.queueForecastChart = function (fn) {
            window.__forecastChartQueue.push(fn);
            if (window.__amChartsReady) {
                processForecastChartQueue();
            }
        };

        function runOne(fn) {
            try {
                fn();
            } catch (e) {
                console.error('Forecast chart init failed', e);
            }
        }

        function processForecastChartQueue() {
            var queue = window.__forecastChartQueue;
            window.__forecastChartQueue = [];
            queue.forEach(function (fn, index) {
                if (window.requestIdleCallback) {
                    window.requestIdleCallback(function () { runOne(fn); }, { timeout: 2000 });
                } else {
                    setTimeout(function () { runOne(fn); }, index * 30);
                }
            });
        }

        function loadScript(src, cb) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = cb;
            s.onerror = cb;
            document.head.appendChild(s);
        }

        loadScript('https://cdn.amcharts.com/lib/4/core.js', function () {
            loadScript('https://cdn.amcharts.com/lib/4/charts.js', function () {
                loadScript('https://cdn.amcharts.com/lib/4/themes/animated.js', function () {
                    window.__amChartsReady = true;
                    processForecastChartQueue();
                });
            });
        });
    })();
</script>

<script>
    var ammount_array = [{
        "date": "2012-07-27"
        , "value": 13
    }, {
        "date": "2012-07-28"
        , "value": 11
    }, {
        "date": "2012-07-29"
        , "value": 15
    }, {
        "date": "2012-07-30"
        , "value": 16
    }, {
        "date": "2012-07-31"
        , "value": 18
    }, {
        "date": "2012-08-01"
        , "value": 13
    }, {
        "date": "2012-08-02"
        , "value": 22
    }, {
        "date": "2012-08-03"
        , "value": 23
    }, {
        "date": "2012-08-04"
        , "value": 20
    }, {
        "date": "2012-08-05"
        , "value": 17
    }, {
        "date": "2012-08-06"
        , "value": 16
    }, {
        "date": "2012-08-07"
        , "value": 18
    }, {
        "date": "2012-08-08"
        , "value": 21
    }, {
        "date": "2012-08-09"
        , "value": 26
    }, {
        "date": "2012-08-10"
        , "value": 24
    }, {
        "date": "2012-08-11"
        , "value": 29
    }, {
        "date": "2012-08-12"
        , "value": 32
    }, {
        "date": "2012-08-13"
        , "value": 18
    }, {
        "date": "2012-08-14"
        , "value": 24
    }, {
        "date": "2012-08-15"
        , "value": 22
    }, {
        "date": "2012-08-16"
        , "value": 18
    }, {
        "date": "2012-08-17"
        , "value": 19
    }, {
        "date": "2012-08-18"
        , "value": 14
    }, {
        "date": "2012-08-19"
        , "value": 15
    }, {
        "date": "2012-08-20"
        , "value": 12
    }, {
        "date": "2012-08-21"
        , "value": 8
    }, {
        "date": "2012-08-22"
        , "value": 9
    }, {
        "date": "2012-08-23"
        , "value": 8
    }, {
        "date": "2012-08-24"
        , "value": 7
    }, {
        "date": "2012-08-25"
        , "value": 5
    }, {
        "date": "2012-08-26"
        , "value": 11
    }];

</script>
{{-- AmCharts 4: light axis/legend/tooltip text on dark dashboard --}}
<script>
    window.applyForecastChartDarkTheme = function (chart, opts) {
        opts = opts || {};
        var text = am4core.color(opts.labelColor || '#ffffff');
        var grid = am4core.color(opts.gridColor || 'rgba(255, 255, 255, 0.12)');
        var keepYLabelColors = opts.keepYAxisLabelColors === true;
        chart.xAxes.each(function (axis) {
            axis.renderer.labels.template.fill = text;
            axis.renderer.grid.template.stroke = grid;
            if (axis.renderer.line) {
                axis.renderer.line.stroke = grid;
            }
        });
        chart.yAxes.each(function (axis) {
            if (!keepYLabelColors) {
                axis.renderer.labels.template.fill = text;
            }
            axis.renderer.grid.template.stroke = grid;
        });
        if (chart.legend && chart.legend.labels && chart.legend.labels.template) {
            chart.legend.labels.template.fill = text;
        }
        if (chart.legend && chart.legend.valueLabels && chart.legend.valueLabels.template) {
            chart.legend.valueLabels.template.fill = text;
        }
        chart.series.each(function (series) {
            if (!series.tooltip || !series.tooltip.label) {
                return;
            }
            series.tooltip.getFillFromObject = false;
            series.tooltip.background.fill = am4core.color('#1e293b');
            series.tooltip.background.stroke = am4core.color('#475569');
            series.tooltip.label.fill = text;
        });
        if (chart.cursor) {
            chart.cursor.lineX.strokeOpacity = 0.6;
            chart.cursor.lineY.strokeOpacity = 0.6;
            chart.cursor.lineX.stroke = text;
            chart.cursor.lineY.stroke = text;
        }
    };
</script>
@foreach($selectedCurrencies as $currencyUpper=>$currency)
@foreach($invoiceTypesModels as $modelType)
<!-- Chart code -->
<script>
    queueForecastChart(function() {
        am4core.useTheme(am4themes_animated);
        var chart = am4core.create("chartdiv__{{ $modelType.$currency }}", am4charts.XYChart);



        chartData = @json(($dashboardResult['invoices_aging'][$modelType][$currency]['chart'] ?? []));
        chartData = chartData.reverse()

        chart.data = chartData;

        // Create axes
        var yAxis = chart.yAxes.push(new am4charts.CategoryAxis());
        yAxis.dataFields.category = "state";
        yAxis.renderer.grid.template.location = 0;
        yAxis.renderer.labels.template.fontSize = 10;
        yAxis.renderer.minGridDistance = 10;

        var xAxis = chart.xAxes.push(new am4charts.ValueAxis());

        // Create series
        var series = chart.series.push(new am4charts.ColumnSeries());
        series.dataFields.valueX = "sales";
        series.dataFields.categoryY = "state";
        series.columns.template.tooltipText = "{categoryY}: [bold]{valueX}[/]";
        series.columns.template.strokeWidth = 0;
        series.columns.template.adapter.add("fill", function(fill, target) {
            if (target.dataItem) {
                switch (target.dataItem.dataContext.region) {

                    case "Past Due":
                        return "#C70039";
                    case "Coming Due":
                        return "#1D9D23";
                    case "Current Due":
                        return "#94a3b8";
                }
            }
            return fill;
        });

        var axisBreaks = {};
        var legendData = [];


       

        let groups = [];
        for (i = 0; i < chartData.length; i++) {
            var currentCategory = chartData[i].region;
            var currentState = chartData[i].state;

            var currentCategoryExist = groups.find(element => {
                if (element.name == currentCategory) {
                    return true;
                }
            })


            if (currentCategoryExist) {
                var index = groups.findIndex(obj => obj.name == currentCategory)
                groups[index].last_due = currentState
            } else {
                currentState = null
                if (currentCategory == 'Coming Due') {
                    currentState = getLastAppearanceOfKeyInObject(chartData, 'Coming Due');
                 
                }
                if (currentCategory == 'Past Due') {
                    currentState = getLastAppearanceOfKeyInObject(chartData, 'Past Due');
                }
                if (currentCategory == 'Current Due') {
                    currentState = '0 Days';
                }

                groups.push({
                    name: currentCategory
                    , first_due: currentState
                    , last_due: currentState
                })
            }
        }


        for (var i = 0; i < groups.length; i++) {
            var color = '#94a3b8';
            if (groups[i].name == "{{ __('Coming Due') }}") { // coming due 
                color = '#1D9D23';
            }
            if (groups[i].name == "{{ __('Current Due') }}") {
                color = '#94a3b8';
            }
            if (groups[i].name == "{{ __('Past Due') }}") {
                color = '#C70039'
            }


        }
        chart.cursor = new am4charts.XYCursor();
        var legend = new am4charts.Legend();
        legend.position = "bottom";
        legend.scrollable = true;
        legend.valign = "top";
        legend.reverseOrder = true;

        chart.legend = legend;

        legend.data = legendData;

        window.applyForecastChartDarkTheme(chart);

    }); 

</script>

<script>
    queueForecastChart(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("chartdivline2_{{ $modelType.$currency }}", am4charts.XYChart);

        // Add data

        var chartData = @json(($dashboardResult['cheques_aging_for_chart'][$modelType][$currency] ?? []));
        chart.data = chartData;
        // Set input format for the dates
        chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        // Create axes
        var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        // Create series
        var series = chart.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = "value";
        series.dataFields.dateX = "date";
        series.tooltipText = "{value}"
        series.strokeWidth = 2;
        series.minBulletDistance = 15;

        // Drop-shaped tooltips
        series.tooltip.background.cornerRadius = 20;
        series.tooltip.background.strokeOpacity = 0;
        series.tooltip.pointerOrientation = "vertical";
        series.tooltip.label.minWidth = 40;
        series.tooltip.label.minHeight = 40;
        series.tooltip.label.textAlign = "middle";
        series.tooltip.label.textValign = "middle";

        // Make bullets grow on hover
        var bullet = series.bullets.push(new am4charts.CircleBullet());
        bullet.circle.strokeWidth = 2;
        bullet.circle.radius = 4;
        bullet.circle.fill = am4core.color("#fff");

        var bullethover = bullet.states.create("hover");
        bullethover.properties.scale = 1.3;

        // Make a panning cursor
        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = "panXY";
        chart.cursor.xAxis = dateAxis;
        chart.cursor.snapToSeries = series;

        // Create vertical scrollbar and place it before the value axis
        chart.scrollbarY = new am4core.Scrollbar();
        chart.scrollbarY.parent = chart.leftAxesContainer;
        chart.scrollbarY.toBack();

        // Create a horizontal scrollbar with previe and place it underneath the date axis
        chart.scrollbarX = new am4charts.XYChartScrollbar();
        chart.scrollbarX.series.push(series);
        chart.scrollbarX.parent = chart.bottomAxesContainer;

        dateAxis.start = 0.79;
        dateAxis.keepSelection = true;

        window.applyForecastChartDarkTheme(chart);

    }); // end am4core.ready()

</script>



@endforeach



<script>
    queueForecastChart(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("chartdivline1{{ $currency }}", am4charts.XYChart);

        // Add data
        chart.data =  @json($cashFlowReport[$currency]['accumulated_net_cash'] ?? []);;

        // Set input format for the dates
        chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        // Create axes
        var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        // Create series
        var series = chart.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = "value";
        series.dataFields.dateX = "date";
        series.tooltipText = "{value}"
        series.strokeWidth = 2;
        series.minBulletDistance = 15;
		
        // Drop-shaped tooltips
        series.tooltip.background.cornerRadius = 20;
        series.tooltip.background.strokeOpacity = 0;
        series.tooltip.pointerOrientation = "vertical";
        series.tooltip.label.minWidth = 40;
        series.tooltip.label.minHeight = 40;
        series.tooltip.label.textAlign = "middle";
        series.tooltip.label.textValign = "middle";

        // Make bullets grow on hover
        var bullet = series.bullets.push(new am4charts.CircleBullet());
        bullet.circle.strokeWidth = 2;
        bullet.circle.radius = 4;
        bullet.circle.fill = am4core.color("#fff");

        var bullethover = bullet.states.create("hover");
        bullethover.properties.scale = 1.3;


        // Make a panning cursor
        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = "panXY";
        chart.cursor.xAxis = dateAxis;
        chart.cursor.snapToSeries = series;

        // Create vertical scrollbar and place it before the value axis
        chart.scrollbarY = new am4core.Scrollbar();
        chart.scrollbarY.parent = chart.leftAxesContainer;
        chart.scrollbarY.toBack();

        // Create a horizontal scrollbar with previe and place it underneath the date axis
        chart.scrollbarX = new am4charts.XYChartScrollbar();
        chart.scrollbarX.series.push(series);
        chart.scrollbarX.parent = chart.bottomAxesContainer;

        dateAxis.start = 0.79;
        dateAxis.keepSelection = true;

        window.applyForecastChartDarkTheme(chart);

    }); // end am4core.ready()

</script>


<script>
    queueForecastChart(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("withdrawal-dues-chart-{{ $currency }}", am4charts.XYChart);

        // Add data (may already have arrived via ajax before this chart existed)
        chart.data = window.__forecastPendingChartData["withdrawal-dues-chart-{{ $currency }}"] || [];

        // Set input format for the dates
        chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        // Create axes
        var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        // Create series
        var series = chart.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = "value";
        series.dataFields.dateX = "date";
        series.tooltipText = "{value}"
        series.strokeWidth = 2;
        series.minBulletDistance = 15;

        // Drop-shaped tooltips
        series.tooltip.background.cornerRadius = 20;
        series.tooltip.background.strokeOpacity = 0;
        series.tooltip.pointerOrientation = "vertical";
        series.tooltip.label.minWidth = 40;
        series.tooltip.label.minHeight = 40;
        series.tooltip.label.textAlign = "middle";
        series.tooltip.label.textValign = "middle";

        // Make bullets grow on hover
        var bullet = series.bullets.push(new am4charts.CircleBullet());
        bullet.circle.strokeWidth = 2;
        bullet.circle.radius = 4;
        bullet.circle.fill = am4core.color("#fff");

        var bullethover = bullet.states.create("hover");
        bullethover.properties.scale = 1.3;

        // Make a panning cursor
        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = "panXY";
        chart.cursor.xAxis = dateAxis;
        chart.cursor.snapToSeries = series;

        // Create vertical scrollbar and place it before the value axis
        chart.scrollbarY = new am4core.Scrollbar();
        chart.scrollbarY.parent = chart.leftAxesContainer;
        chart.scrollbarY.toBack();

        // Create a horizontal scrollbar with previe and place it underneath the date axis
        chart.scrollbarX = new am4charts.XYChartScrollbar();
        chart.scrollbarX.series.push(series);
        chart.scrollbarX.parent = chart.bottomAxesContainer;

        dateAxis.start = 0.79;
        dateAxis.keepSelection = true;

        window.applyForecastChartDarkTheme(chart);

    }); 

</script>


<script>
    queueForecastChart(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("loan-chart-{{ $currency }}", am4charts.XYChart);

        // Add data (may already have arrived via ajax before this chart existed)
        chart.data = window.__forecastPendingChartData["loan-chart-{{ $currency }}"] || [];

        // Set input format for the dates
        chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        // Create axes
        var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        // Create series
        var series = chart.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = "value";
        series.dataFields.dateX = "date";
        series.tooltipText = "{value}"
        series.strokeWidth = 2;
        series.minBulletDistance = 15;

        // Drop-shaped tooltips
        series.tooltip.background.cornerRadius = 20;
        series.tooltip.background.strokeOpacity = 0;
        series.tooltip.pointerOrientation = "vertical";
        series.tooltip.label.minWidth = 40;
        series.tooltip.label.minHeight = 40;
        series.tooltip.label.textAlign = "middle";
        series.tooltip.label.textValign = "middle";

        // Make bullets grow on hover
        var bullet = series.bullets.push(new am4charts.CircleBullet());
        bullet.circle.strokeWidth = 2;
        bullet.circle.radius = 4;
        bullet.circle.fill = am4core.color("#fff");

        var bullethover = bullet.states.create("hover");
        bullethover.properties.scale = 1.3;

        // Make a panning cursor
        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = "panXY";
        chart.cursor.xAxis = dateAxis;
        chart.cursor.snapToSeries = series;

        // Create vertical scrollbar and place it before the value axis
        chart.scrollbarY = new am4core.Scrollbar();
        chart.scrollbarY.parent = chart.leftAxesContainer;
        chart.scrollbarY.toBack();

        // Create a horizontal scrollbar with previe and place it underneath the date axis
        chart.scrollbarX = new am4charts.XYChartScrollbar();
        chart.scrollbarX.series.push(series);
        chart.scrollbarX.parent = chart.bottomAxesContainer;

        dateAxis.start = 0.79;
        dateAxis.keepSelection = true;

        window.applyForecastChartDarkTheme(chart);

    }); 

</script>



									

<script>
    queueForecastChart(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("chartdivmulti{{ $currency }}", am4charts.XYChart);

        //

        // Increase contrast by taking evey second color
        chart.colors.step = 2;
        // Add data
        chart.data = @json($cashFlowReport[$currency]['total_cash_in_out_flow'] ?? []);
		

        // Create axes
        var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        dateAxis.renderer.minGridDistance = 50;


        // Create series
        function createAxisAndSeries(field, name, opposite, bullet) {
            var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
            if (chart.yAxes.indexOf(valueAxis) != 0) {
                valueAxis.syncWithAxis = chart.yAxes.getIndex(0);
            }

            var series = chart.series.push(new am4charts.LineSeries());
            series.dataFields.valueY = field;
            series.dataFields.dateX = "date";
            series.strokeWidth = 2;
            series.yAxis = valueAxis;
            series.name = name;
            series.tooltipText = "{name}: [bold]{valueY}[/]";
            series.tensionX = 0.8;
            series.showOnInit = true;

            var interfaceColors = new am4core.InterfaceColorSet();

            switch (bullet) {
                case "triangle":
                    var bullet = series.bullets.push(new am4charts.Bullet());
                    bullet.width = 12;
                    bullet.height = 12;
                    bullet.horizontalCenter = "middle";
                    bullet.verticalCenter = "middle";

                    var triangle = bullet.createChild(am4core.Triangle);
                    triangle.stroke = interfaceColors.getFor("background");
                    triangle.strokeWidth = 2;
                    triangle.direction = "top";
                    triangle.width = 12;
                    triangle.height = 12;
                    break;
                case "rectangle":
                    var bullet = series.bullets.push(new am4charts.Bullet());
                    bullet.width = 10;
                    bullet.height = 10;
                    bullet.horizontalCenter = "middle";
                    bullet.verticalCenter = "middle";

                    var rectangle = bullet.createChild(am4core.Rectangle);
                    rectangle.stroke = interfaceColors.getFor("background");
                    rectangle.strokeWidth = 2;
                    rectangle.width = 10;
                    rectangle.height = 10;
                    break;
                default:
                    var bullet = series.bullets.push(new am4charts.CircleBullet());
                    bullet.circle.stroke = interfaceColors.getFor("background");
                    bullet.circle.strokeWidth = 2;
                    break;
            }

            valueAxis.renderer.line.strokeOpacity = 1;
            valueAxis.renderer.line.strokeWidth = 2;
            valueAxis.renderer.line.stroke = series.stroke;
            valueAxis.renderer.labels.template.fill = series.stroke;
            valueAxis.renderer.opposite = opposite;
        }


        createAxisAndSeries("cash_in", "Cash Inflow", false, "circle");
        createAxisAndSeries("cash_out", "Cash Outflow", true, "circle");
        // createAxisAndSeries("hits", "Hits", true, "rectangle");

        // Add legend
        chart.legend = new am4charts.Legend();

        // Add cursor
        chart.cursor = new am4charts.XYCursor();

        window.applyForecastChartDarkTheme(chart, {keepYAxisLabelColors: true});



    }); 
    $(document).on('change', '[js-refresh-withdrawal-due-data-and-chart][data-currency="{{ $currency }}"]', function() {
        const currencyName = $(this).attr('data-currency')
        const currentChartId = 'withdrawal-dues-chart-' + currencyName;
        const accountTypeId = $('select.withdrawal-account-type-js[data-currency="'+currencyName+'"]').val()
		const withdrawalStartDate = $('.withdrawal-start-date[data-currency="'+currencyName+'"]').val()
		const withdrawalEndDate = $('.withdrawal-end-date[data-currency="'+currencyName+'"]').val()
        $.ajax({
            url: "{{ route('refresh.withdrawal.report',['company'=>$company->id]) }}"
            , data: {
                accountTypeId
                , currencyName,
				withdrawalStartDate,
				withdrawalEndDate
            }
            , type: "get"
            , success: function(res) {
                let data = []
                let chartData = []
                let trs = '';
                for (var item of res.data) {
                    trs += `<tr> 
					<td>${item.due_date}</td>
					<td class="text-center">${number_format(item.net_balance)}</td>
				 </tr>`
                    chartData.push({
                        date: item.due_date
                        , value: item.net_balance
                    })
                }
                $('#append-withdrawal-due-' + currencyName).empty().append(trs)
				window.__forecastPendingChartData[currentChartId] = chartData;
				if (window.am4core) {
					var sprite = am4core.registry.baseSprites.find(function (c) { return c.htmlContainer.id === currentChartId; });
					if (sprite) {
						sprite.data = chartData;
					}
				}
            }
        })
    })
	
	
	
	
	 $(document).on('change', '[js-refresh-medium-term-loan-chart][data-currency="{{ $currency }}"]', function() {
        const currencyName = $(this).attr('data-currency')
        const currentChartId = 'loan-chart-' + currencyName;
        const financialInstitutionId = $('select.financial-instutiton-js[data-currency="'+currencyName+'"]').val()
        const mediumTermLoanId = $('select.medium-term-loan-js[data-currency="'+currencyName+'"]').val()
		const loanStartDate = $('.loan-start-date[data-currency="'+currencyName+'"]').val()
		const loanEndDate = $('.loan-end-date[data-currency="'+currencyName+'"]').val()
        $.ajax({
            url: "{{ route('refresh.medium.term.loan.report',['company'=>$company->id]) }}"
            , data: {
                financialInstitutionId,
                mediumTermLoanId
                , currencyName,
				loanStartDate,
				loanEndDate
            }
            , type: "get"
            , success: function(res) {
                let data = []
                let chartData = []
                let trs = '';
                for (var item of res.data) {
                    trs += `<tr> 
					<td>${item.date}</td>
					<td class="text-center">${number_format(item.schedule_payment)}</td>
				 </tr>`
                    chartData.push({
                        date: item.date
                        , value: item.schedule_payment
                    })
                }
                $('#append-loan-' + currencyName).empty().append(trs)
                window.__forecastPendingChartData[currentChartId] = chartData;
                if (window.am4core) {
                    const chartSprite = am4core.registry.baseSprites.find(c => c.htmlContainer.id === currentChartId)
                    if (chartSprite) {
                        chartSprite.data = chartData
                    }
                }
            }
        })
    })
	

</script>
@endforeach

<script>
    $(function () {
        $('select[js-refresh-withdrawal-due-data-and-chart]').trigger('change')
        $('select.financial-instutiton-js').trigger('change')
    })
</script>
<script>
    function getLastAppearanceOfKeyInObject(items, key) {
        var result = '';
        for (object of items) {
            if (object.region == key) {
                result = object.state;
            }
        }
        return result;
    }

</script>
<script>
 $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('.kt-portlet__body')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency') ? $(this).find('option:selected').data('currency').toUpperCase() : ''
        const startDate = $(this).find('option:selected').data('start-date')
        const endDate = $(this).find('option:selected').data('end-date')
        $(parent).find('.contract-code').val(code)
        $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)
        $(parent).find('.contract-start-date-class').val(startDate)
        $(parent).find('.contract-end-date-class').val(endDate)


    })
 $(document).on('change', 'select.financial-instutiton-js', function() {
        const parent = $(this).closest('.kt-portlet__body')
        const financialInstitutionId = parseInt($(this).val())
		const currency = $(this).attr('data-currency');

        $.ajax({
            url: "{{ route('get.medium.term.loan.for.financial.institution',['company'=>$company->id]) }}"
            , data: {
                financialInstitutionId
                , currency
                
            }
            , type: "get"
            , success: function(res) {
                let loans = '<option value="0">{{ __("All") }}</option>';
                const currentSelected = 0
                // const currentSelected = $(parent).find('select.contracts-js').data('current-selected')
                for (var loan of res.loans) {
                    loans += `<option ${currentSelected ==loan.id ? 'selected' :'' } value="${loan.id}"  >${loan.name}</option>`;
                }
                parent.find('select.medium-term-loan-js').empty().append(loans).trigger('change')
            }
        })
    })
	
    $(document).on('change', 'select.suppliers-or-customers-js', function() {
        const parent = $(this).closest('.kt-portlet__body')
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
        $('select.financial-instutiton-js').trigger('change')
    })
	
	
</script>


<script>
$(document).on('click', '.js-show-customer-due-invoices-modal', function(e) {
        e.preventDefault();
        $(this).closest('.parent-item').find('.modal-item-js').modal('show')
    })
	
	
	
$(document).on('click', '.js-show-loan-past-due-installment-modal', function(e) {
        e.preventDefault();
        $(this).closest('.parent-item').find('.modal-item-js').modal('show')
    })
	
</script>

@endsection
