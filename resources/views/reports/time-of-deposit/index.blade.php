@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
@php
use \App\Models\TimeOfDeposit;
@endphp
<style>
    .money-flow-dark input[type="checkbox"] {
        cursor: pointer;
    }

    .money-flow-dark .bank-max-width {
        max-width: 200px !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Time Of Deposit ' ) }} [{{ $financialInstitution->getName() }}]
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == TimeOfDeposit::RUNNING ?'active':'' }}" data-toggle="tab" href="#{{ TimeOfDeposit::RUNNING }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Running Time Of Deposit') }}
                    </a>
                </li>

                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == TimeOfDeposit::MATURED ?'active':'' }}" data-toggle="tab" href="#{{ TimeOfDeposit::MATURED }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Matured Time Of Deposit') }}
                    </a>
                </li>
				
				
				 <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == TimeOfDeposit::BROKEN ?'active':'' }}" data-toggle="tab" href="#{{ TimeOfDeposit::BROKEN }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Broken Time Of Deposit') }}
                    </a>
                </li>
				


            </ul>
@if(hasAuthFor('create time of deposit'))
           <div class="flex-tabs">
		    <a href="{{ route('create.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id]) }}" class="btn  active-style btn-icon-sm align-self-center">
                <i class="fas fa-plus"></i>
                {{ __('New Record') }}
            </a>
		   </div>
		   @endif 
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            <!--Begin:: Tab Content-->
			@php
				$currentType = TimeOfDeposit::RUNNING ;
			@endphp
            <div class="tab-pane {{ !Request('active') || Request('active') == $currentType  ? 'active'  :  '' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">

                    <x-table-title.with-two-dates :type="$currentType" :title="__('Running Time Of Deposit')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-time-of-deposit :financialInstitution="$financialInstitution" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('create.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])}}" />
                    </x-table-title.with-two-dates>


                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Intreset Rate') }}</th>
                                    <th>{{ __('Interest Amount') }}</th>
                                    <th>{{ __('Blocked Against') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="text-nowrap">{{ $model->getStartDateFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getAccountNumber() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getCurrency() }}</td>
                                    <td>{{ $model->getInterestRateFormatted() }}</td>
                                    <td>{{ $model->getInterestAmountFormatted() }}</td>
                                    <td>{{ $model->getBlockedAgainstFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
											@if(hasAuthFor('create time of deposit'))
											@include('reports._integrated_modal',['model'=>$model])
											@include('reports.time-of-deposit.renewal-date._renew_modal')
											
											@include('reports.time-of-deposit.renewal-date._apply_periodic_interest')
                                            <a
											
											 data-toggle="modal" data-target="#apply-deposit-modal-{{ $model->id }}" type="button" class="btn 
											 
											 @if($model->isDueTodayOrGreater())
											 disabled 
											@endif 
											 
											  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Apply TD Deposit Maturity ') }}" href="#"><i class="fa fa-coins"></i></a>
											  
											 
                                            <div class="modal fade" id="apply-deposit-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('apply.deposit.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id ]) }}" method="post">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-left" id="exampleModalLongTitle">{{ __('Do You Want To Apply Deposit To This Time Of Deposit ?') }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
																
																 <div class="col-md-4 mb-4">
                                                                        <label>{{__('TD Amount')}} </label>
                                                                        <div class="kt-input-icon">
                                                                            <input value="{{  $model->getAmount()  }}" disabled  class="form-control">
                                                                        </div>
                                                                    </div>


                                                                    <div class="col-md-4 mb-4">
                                                                        <label>{{__('Interest Amount')}} </label>
                                                                        <div class="kt-input-icon">
																		@php
																	///		$interestAmount = $model->isMatured() ? $model->getActualInterestAmount() : $model->getInterestAmount();
																		///	$interestAmount = $model->isPeriodically() ? 0 : $interestAmount;
																		@endphp
                                                                            <input value="{{  0}}" type="text" name="actual_interest_amount" class="form-control only-greater-than-or-equal-zero-allowed">
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-4 mb-4">
                                                                        <label>{{__('Deposit Date')}}</label>
                                                                        <div class="kt-input-icon">
                                                                            <div class="input-group date">
                                                                                <input required type="text" name="deposit_date" value="" class="form-control kt_datepicker_max_date_is_today" readonly placeholder="Select date" i />
                                                                                <div class="input-group-append">
                                                                                    <span class="input-group-text">
                                                                                        <i class="la la-calendar-check-o"></i>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>




                                                                </div>


                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn bg-green text-white">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											
											
											
											 @endif 
											
											
												@if(hasAuthFor('create time of deposit'))
											<a data-toggle="modal" data-target="#apply-break-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-danger   btn-icon" title="{{ __('Break') }}" href="#"><i class="fa fa-ban"></i></a>
                                            <div class="modal fade" id="apply-break-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('apply.break.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id ]) }}" method="post">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Break This Time Of Deposit ?') }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">

                                                                   

                                                                    <div class="col-md-4 mb-4">
                                                                        <label>{{__('Break Date')}}</label>
                                                                        <div class="kt-input-icon">
                                                                            <div class="input-group date">
                                                                                <input required type="text" name="break_date" value="" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                                                                <div class="input-group-append">
                                                                                    <span class="input-group-text">
                                                                                        <i class="la la-calendar-check-o"></i>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
																	
																	
																	 <div class="col-md-4 mb-4">
                                                                        <label>{{__('Amount')}} </label>
                                                                        <div class="kt-input-icon">
																			<input type="hidden" name="amount" value="{{ $model->getAmount() }}" >
                                                                            <input disabled value="{{  $model->getAmountFormatted()  }}" type="text"  class="form-control only-greater-than-or-equal-zero-allowed">
                                                                        </div>
                                                                    </div>
																	
																	
																		 <div class="col-md-4 mb-4">
                                                                        <label>{{__('Break Interest Amount')}} </label>
                                                                        <div class="kt-input-icon">
                                                                            <input name="break_interest_amount" value="{{  0  }}" type="text"  class="form-control only-greater-than-or-equal-zero-allowed">
                                                                        </div>
                                                                    </div>
																	
																	 {{-- <div class="col-md-3 mb-4">
                                                                        <label>{{__('Break Charge Amount')}} </label>
                                                                        <div class="kt-input-icon">
                                                                            <input name="break_charge_amount" value="{{  0  }}" type="text"  class="form-control only-greater-than-or-equal-zero-allowed">
                                                                        </div>
                                                                    </div> --}}
																	
																	



                                                                </div>


                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-green">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											@endif 
											

	@if(hasAuthFor('update time of deposit'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											
												@if(hasAuthFor('delete time of deposit'))
                                            <a data-toggle="modal" data-target="#delete-time-of-deposits-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
									



                                            <div class="modal fade" id="delete-time-of-deposits-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											
													@endif 
													
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
			
			
			
			
			
			
			
			
			
			
					@php
				$currentType = TimeOfDeposit::MATURED ;
			@endphp
            <div class="tab-pane {{  Request('active') == $currentType  ? 'active'  :  '' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">

                    <x-table-title.with-two-dates :type="$currentType" :title="__('Matured Time Of Deposit')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-time-of-deposit :financialInstitution="$financialInstitution" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('create.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])}}" />
                    </x-table-title.with-two-dates>


                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Intreset Rate') }}</th>
                                    <th>{{ __('Interest Amount') }}</th>
                                    <th>{{ __('Deposit Date') }}</th>
                                    <th>{{ __('Actual Interest Amount') }}</th>
                                    <th>{{ __('Blocked Against') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="text-nowrap">{{ $model->getStartDateFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getAccountNumber() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getCurrency() }}</td>
                                    <td>{{ $model->getInterestRateFormatted() }}</td>
                                    <td>{{ $model->getInterestAmountFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getDepositDateFormatted() }}</td>
                                    <td>{{ $model->getActualInterestAmountFormatted() }}</td>
									     <td>{{ $model->getBlockedAgainstFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._integrated_modal',['model'=>$model])
										@include('reports.time-of-deposit.renewal-date._renew_modal')
                                            <a data-toggle="modal" data-target="#reverse-deposit-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Reverse Deposit') }}" href="#"><i class="fa fa-undo"></i></a>
                                            <div class="modal fade" id="reverse-deposit-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('reverse.deposit.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id ]) }}" method="post">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Send This Time To Running ?') }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            {{-- <div class="modal-body">
                                                                <div class="row mb-3">

                                                                    <div class="col-md-4 mb-4">
                                                                        <label>{{__('Interest Amount')}} </label>
                                                                        <div class="kt-input-icon">
                                                                            <input value="{{ is_null($model->actual_interest_amount) ? $model->getInterestAmount() : $model->actual_interest_amount }}" type="text" name="actual_interest_amount" class="form-control only-greater-than-or-equal-zero-allowed">
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-4 mb-4">
                                                                        <label>{{__('Deposit Date')}}</label>
                                                                        <div class="kt-input-icon">
                                                                            <div class="input-group date">
                                                                                <input required type="text" name="actual_deposit_date" value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                                                                <div class="input-group-append">
                                                                                    <span class="input-group-text">
                                                                                        <i class="la la-calendar-check-o"></i>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>




                                                                </div>


                                                            </div> --}}
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

	
                                            {{-- <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-time-of-deposits-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a> --}}



                                            {{-- <div class="modal fade" id="delete-time-of-deposits-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('delete.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div> --}}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
			
			
			
			
			
			
			
			
			
			
			
			@php
				$currentType = TimeOfDeposit::BROKEN ;
			@endphp
            <div class="tab-pane {{  Request('active') == $currentType  ? 'active'  :  '' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">

                    <x-table-title.with-two-dates :type="$currentType" :title="__('Broken Time Of Deposit')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-time-of-deposit :financialInstitution="$financialInstitution" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('create.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])}}" />
                    </x-table-title.with-two-dates>


                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Intreset Rate') }}</th>
                                    <th>{{ __('Interest Amount') }}</th>
                                    <th>{{ __('Broken Date') }}</th>
                                    <th>{{ __('Actual Interest Amount') }}</th>
                                    {{-- <th>{{ __('Blocked Against') }}</th> --}}
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="text-nowrap">{{ $model->getStartDateFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getAccountNumber() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getCurrency() }}</td>
                                    <td>{{ $model->getInterestRateFormatted() }}</td>
                                    <td>{{ $model->getInterestAmountFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getBreakDateFormatted() }}</td>
                                    <td>{{ $model->getBreakInterestAmountFormatted() }}</td>
                                    {{-- <td>{{ $model->getBlockedAgainstFormatted() }}</td> --}}
									
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._integrated_modal',['model'=>$model])
										@include('reports.time-of-deposit.renewal-date._renew_modal')
                                            <a data-toggle="modal" data-target="#reverse-broken-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Reverse Broken') }}" href="#"><i class="fa fa-undo"></i></a>
                                            <div class="modal fade" id="reverse-broken-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('reverse.broken.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id ]) }}" method="post">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Send This Time To Running ?') }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                      
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
			
			










            <!--End:: Tab Content-->



            <!--End:: Tab Content-->
        </div>
    </div>
</div>
</div>

@endsection
@section('js')
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
<script>


</script>



{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js') }}" type="text/javascript">
</script> --}}

{{-- <script>
    $(function() {
        $('#firstColumnId').trigger('change');
    })

</script> --}}

<script>
    $(document).on('click', '.js-close-modal', function() {
        $(this).closest('.modal').modal('hide');
    })

</script>
<script>
    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'start_date') {
            modal.find('.data-type-span').html('[ {{ __("Start Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'end_date') {
            modal.find('.data-type-span').html('[ {{ __("End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        }
        //else if(searchFieldName === 'balance_date') {
        //     modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
        //     $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        // }
        else {
            modal.find('.data-type-span').html('[ {{ __("Start Date") }} ]')
            $(modal).find('.search-field').prop('disabled', false);
        }
    })
    $(function() {

        $('.js-search-modal').trigger('change')

    })

</script>
@endsection
@push('js')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script> --}}
@endpush
