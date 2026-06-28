@extends('layouts.dashboard')
@php
	use MathPHP\Statistics\Correlation ;
	use App\Helpers\HArr;
	use App\Helpers\HMath;
@endphp
@section('css')
<link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />

@endsection

@section('dash_nav')
<style>
    .chartdiv_two_lines {
        width: 100%;
        height: 400px;
    }

    .chartDiv {
        max-height: 400px !important;
    }

    .margin__left {
        border-left: 2px solid #366cf3;
    }

    .sky-border {
        border-bottom: 1.5px solid #CCE2FD !important;
    }

    .kt-widget24__title {
        color: white !important;
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

    /* .dataTables_wrapper{max-width: 100%;  padding-bottom: 50px !important;overflow-x: overlay;max-height: 4000px;} */

</style>
@endsection
@section('content')
<div class="kt-portlet">

    <form action="{{ route('view.expense.analysis.dashboard',['company'=>$company->id]) }}" class="kt-portlet__head w-full sky-border" style="">
        <div class="kt-portlet__head-label w-full">
            <h3 class="kt-portlet__head-title head-title text-primary w-full">


                <div class="row mb-3">
                    <div class="col-md-2">
                        <label class="visibility-hidden"> {{__('Currency')}}
                            @include('star')
                        </label>
                        <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-nowrap" style=""> {{ __('Dashboard Results') }}</h3>

                    </div>
                    <div class="col-md-2">
                        <div class="d-flex  align-items-center mt-4">
                            <label class="label text-nowrap mr-2"> {{__('End Date')}}
                                @include('star')
                            </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input id="js-start-date" type="date" value="{{ isset($startDate) ? $startDate: date('Y-m-d') }}" name="start_date" class="form-control" placeholder="Select date" id="kt_datepicker_2" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
					
					  <div class="col-md-2">
                        <div class="d-flex  align-items-center mt-4">
                            <label class="label text-nowrap mr-2"> {{__('End Date')}}
                                @include('star')
                            </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input id="js-end-date" type="date" value="{{ isset($endDate) ? $endDate: date('Y-m-d') }}" name="end_date" class="form-control" placeholder="Select date" id="kt_datepicker_2" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
					
                    <div class="col-md-3 kt-align-right">

                        <label class="visibility-hidden"> {{__('Currency')}}
                            @include('star')
                        </label>

                        <div class="input-group">
                            <button type="submit" class="btn active-style save-form">{{__('Save')}}</button>
                        </div>
                    </div>

                </div>



            </h3>
        </div>
    </form>

    <div class="kt-portlet__body" style="padding-bottom:0 !important;">
        <ul style="margin-bottom:0 ;" class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
            @php
            $index = 0 ;
            @endphp
            {{-- @foreach($selectedCurrencies as $currencyUpper=>$currency) --}}

            <li class="nav-item 
			 active 
			{{-- @if($index ==0 ) active @endif --}}
			
			">
                <a class="nav-link 
				 active 
				{{-- @if($index ==0 ) active @endif --}}
				
				" data-toggle="tab" href="#kt_apps_contacts_view_tab_main" {{-- href="#kt_apps_contacts_view_tab_main{{ $index }}" --}} role="tab">
                    <i class="flaticon2-checking icon-lg"></i>
                    <span style="font-size:18px !important;">
                        {{-- {{ $currency }} --}}
                        {{ __('Expense Analysis') }}
                    </span>
                </a>
            </li>

            {{-- @php
            $index++;
            @endphp
            @endforeach --}}
        </ul>
    </div>
</div>

<div class="tab-content  kt-margin-t-20">
    @php
    $index = 0 ;
    @endphp

    {{-- @foreach($selectedCurrencies as $name=>$currency) --}}

    <div class="tab-pane  
	 active 
	{{-- @if($index == 0) active @endif --}}
	
	" {{-- id="kt_apps_contacts_view_tab_main{{ $index }}" --}} id="kt_apps_contacts_view_tab_main" role="tabpanel">
	
	
	<div class="kt-portlet">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
		 <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{__('Expenses Results')}}</h3>
           
        </div>
    </div>
    <div class="kt-portlet__body  kt-portlet__body--fit">
        <div class="row row-no-padding row-col-separator-xl">
            {{-- Daily --}}
           
            {{-- Current --}}
            <div class="col-md-3 ">

                <!--begin::New Orders-->
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <div class="kt-widget24__info">
                            {{-- @if(isset((explode('-', $salesReport['last_date'])[1] ))) --}}
                            <h4 class="kt-widget24__title font-size">


                                {{ __('Current Month') }} :
                                {{ \Carbon\Carbon::make($endDate)->format('M - Y') }}
                                {{-- {{ (explode('-', $salesReport['last_date'])[1] ?? '')  . ( ' - '  . explode('-', $salesReport['last_date'])[2] ?? '') }} --}}
                                {{-- {{ __('Current Month') }} : --}}

                            </h4>

                            {{-- @endif  --}}

                        </div>
                    </div>
                    <div class="kt-widget24__details">
                        <span class="kt-widget24__stats kt-font-danger">
                            {{ number_format($currentMonthExpenses) }}
                        </span>
                    </div>

                    <div class="progress progress--sm">
                        <div class="progress-bar kt-bg-danger" role="progressbar" style="width: {{ $percentage }}%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="kt-widget24__action">
                        <span class="kt-widget24__change">
                            {{ __('Change') }}
                        </span>
                        <span class="kt-widget24__number">
                            {{-- {{ $percentage }}% --}}
                            {{-- <br> --}}
                            {{ number_format($percentage , 2) }} %
                        </span>
                    </div>
                </div>

                <!--end::New Orders-->
            </div>
			{{-- Previous Month --}}
            <div class="col-md-3">

                <!--begin::New Feedbacks-->
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <div class="kt-widget24__info">
                            {{-- @if($salesReport['last_date']) --}}
                            <h4 class="kt-widget24__title font-size">
                                {{ __('Previous Month') }} : ( {{
                                      \Carbon\Carbon::make($endDate)->startOfMonth()->subMonth(1)->format('M')
                                      }} ) ({{ $yearOfEndDate ?? '' }})
                            </h4>
                            {{-- @endif  --}}
                        </div>
                    </div>
                    <div class="kt-widget24__details">
                        <span class="kt-widget24__stats kt-font-warning">
                            <span class="text-red"></span>
                            {{ number_format($previousMonthExpenses) }}
                            {{-- {{ $sales_value_data['previous_month'] !== '-' ? number_format($sales_value_data['previous_month']) : '-' }} --}}
                        </span>
                    </div>
                    <div class="progress progress--sm">
                        <div class="progress-bar kt-bg-warning" role="progressbar" style="width: 100%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="kt-widget24__action">
                        <span class="kt-widget24__change">

                        </span>
                        <span class="kt-widget24__number">

                        </span>
                    </div>
                </div>

                <!--end::New Feedbacks-->
            </div>
          
            {{-- Previous 3 Months --}}
            <div class="col-md-3 ">

                <!--begin::Total Profit-->
                <div class="kt-widget24 text-center">
                    <div class="kt-widget24__details">
                        <div class="kt-widget24__info">

                            {{-- @if($salesReport['last_date']) --}}
                            <h4 class="kt-widget24__title font-size">
                                {{ __('Previous 3 Months') }} : ( {{ \Carbon\Carbon::make($endDate)->startOfMonth()->subMonth(3)->format('M') 
                                    . ' - ' . \Carbon\Carbon::make($endDate)->startOfMonth()->subMonth(2)->format('M') . ' - ' .
                                     \Carbon\Carbon::make($endDate)->startOfMonth()->subMonth(1)->format('M') }} )

                                ({{ $yearOfEndDate }})

                            </h4>
                            {{-- @endif  --}}

                        </div>
                    </div>
                    <div class="kt-widget24__details">
                        <span class="kt-widget24__stats kt-font-brand">
                            {{-- <span style="color:red !important">{{ NUMBER }}</span> --}}
                        {{-- {{ $sales_value_data['previous_three_months'] !== '-' ? number_format($sales_value_data['previous_three_months']) : '-' }} --}}
                        {{ number_format($perviousThreeMonthsExpenses) }}
                        </span>
                    </div>

                    <div class="progress progress--sm">
                        <div class="progress-bar kt-bg-brand" role="progressbar" style="width: 100%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="kt-widget24__action">
                        <span class="kt-widget24__change">

                        </span>
                        <span class="kt-widget24__number">

                        </span>
                    </div>
                </div>

                <!--end::Total Profit-->
            </div>
			
			  {{-- Year To Date --}}
            <div class="col-md-3">

                <!--begin::New Users-->
                <div class="kt-widget24">
                    <div class="kt-widget24__details">
                        <div class="kt-widget24__info">
                            <h4 class="kt-widget24__title font-size">
                                {{ __('Year To Date Expenses') }}
                                ({{ $yearOfEndDate }})
					
                            </h4>

                        </div>
                    </div>
                    <div class="kt-widget24__details">
                        <span class="kt-widget24__stats kt-font-success">
                            {{ number_format($expensesToDate) }}
							@if($totalSales)
								 [ 
									
										{{ number_format($expensesToDate / $totalSales * 100,2) . ' % / Rev' }}
								 ]
										@endif
                            {{-- <br> --}}
                            {{-- {{ $sales_value_data['year_to_date'] !== '-' ? number_format($sales_value_data['year_to_date']) : '-' }} --}}
                        </span>
                    </div>
                    <div class="progress progress--sm">
                        <div class="progress-bar kt-bg-success" role="progressbar" style="width: 100%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="kt-widget24__action">

                    </div>
                </div>

                <!--end::New Users-->
            </div>
			
            
        </div>
    </div>
</div>













        <div class="kt-portlet">
            <div class="kt-portlet__head sky-border">
                <div class="kt-portlet__head-label">
                    <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{__('Year To Date Expense Breakdown')}}</h3>
                </div>
            </div>
            <div class="kt-portlet__body  kt-portlet__body--fit">
                <div class="row row-no-padding row-col-separator-xl">

					@php
						$mainCategoriesNames = [];
							$subItemNames= [];
							$expensesMonthlyTotals = [];
							$fixedVariableExpenseCoefficientCorrelations = [];
					@endphp

                    @foreach( $result['report_data']??[] as $name => $subItems )
					@php
						$subItems =  HArr::sortBySumOfKeyWithoutPreservingOriginalArray($subItems,'Avg. Prices') ;
						
						
					@endphp
				
					@if($name == 'Growth Rate %')
						@continue
					@endif 
					
					@php
						$currentModalId = convertStringToClass($name);
					
						$cardTotal = array_sum($subItems['Total'] ?? []) ;
					
						if($name != 'Total'){
						foreach($subItems as $subItemName => $subItemValueArr)
						{
							$chartData['pie'][$name][] = ['name'=>$subItemName , 'value'=>number_format(array_sum($subItemValueArr['Avg. Prices'] ?? []))];
						
						
								if($subItemName != 'Total'  && $subItemName != 'Growth Rate %'){
								$subItemNames[$name][]=$subItemName;
									
								}
								$currentLoopItems = $subItemValueArr['Avg. Prices']??[] ;
								$currentLoopItems = $subItemValueArr['Avg. Prices']??[] ;
								if(count($currentLoopItems) != count(array_keys($monthlySalesForSalesGathering))){
									$currentLoopItems = HArr::fillMissingKeyInOneDimArrWith($currentLoopItems,array_keys($monthlySalesForSalesGathering));
								}
								
									if(array_sum($currentLoopItems) && array_sum($monthlySalesForSalesGathering) && $subItemName !='Total' && $subItemName != 'Growth Rate %'){

$fixedVariableExpenseCoefficientCorrelations[$name][$subItemName] = 0;
								    try{
								    	$fixedVariableExpenseCoefficientCorrelations[$name][$subItemName]  = Correlation::r($currentLoopItems, $monthlySalesForSalesGathering);
								    }
								    catch(\Exception $e){
								  
								    }									
									
									
									}elseif($subItemName !='Total' && $subItemName != 'Growth Rate %'){
										$fixedVariableExpenseCoefficientCorrelations[$name][$subItemName] = 0;
									}
								
								if($subItemName == 'Total'){
									$currentLoopItems = $subItemValueArr;
								}
							
							
						
								foreach( $currentLoopItems?? [] as $d => $v){
									
										if($subItemName !='Total' && $subItemName != 'Growth Rate %'){
										$expensesMonthlyTotals[$d] = isset($expensesMonthlyTotals[$d]) ? $expensesMonthlyTotals[$d] + $v : $v; 
										}
									$currentSalesValue = $monthlySalesForSalesGathering[$d]??0;
									$currentGrowthRate = $result['report_data'][$name][$subItemName]['Growth Rate %'][$d]??0 ;
									if($subItemName =='Total' ){
										$currentGrowthRate =$result['report_data'][$name]['Growth Rate %'][$d]??0;
									}
									$chartData['three_lines'][$name][$subItemName][] =[
										'date'=>\App\Helpers\HVero::formatDateForChart($d) ,
										'monthly_expense_value'=>number_format($v)
										,'growth_rate'=> number_format($currentGrowthRate,2),
										'revenue_percentage'=>$currentSalesValue ? number_format($v /$currentSalesValue   * 100,2) : 0
										];
								}
							
							
						}	
						}
					
							$currentLoopItems = $name == 'Total' ? $subItems : \App\Helpers\HArr::array_get($subItems,'Total') ;
						foreach($currentLoopItems as $currentDate => $currentValue){
								$currentSalesValue = $monthlySalesForSalesGathering[$currentDate]??0;
								$currentGrowthRate = $result['report_data']['Growth Rate %'][$currentDate] ?? 0 ;
								$chartData['three_lines']['general'][$name][] =['date'=> \App\Helpers\HVero::formatDateForChart($currentDate) , 'monthly_expense_value'=>number_format($currentValue),
								'revenue_percentage'=>$currentSalesValue ? number_format($currentValue /$currentSalesValue   * 100,2) : 0
								 , 'growth_rate'=>number_format($currentGrowthRate,2)  ];
							}
						if($name != 'Total' && $name != 'Growth Rate %'){
							$chartData['pie']['general'][] = ['name'=>$name , 'value'=>number_format($cardTotal)] ;
							$mainCategoriesNames[] =$name; 
							
						}
					@endphp
					@if($name !='Total')
                    <div class="col-md-6 col-lg-3 col-xl-3">
                        <!--begin::Total Profit-->
                        <div class="kt-widget24 text-center">
                            <div class="kt-widget24__details">
                                <div class="kt-widget24__info w-100">
                                    <h4 class="kt-widget24__title font-size text-uppercase d-flex justify-content-between align-items-center">
                                        {{ $name }}
                                        @php
                                        // $currentModalId = 'cost_of_sales';
                                        @endphp
										@if($name !='Total')
                                        <button class="btn btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModalId }}">{{ __('Details') }}</button>
										@endif
                                        @include('admin.dashboard.expense_modal',['detailItems'=> $subItems ,'cardTotal'=>$cardTotal , 'modalId'=>$currentModalId ,'title'=>$name])
                                    </h4>

                                </div>
                            </div>
							
							
                            <div class="kt-widget24__details">
								@php
									$currentExpenseTotal = 0 ;
								@endphp
                                <span class="kt-widget24__stats kt-font-brand text-left">
									
									@php
										$currentExpenseTotal = $cardTotal
									@endphp
                                    {{ number_format($currentExpenseTotal) }}
							
									@if($totalSales)
									<br>
									<br>
									<span class="text-green">[{{ number_format($currentExpenseTotal / $totalSales * 100,2) . ' % / Rev'  }}]</span>
									@endif 
                                </span>
                            </div>

                            <div class="progress progress--sm">
                                <div class="progress-bar kt-bg-brand" role="progressbar" style="width: 78%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                        </div>

                        <!--end::Total Profit-->
                    </div>
					@endif 
                    @endforeach



                </div>
            </div>
        </div>
		
		  <div class="kt-portlet">
            <div class="kt-portlet__head sky-border">
                <div class="kt-portlet__head-label">
                    <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{__('Auto calculated Sales Breakeven Value = ' . number_format(HMath::calculateBreakevenPoint($monthlySalesForSalesGathering,$expensesMonthlyTotals)) )}}</h3>
                </div>
            </div>
            </div>
			
		
        <!--end:: Widgets/Stats-->

        {{-- <div class="row">
            <div class="col-md-12">
                <div class="kt-portlet ">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{ __('Expense') }} </h3>

                        </div>
                    </div>
                </div>
            </div>
        </div> --}}


        {{-- <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">

                    <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{ __('test name here') }} </h3>

                </div>
            </div> --}}
            {{-- <div class="kt-portlet__body  kt-portlet__body--fit">
                <div class="row row-no-padding row-col-separator-xl">
                    <div class="col-md-6 col-lg-3 col-xl-3">

                        <!--begin::Limit-->
                        <div class="kt-widget24 text-center">
                            <div class="kt-widget24__details">
                                <div class="kt-widget24__info">
                                    <h4 class="kt-widget24__title font-size">
                                        {{ __('Limit') }}
            </h4>

        </div>
    </div>
    <div class="kt-widget24__details">
        <span class="kt-widget24__stats kt-font-brand">
            {{ number_format($totalCard[$currency]['limit'] ?? 0,0) }}
        </span>
    </div>


</div>

<!--end::Total Profit-->
</div>
<div class="col-md-6 col-lg-3 col-xl-3">

    <!--begin::New Feedbacks-->
    <div class="kt-widget24">
        <div class="kt-widget24__details">
            <div class="kt-widget24__info">
                <h4 class="kt-widget24__title font-size">
                    {{ __('Outstanding') }}
                </h4>
            </div>
        </div>
        <div class="kt-widget24__details">
            <span class="kt-widget24__stats kt-font-warning">
                {{ number_format($totalCard[$currency]['outstanding']??0,0) }}
            </span>
        </div>

    </div>

    <!--end::New Feedbacks-->
</div>
<div class="col-md-6 col-lg-3 col-xl-3">

    <!--begin::New Orders-->
    <div class="kt-widget24">
        <div class="kt-widget24__details">
            <div class="kt-widget24__info">
                <h4 class="kt-widget24__title font-size">
                    {{ __('Available') }}
                </h4>

            </div>
        </div>
        <div class="kt-widget24__details">
            <span class="kt-widget24__stats kt-font-danger">
                {{ number_format($totalCard[$currency]['room']??0,0) }}
            </span>
        </div>
    </div>

    <!--end::New Orders-->
</div>
<div class="col-md-6 col-lg-3 col-xl-3">

    <!--begin::New Users-->
    <div class="kt-widget24">
        <div class="kt-widget24__details">
            <div class="kt-widget24__info">
                <h4 class="kt-widget24__title font-size">
                    {{ __('Interest') }}
                </h4>

            </div>
        </div>
        <div class="kt-widget24__details">
            <span class="kt-widget24__stats kt-font-success">
                {{ number_format($totalCard[$currency]['interest_amount']??0,0) }}
            </span>
        </div>
    </div>

    <!--end::New Users-->
</div>
</div>
</div> --}}
{{-- </div> --}}


<div class="row">



  {{-- Fully Secured Overdraft  --}}
    {{-- @if($hasCostOfSales??true) --}}
  

    {{-- Fully Secured Overdraft  Chart --}}
    <div class="col-md-12">
        <div class="kt-portlet kt-portlet--tabs">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-toolbar w-full">
                    <ul class="w-full nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tab">
                                <i class="flaticon-line-graph"></i> &nbsp; {{ __('Charts') }}
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a class="nav-link " data-toggle="tab" href="#FullySecuredOverdraftkt_apps_contacts_view_tab_2" role="tab">
                                <i class="flaticon2-checking"></i>{{ __('Reports Table') }}
                            </a>
                        </li> --}}
                        <li class="nav-item ml-auto">
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">
									<form   target="_blank"  method="post" action="{{ route('one.selector.expense.report.result',['company'=>$company->id]) }}">
										@csrf
										<input type="hidden" name="data_type" value="value">
										<input type="hidden" name="report_type" value="trend">
										<input type="hidden" name="interval" value="monthly">
										<input type="hidden" name="table_name" value="expense_analysis">
										<input type="hidden" name="firstColumnName" value="category_name">
										<input type="hidden" name="type" value="category_name">
										<input type="hidden" name="reportSelectorType" value="one_selector">
										@foreach($mainCategoriesNames as $categoryName)
											<input type="hidden" name="firstColumnData[]" value="{{ $categoryName }}">
										@endforeach
										<input type="hidden" name="start_date" value="{{ $startDate }}">
										<input type="hidden" name="end_date" value="{{ $endDate }}">
										<button class="btn btn-sm btn-brand btn-elevate btn-pill text-white" type="submit">{{ __('Expense Category Trend Report') }}</button>
									</form>
                                </div>
                            </div>
                        </li>

                        {{-- <li class="nav-item">
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">
                                    <a href="#" type="button" class="btn btn-sm btn-brand btn-elevate btn-pill text-white"><i class="fa fa-chart-line"></i> {{ __('Expense Against Revenues Report') }} </a>
                                </div>
                            </div>
                        </li> --}}

                    </ul>
                </div>
            </div>
            <div class="kt-portlet__body pt-0">
                <select class="current-currency hidden">
                    {{-- <option value="{{ $currency }}"></option> --}}
                </select>

                <div class="tab-content  kt-margin-t-20">

                    <div class="tab-pane active" id="FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tabpanel">

                        {{-- Monthly Chart --}}
                        <div class="row">
                            <div class="col-md-4">
                    <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" > {{ __('Category Breakdown') }} </h3>

                                {{-- <h4>  </h4> --}}
                                <div id="pie-chart-general-id" class="chartDiv"></div>
								<input type="hidden" id="pie-chart-general-data-id" data-chart-data="{{ json_encode($chartData['pie']['general']??[]) }}">
                            </div>



                            <div class="col-md-8 margin__left">

                                <div class="row mb-3 ml-4">
                                    <div class="col-12">
									 <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" > {{ __('Monthly Expense Trend') }} </h3>
                                    </div>
                                    <div class="col-md-6 ">
                                        <select  js-refresh-three-line-chart class="form-control" data-type="general" id="general-three-line-chart-select">
                                            <option value="Total"> {{ 'All' }} </option>
                                            @foreach($mainCategoriesNames as $mainCategoryName)
                                            <option value="{{ $mainCategoryName }}"> {{ $mainCategoryName }} </option>
                                            @endforeach
                                        </select>
                                    </div>



                                </div>
                                <div class="chartdiv_two_lines" id="three-line-chart-general-id"></div>
								
								@foreach($chartData['three_lines']['general']??[] as $chartName => $currentChartData )
					
								<input type="hidden" class="three-line-chart-general-data-class"  data-chart-name="{{ $chartName }}"  data-chart-data="{{ json_encode($currentChartData) }}">
								@endforeach
                            </div>
                        </div>

                    </div>

                    {{-- <div class="tab-pane" id="FullySecuredOverdraftkt_apps_contacts_view_tab_2" role="tabpanel">
                        <div class="col-md-12">
                            <div class="kt-portlet kt-portlet--mobile">

                                <div class="kt-portlet__body">

                                    <!--begin: Datatable -->
                                    <x-table :tableClass="'kt_table_with_no_pagination_no_scroll_no_entries'">
                                        @slot('table_header')
                                        <tr class="table-active text-center">
                                            <th class="text-center max-w-300">{{ __('Sub Category Name') }}</th>
                                            <th class="text-center ">{{ __('Amount') }}</th>
                                            <th class="text-center ">{{ __('% Of Total') }}</th>
                                            <th class="text-center ">{{ __('% Of Revenues') }}</th>
                                        </tr>
                                        @endslot
                                        @slot('table_body')


                                        @foreach ([] as $key => $item)
                                        <tr>

                                            <td class=" max-w-300">{{ '-'}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                        </tr>
                                        @endforeach

                                        <tr class="table-active text-center">
                                            <td>{{__('Total')}}</td>
                                            <td>{{number_format(0)}}</td>
                                            <td>{{number_format(0)}}</td>
                                            <td>{{number_format(0)}}</td>

                                        </tr>
                                        @endslot
                                    </x-table>

                                    <!--end: Datatable -->
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="FullySecuredOverdrafttotal_available_room" data-total="{{ json_encode( [] ) }}">
                    </div> --}}
                </div>
            </div>
        </div>

    </div>
    {{-- @endif --}}
    {{-- End Fully Secured Overdraft --}}
	

    {{-- Fully Secured Overdraft  --}}
   @foreach($mainCategoriesNames as $mainCategoriesName)
    <div class="col-md-4 " >
        <div class="kt-portlet " style="height:97% ">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label col-8">
                    <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" style=""> {{ $mainCategoriesName . ' ' . __('Category') }} </h3>
                </div>
            </div>
            <div class="kt-portlet__body">
                <div class="row">
					<div class="col-md-12">

						@include('admin.dashboard.avg_min_max_dashboard_table',[
							'avg'=>$avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Average Value']??0 ,
							'min'=>$avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Min Value']['value']??0,
							'max'=>$avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Max Value']['value']??0,
							'fixedVariableExpenseCoefficientCorrelations'=>$fixedVariableExpenseCoefficientCorrelations[$mainCategoriesName]??[]
						])
					</div>
				
                    {{-- <div class="col-md-6">
						
                        <div class="kt-portlet kt-iconbox kt-iconbox--brand kt-iconbox--animate-slower">
                            <div class="kt-portlet__body">
                                <div class="kt-iconbox__body">
                                    <div class="kt-iconbox__desc">
                                        <h3 class="kt-iconbox__title">
                                            <a class="kt-link" onclick="return false" href="#">{{ __('Average Value') }}</a>
                                        </h3>
                                        <div class="kt-iconbox__content text-primary  ">
                                            <h4>{{ number_format($avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Average Value']??0) }}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="kt-portlet kt-iconbox kt-iconbox--brand kt-iconbox--animate-slower">
                            <div class="kt-portlet__body">
                                <div class="kt-iconbox__body">
                                    <div class="kt-iconbox__desc">
                                        <h3 class="kt-iconbox__title">
                                            <a class="kt-link" onclick="return false" href="#">{{ __('Min Value') }}</a>
                                        </h3>
                                        <div class="kt-iconbox__content text-primary  ">
                                                    <h4>{{ number_format($avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Min Value']['value']??0) }}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> --}}
                </div>
                {{-- <div class="row">
                    <div class="col-md-6">
                        <div class="kt-portlet kt-iconbox kt-iconbox--brand kt-iconbox--animate-slower">
                            <div class="kt-portlet__body">
                                <div class="kt-iconbox__body">
                                    <div class="kt-iconbox__desc">
                                        <h3 class="kt-iconbox__title">
                                            <a class="kt-link" onclick="return false" href="#">{{ __('Max Value') }}</a>
                                        </h3>
                                        <div class="kt-iconbox__content text-primary  ">
                                                     <h4>{{ number_format($avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Max Value']['value']??0) }}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="kt-portlet kt-iconbox kt-iconbox--brand kt-iconbox--animate-slower">
                            <div class="kt-portlet__body">
                                <div class="kt-iconbox__body">
                                    <div class="kt-iconbox__desc">
                                        <h3 class="kt-iconbox__title">
                                            <a class="kt-link" onclick="return false" href="#">{{ __('Outliers Values') }}</a>
                                        </h3>
                                        <div class="kt-iconbox__content text-primary  ">
							
										@php
											$currentOutliersArr = $avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Outliers'] ?? [];
											$currentModalId = convertStringToClass($mainCategoriesName.'-outliers');
											$title = __('Outliers For ' . $mainCategoryName . ' Modal');
											
										@endphp
										<button
											 class="btn btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModalId }}">{{ __('Details') }}</button>
										@include('admin.modals.avg-min-max-outliers-date-value-modals',['detailItems'=> $currentOutliersArr , 'modalId'=>$currentModalId ,'title'=>$title])
													
                                          
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> --}}
                {{-- Chart --}}
                {{-- <div class="row">
                            <div class="col-md-12">
                                <div class="chartdiv" id="chartdiv2"></div>
                            </div>
                        </div> --}}
            </div>
        </div>
    </div>

    {{-- Start Section --}}
    <div class="col-md-8">
        <div class="kt-portlet kt-portlet--tabs">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-toolbar w-full">
                    <ul class="w-full nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tab">
                                <i class="flaticon-line-graph"></i> &nbsp; {{ __('Charts') }}
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a class="nav-link " data-toggle="tab" href="#FullySecuredOverdraftkt_apps_contacts_view_tab_2" role="tab">
                                <i class="flaticon2-checking"></i>{{ __('Reports Table') }}
                            </a>
                        </li> --}}
					
                        <li class="nav-item ml-auto">
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">
									<form target="_blank" method="post" action="{{ route('result.expense.against.report',['company'=>$company->id]) }}">
										@csrf
										<input type="hidden" name="type" value="expense_name"  >
										<input type="hidden" name="data_type" value="value"  >
										<input type="hidden" name="report_type" value="trend"  >
										<input type="hidden" name="start_date" value="{{ $startDate }}"  >
										<input type="hidden" name="end_date" value="{{ $endDate }}"  >
										<input type="hidden" name="interval" value="monthly"  >
										<input type="hidden" name="tableName" value="expense_analysis"  >
										<input type="hidden" name="firstColumnName" value="category_name"  >
										<input type="hidden" name="secondColumnName" value="sub_category_name"  >
										<input type="hidden" name="thirdColumnName" value="expense_name"  >
										<input type="hidden" name="reportSelectorType" value="three_selector"  >
										<input type="hidden" name="firstColumnData[]" value="{{ $mainCategoriesName }}"  >
										@foreach($subItemNames[$mainCategoriesName]??[] as $subItemName)
											<input type="hidden" name="thirdColumnData[]" value="{{ $subItemName }}"  >
										@endforeach 
										
										
										
										<button type="submit" class="btn btn-sm btn-brand btn-elevate btn-pill text-white" >{{ __('Expense Trend Report') }}</button>
									</form>
                                    {{-- <a href="#" type="button" class="btn btn-sm btn-brand btn-elevate btn-pill text-white"><i class="fa fa-chart-line"></i> {{ __('Expense Trend Report') }} </a> --}}
                                </div>
                            </div>
                        </li>

                        {{-- <li class="nav-item">
                            <div class="kt-portlet__head-label ">
                                <div class="kt-align-right">
                                    <a href="#" type="button" class="btn btn-sm btn-brand btn-elevate btn-pill text-white"><i class="fa fa-chart-line"></i> {{ __('Expense Against Revenues Report') }} </a>
                                </div>
                            </div>
                        </li> --}}

                    </ul>
                </div>
            </div>
            <div class="kt-portlet__body pt-0">
                <select class="current-currency hidden">
                    {{-- <option value="{{ $currency }}"></option> --}}
                </select>

                <div class="tab-content  kt-margin-t-20">

                    <div class="tab-pane active" id="FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tabpanel">

                        {{-- Monthly Chart --}}
                        <div class="row">
                            <div class="col-md-4">
				<h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" > {{ __('Expense Item Breakdown') }} </h3>
                          
								<div id="{{ 'pie-chart-'.convertStringToClass($mainCategoriesName).'-id' }}" class="chartDiv"></div>
								<input type="hidden" id="{{ 'pie-chart-'.convertStringToClass($mainCategoriesName).'-data-id' }}" data-chart-data="{{ json_encode($chartData['pie'][$mainCategoriesName]??[]) }}">
										
								{{-- <input type="hidden" name=> --}}
                            </div>



						  
                            <div class="col-md-8 margin__left">
                                <div class="row mb-3 ml-4">
                                    <div class="col-12">
									 <h3 class="font-weight-bold  form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap" > {{ __('Monthly Expense Trend') }} </h3>
                                    </div>
                                    <div class="col-md-6 ">
                                        <select  js-refresh-three-line-chart class="form-control" data-type="{{ convertStringToClass($mainCategoriesName) }}" id="{{ convertStringToClass($mainCategoriesName) }}-three-line-chart-select">
                                            <option value="Total"> {{ 'All' }} </option>
                                            @foreach($subItemNames[$mainCategoriesName]??[] as $subItemName)
                                            <option value="{{ $subItemName }}"> {{ $subItemName }} </option>
                                            @endforeach
                                        </select>
                                    </div>



                                </div>
                                <div class="chartdiv_two_lines" id="three-line-chart-{{ convertStringToClass($mainCategoriesName) }}-id"></div>
								
								@foreach($chartData['three_lines'][$mainCategoriesName]??[] as $chartName => $currentChartData )
								<input type="hidden" class="three-line-chart-{{ convertStringToClass($mainCategoriesName) }}-data-class"   data-chart-name="{{ $chartName }}" data-chart-data="{{ json_encode($currentChartData) }}">
								@endforeach
                            </div>
							
                        </div>

                    </div>

                    {{-- <div class="tab-pane" id="FullySecuredOverdraftkt_apps_contacts_view_tab_2" role="tabpanel">
                        <div class="col-md-12">
                            <div class="kt-portlet kt-portlet--mobile">

                                <div class="kt-portlet__body">

                                    <!--begin: Datatable -->
                                    <x-table :tableClass="'kt_table_with_no_pagination_no_scroll_no_entries'">
                                        @slot('table_header')
                                        <tr class="table-active text-center">
                                            <th class="text-center max-w-300">{{ __('Sub Category Name') }}</th>
                                            <th class="text-center ">{{ __('Amount') }}</th>
                                            <th class="text-center ">{{ __('% Of Total') }}</th>
                                            <th class="text-center ">{{ __('% Of Revenues') }}</th>
                                        </tr>
                                        @endslot
                                        @slot('table_body')


                                        @foreach ([] as $key => $item)
                                        <tr>

                                            <td class=" max-w-300">{{ '-'}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                            <td class="text-center">{{number_format(0)}}</td>
                                        </tr>
                                        @endforeach

                                        <tr class="table-active text-center">
                                            <td>{{__('Total')}}</td>
                                            <td>{{number_format(0)}}</td>
                                            <td>{{number_format(0)}}</td>
                                            <td>{{number_format(0)}}</td>

                                        </tr>
                                        @endslot
                                    </x-table>

                                    <!--end: Datatable -->
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="FullySecuredOverdrafttotal_available_room" data-total="{{ json_encode( [] ) }}">
                    </div> --}}
                </div>
            </div>
        </div>

    </div>
	@endforeach
    {{-- End Secured Overdraft --}}


</div>

<!--end:: Widgets/Stats-->


</div>

{{-- @php
    $index++;
    @endphp
    @endforeach --}}
</div>
@endsection
@section('js')
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<!-- Resources -->
<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>








<!--begin::Page Scripts(used by this page) -->
<script src="{{url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js')}}" type="text/javascript"></script>
{{-- pie chart --}}
<script>
 am4core.ready(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("pie-chart-general-id", am4charts.PieChart);

        // Add data
        chart.data = $('#pie-chart-general-data-id').data('chart-data');
	
        // Add and configure Series
        var pieSeries = chart.series.push(new am4charts.PieSeries());
        pieSeries.dataFields.value = "value";
        pieSeries.dataFields.category = "name";
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
                chart.legend.position = "bottom";
            chart.legend.scrollable = true;


    }); 
	
	
	//three lines chart
	
	
	  am4core.ready(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("three-line-chart-general-id", am4charts.XYChart);
		var data = [];
        //
        // Increase contrast by taking evey second color
        chart.colors.step = 2;

        // Add data
        chart.data = data;

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

        createAxisAndSeries("monthly_expense_value", "{{ __('Monthly Expense Value') }}", false, "circle");
        createAxisAndSeries("growth_rate", "{{ __('Growth Rate %') }}", true, "triangle");
         createAxisAndSeries("revenue_percentage", "{{ __('Revenue %') }}", true, "rectangle");

        // Add legend
        chart.legend = new am4charts.Legend();

        // Add cursor
        chart.cursor = new am4charts.XYCursor();



    }); // end am4core.ready()
	

</script>


@foreach($chartData['pie']??[] as $pieChartName => $pieChartValueArr)
@if($pieChartName == 'general')

@continue
@endif 
@php
	$pieChartNameAsClass = convertStringToClass($pieChartName);
	$pieChartId = "pie-chart-".$pieChartNameAsClass."-id";
	$pieChartDataId = "pie-chart-".$pieChartNameAsClass."-data-id";
@endphp
<script>
 am4core.ready(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("{{ $pieChartId }}", am4charts.PieChart);

        // Add data
        chart.data = $('#{{ $pieChartDataId }}').data('chart-data');
	
        // Add and configure Series
        var pieSeries = chart.series.push(new am4charts.PieSeries());
        pieSeries.dataFields.value = "value";
        pieSeries.dataFields.category = "name";
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
         //chart.legend = new am4charts.Legend();
          //      chart.legend.position = "right";
          //  chart.legend.scrollable = true;


    }); 
	

</script>

@endforeach 













@foreach($chartData['three_lines']??[] as $threeLineChartName => $pieChartValueArr)
@if($threeLineChartName == 'general')

@continue
@endif 
@php
	$threeLineChartNameAsClass = convertStringToClass($threeLineChartName);
	$threeLineChartId = "three-line-chart-".$threeLineChartNameAsClass."-id";

	//$pieChartDataId = "three-line-chart-".$threeLineChartNameAsClass."-data-id";
@endphp
<script>
	
	  am4core.ready(function() {

        // Themes begin
        am4core.useTheme(am4themes_animated);
        // Themes end

        // Create chart instance
        var chart = am4core.create("{{ $threeLineChartId }}", am4charts.XYChart);
		var data = [];
        //
        // Increase contrast by taking evey second color
        chart.colors.step = 2;

        // Add data
        chart.data = data;

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

        createAxisAndSeries("monthly_expense_value", "{{ __('Monthly Expense Value') }}", false, "circle");
        createAxisAndSeries("growth_rate", "{{ __('Growth Rate %') }}", true, "triangle");
    	 createAxisAndSeries("revenue_percentage", "{{ __('Revenue %') }}", true, "rectangle");

        // Add legend
        chart.legend = new am4charts.Legend();

        // Add cursor
        chart.cursor = new am4charts.XYCursor();



    }); // end am4core.ready()

</script>

@endforeach 








<script>
    $(document).on('change', 'select[js-refresh-three-line-chart]', function(e) {
		let chartName = $(this).val();
		let chartType = $(this).attr('data-type');
		let chartDataArr = $('.three-line-chart-'+chartType+'-data-class[data-chart-name="'+chartName+'"]').attr('data-chart-data')
		let currentChartId = 'three-line-chart-'+chartType+'-id';
		if(chartDataArr){
			chartDataArr = JSON.parse(chartDataArr);
		}else{
			chartDataArr = {};
		}
	
 		  am4core.registry.baseSprites.find(c => c.htmlContainer.id === currentChartId).data = chartDataArr		
    })



</script>
<script>
$(function(){
	    $('select[js-refresh-three-line-chart]').trigger('change')
})
</script>

{{-- <script src="{{url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js')}}" type="text/javascript"></script> --}}

<!--end::Page Scripts -->

@endsection
