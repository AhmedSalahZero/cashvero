@extends('layouts.dashboard')
@section('Title')
<style>
tbody td{
	font-weight:bold;
	color:black !important ;
}
</style>
<span class="kt-portlet__head-icon">
    <i class="kt-font-brand flaticon2-line-chart fa-fw flaticon-house-sketch pull-{{__('left')}}"></i>
    {{ __('Loan Calculator') . ' ( ' .str_to_upper(Request()->segments()[count(Request()->segments())-1]) . ' )'}}
</span>
@endsection
@section('css')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.4/kt-2.7.0/r-2.3.0/rg-1.2.0/rr-1.2.8/sc-2.0.7/sb-1.3.4/sp-2.0.2/sl-1.4.0/sr-1.1.1/datatables.min.css"/>

@endsection



@section('content')
@if(isset($storeByAjax) && $storeByAjax)
<input type="hidden" id="store-by-ajax">
<input type="hidden" id="loanTypeId" value="{{ $loanType }}">
<input type="hidden" id="page-is-loading">

@endif
@if(isset($triggerClick))
<input type="hidden" id="trigger_click" value="1">
@endif 
<!-- end::Sticky Toolbar -->
<div class="kt-portlet">
    @if(Session::has('success'))
    <div class="alert alert-success">
        <ul>
            <li>{{Session::get('success')}}</li>
        </ul>
    </div>
    @endif
</div>
<h3 class="font-weight-bold text-white form-label kt-subheader__title small-caps mr-5 text-nowrap" style="">{{ $title }}</h3>

<form class="kt-form kt-form--label-right" id="create-form" method="POST" action="{{ route('loan2.store',['company' => $company->id]) }}">
    {{ csrf_field() }}


    <div class="kt-portlet">
        <div class="kt-portlet__body">


            <div class="row">
                @if($type =='fixed')
                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Fixed Loan Type')}}</label><span class="astric">*</span>
                        <div class="form-group-sub">
                            <select name="fixed_loan_type" id="fixed_loan_type" class="form-control">
                                @foreach(getFixedLoanTypes() as $fixedType)
                                <option value="{{ $fixedType }}" {{ @old('fixed_loan_type') == $fixedType ? 'selected' : '' }}
                                
                                {{ isset($loan) && ($loan->fixedType == $fixedType) ? 'selected' : ''  }}
                                
                                >{{str_to_upper($fixedType)}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('installment_interval'))
                            <div class="invalid-feedback">{{ $errors->first('fixed_loan_type') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                @endif


                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Loan Start Date')}}
                            <span class="astric">*</span>

                        </label>
                        <div class="form-group-sub">
                            <input

                            @if(isset($longTermFunding) && $longTermFunding->date  )
                            value="{{\Carbon\Carbon::make($longTermFunding->date)->format('Y-m-d')}}"
                            
                            readonly
                            @elseif(Request()->has('date'))
                            value="{{\Carbon\Carbon::make(Request()->get('date'))->format('Y-m-d')}}"
                            readonly
                            @endif 
                             required type="date" id="start-date" name="start_date" class="form-control number interval-calcs" placeholder="{{__('Loan Start Date')}} {{__('Autoload')}}  .." />
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Loan Amount')}}
                            <span class="astric">*</span>
                        </label>
                        @if(isset($longTermFunding))
                        <input type="hidden" name="company_id" value="{{$longTermFunding->company_id}}">
                        <input type="hidden" name="financial_id" value="{{$longTermFunding->financial_id}}">
                        <input type="hidden" name="long_term_funding_id" value="{{$longTermFunding->id}}">

                         @elseif(Request()->has('financial_id'))
                        <input type="hidden" name="long_term_funding_id" value="{{$longTermFunding ? $longTermFunding->id : Request('long_term_funding_id')}}">
                         <input type="hidden" name="company_id" value="{{Request()->segment(3)}}">
                        <input type="hidden" name="financial_id" value="{{Request()->get('financial_id')}}">

                        @endif 

                       
                        <div class="form-group-sub">
                            <input
                            @if(isset($longTermFunding) && $longTermFunding->long_term_banking_facility_amount)

                            value="{{$longTermFunding->long_term_banking_facility_amount}}"
                            readonly
                            @elseif(Request()->has('current_amount'))
                                value="{{ Request()->get('current_amount') }}"
                            readonly

                            @else
                                value="{{ @old('loan_amount') }}"

                            @endif 
                            
                             type="number" step="any" id="loan_amount" name="loan_amount" class="form-control number" placeholder="{{__('Loan Amount')}} .." required />
                            @if ($errors->has('loan_amount'))
                            <div class="invalid-feedback">{{ $errors->first('loan_amount') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($type == 'fixed')
                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Base Rate % ')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input 
                            @if($loan && $loan->base_rate)
                            value="{{$loan->base_rate ?: old('base_rate')}}"
                            @endif 
                            type="number" step="any" id="base_rate" name="base_rate" class="form-control number pricing-calc-item" placeholder="{{__('Base Rate')}} .." required />
                            @if ($errors->has('base_rate'))
                            <div class="invalid-feedback">{{ $errors->first('base_rate') }}</div>
                            @endif
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Margin Rate % ')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input
                              @if($loan && $loan->margin_rate)
                              value="{{$loan->margin_rate ?: old('margin_rate') }}"
                              @endif 
                             type="number" step="any" id="margin_rate" name="margin_rate"  class="form-control number pricing-calc-item" placeholder="{{__('Margin Rate')}} .." required />
                            @if ($errors->has('margin_rate'))
                            <div class="invalid-feedback">{{ $errors->first('margin_rate') }}</div>
                            @endif
                        </div>
                    </div>
                </div>



                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Pricing %')}}
                            {{-- <span class="astric">*</span> --}}
                        </label>
                        <div class="form-group-sub">
                            <input
                               @if($loan && $loan->pricing)
                                value="{{$loan->pricing ?: old('pricing') }}"
                            
                               @else 
                                value="{{ @old('pricing') }}"
                             @endif 

                             disabled type="number" step="any" min="0" id="pricing" name="pricing"  class="form-control number pricing-calc-item" placeholder="{{__('Pricing')}} .." required />
                            @if ($errors->has('pricing'))
                            <div class="invalid-feedback">{{ $errors->first('pricing') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($type =='fixed')
                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Tenor (Duration In Months) ')}}
                        </label><span class="astric">*</span>
                        <div class="form-group-sub">
                            <input 
                             @if($loan && $loan->duration)
                                value="{{$loan->duration ?: old('duration') }}"
                      
                               @else 
                                value="{{ @old('duration') }}"
                             @endif 

                            type="number" step="1" min="1" max="600" id="duration" name="duration"  class="form-control number  grace_period_calc max-tenor-limit installment_condition" placeholder="{{__('Duration In Months')}} .." required />
                            @if ($errors->has('duration'))
                            <div class="invalid-feedback">{{ $errors->first('duration') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Grace Period')}} ( {{__('Months')}} )
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input 
                             @if($loan && $loan->grace_period)
                                value="{{$loan->grace_period ?: old('grace_period') }}"
                            
                               @else 
                                value="{{ @old('grace_period') }}"
                             @endif 

                            type="text" step="any" id="grace_periodid" name="grace_period" class="form-control number  grace-period-class grace_period_calc installment_condition" placeholder="{{__('Grace Period')}} .." />
                            @if ($errors->has('grace_period'))
                            <div class="invalid-feedback">{{ $errors->first('grace_period') }}</div>
                            @endif
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Capitalization Type')}}</label>
                        <div class="form-group-sub">
                            <select disabled name="capitalization_type" id="capitalization_type" class="form-control">
                                <option value="with_capitalization" {{ @old('capitalization_type') == 'with_capitalization' ? 'selected' : '' }}
                                
                                          @if($loan && $loan->capitalization_type == 'with_capitalization')
                            selected
                 
                             @endif 

                                >{{__('With Capitalization')}}</option>
                                <option 
                                    @if($loan && $loan->capitalization_type == 'without_capitalization')
                            selected
                 
                             @endif 

                                
                                value="without_capitalization" {{ @old('capitalization_type') == 'without_capitalization' ? 'selected' : '' }}>{{__('Without Capitalization')}}</option>
                            </select>
                            @if ($errors->has('capitalization_type'))
                            <div class="invalid-feedback">{{ $errors->first('capitalization_type') }}</div>
                            @endif
                        </div>
                    </div>
                </div>



                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Installment Payment Interval')}}</label><span class="astric">*</span>
                        <div class="form-group-sub">
                            <select name="installment_interval" id="installment_interval" class="form-control installment_condition">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                                <option
                                  @if($loan && $loan->installment_interval == 'monthly')
                            selected
                 
                             @endif 

                                 value="monthly" {{ @old('installment_interval') == 'monthly' ? 'selected' : '' }} data-order="1">{{__('Monthly')}}</option>
                                <option 
                                  @if($loan && $loan->installment_interval == 'quarterly')
                            selected
                 
                             @endif 

                                value="quarterly" {{ @old('installment_interval') == 'quarterly' ? 'selected' : '' }} data-order="2">{{__('Quarterly')}}</option>
                                <option
                                @if($loan && $loan->installment_interval == 'semi annually')
                            selected
                 
                             @endif 

                                 value="semi annually" {{ @old('installment_interval') == 'semi annually' ? 'selected' : '' }} data-order="3">{{__('Semi-annually')}}</option>
                            </select>
                            @if ($errors->has('installment_interval'))
                            <div class="invalid-feedback">{{ $errors->first('installment_interval') }}</div>
                            @endif
                        </div>
                    </div>
                </div>






                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Step-up Rate ( % ) ')}}
                            {{-- <span class="astric">*</span> --}}
                        </label><span class="astric">*</span>
                        <div class="" id="step-up-id">
                            <div class="form-group-sub">
                                <input 
                                  @if($loan && $loan->step_up_rate)
                                value="{{$loan->step_up_rate ?: old('step_up_rate') }}"
                            
                               @else 
                                value="{{ @old('step_up_rate') }}"
                             @endif 

                                type="number" step="any" min="0" max="100" id="step_up_rate" name="step_up_rate" class="form-control number" placeholder="{{__('Step-up Rate')}} .." required />
                                @if ($errors->has('step_up_rate'))
                                <div class="invalid-feedback">{{ $errors->first('step_up_rate') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @endif

                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Step-up Interval')}}</label><span class="astric">*</span>
                        <div class="form-group-sub">
                            <select name="step_up_interval" id="step_up_interval" class="form-control interval-calcs">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                                {{-- <option value="monthly" {{ @old('step_up_interval') == 'monthly' ? 'selected' : '' }}>{{__('Monthly')}}</option> --}}
                                <option
                                  @if($loan && $loan->step_up_interval == 'quarterly')
                            selected
                 
                             @endif 


                                 value="quarterly" {{ @old('step_up_interval') == 'quarterly' ? 'selected' : '' }}>{{__('Quarterly')}}</option>
                                <option
                                   @if($loan && $loan->step_up_interval == 'semi annually')
                            selected
                 
                             @endif 


                                 value="semi annually" {{ @old('step_up_interval') == 'semi annually' ? 'selected' : '' }}>{{__('Semi-annually')}}</option>
                                <option
                                
                                    @if($loan && $loan->step_up_interval == 'annually')
                            selected
                 
                             @endif 

                                 value="annually" {{ @old('step_up_interval') == 'annually' ? 'selected' : '' }}>{{__('Annually')}}</option>
                            </select>
                            @if ($errors->has('step_up_interval'))
                            <div class="invalid-feedback">{{ $errors->first('step_up_interval') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Step-down Rate ( % ) ') . ' ' . __('Please Insert Negative Number')}}
                            {{-- <span class="astric">*</span> --}}
                        </label>
                        <div class="form-group-sub">
                            <input
                            



      			    @if($loan && $loan->step_down_rate)
                                value="{{$loan->step_down_rate ?: old('step_down_rate') }}"
                            
                               @else 
                                value="{{ @old('step_down_rate') }}"
                             @endif 


                             type="text" 
                             
                             
                             step="any" {{-- min="-100" --}} {{-- max="0" --}} id="step_down_rate" name="step_down_rate" value="{{ @old('step_down_rate') }}" class="form-control negative-numbers" placeholder="{{__('Step-down Rate')}} .." required />
                            @if ($errors->has('step_down_rate'))
                            <div class="invalid-feedback">{{ $errors->first('step_down_rate') }}</div>
                            @endif
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Step-down Interval')}}</label> <span class="astric">*</span>
                        <div class="form-group-sub">
                            <select name="step_down_interval" id="step_down_interval" class="form-control interval-calcs">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                                <option 
                                      @if($loan && $loan->step_down_interval == 'quarterly')
                            selected
                 
                             @endif 

                                value="quarterly" {{ @old('step_down_interval') == 'quarterly' ? 'selected' : '' }}>{{__('Quarterly')}}</option>
                                <option 
                                      @if($loan && $loan->step_down_interval == 'semi annually')
                            selected
                 
                             @endif 

                                value="semi annually" {{ @old('step_down_interval') == 'semi annually' ? 'selected' : '' }}>{{__('Semi-annually')}}</option>
                                <option 
                                      @if($loan && $loan->step_down_interval == 'annually')
                            selected
                 
                             @endif 

                                value="annually" {{ @old('step_down_interval') == 'annually' ? 'selected' : '' }}>{{__('Annually')}}</option>
                            </select>
                            @if ($errors->has('step_down_interval'))
                            <div class="invalid-feedback">{{ $errors->first('step_down_interval') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
    <!-- //////////////////////////////LOAN TYPE////////////////////////////// -->
    <input type="hidden" value="{{$type}}" name="loan_type" />
    <!-- //////////////////////////////LOAN Interests////////////////////////////// -->
    @if($type != 'fixed')
    <div class="kt-portlet" id="view_loan_interest" style="{{@old('loan_type')  == 'variable' || @old('loan_type')  == 'fixed' ? 'display: block' : 'display:none'}}">
        <div class="kt-portlet__body">
            <div class="row">
                <div class="col-md-5" id="view_borrowing_rate" style="{{@old('loan_type')  == 'variable'? 'display: block' : 'display:none'}}">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Borrowing Rate')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input 
                                  @if($loan && $loan->borrowing_rate)
                                value="{{$loan->borrowing_rate ?: old('borrowing_rate') }}"
                            
                               @else 
                                value="{{ @old('borrowing_rate') }}"
                             @endif 
                            type="number" step="any" id="borrowing_rate" name="borrowing_rate"  class="form-control number" placeholder="{{__('Borrowing Rate')}} .." />
                            @if ($errors->has('borrowing_rate'))
                            <div class="invalid-feedback">{{ $errors->first('borrowing_rate') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group validated">
                        <label class="col-form-label take " id="interest_label">
                            {{@old('loan_type') == 'variable'? __('Interest Margin') : __('Interest')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input 
                               @if($loan && $loan->margin_interest)
                                value="{{$loan->margin_interest ?: old('margin_interest') }}"
                            
                               @else 
                                value="{{ @old('margin_interest') }}"
                             @endif 

                            
                            type="number" step="any" id="margin_interest" name="margin_interest"  class="form-control number" placeholder="{{__('Interest Margin')}} .." />
                            @if ($errors->has('margin_interest'))
                            <div class="invalid-feedback">{{ $errors->first('margin_interest') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-2" id="view_interest" style="{{@old('loan_type')  == 'variable'? 'display: block' : 'display:none'}}">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Loan Interest')}}
                        </label>
                        <div class="form-group-sub">
                            <input
                             @if($loan && $loan->loan_interest)
                                value="{{$loan->loan_interest ?: old('loan_interest') }}"
                            
                               @else 
                                value="{{ @old('loan_interest') }}"
                             @endif 

                             type="text" step="any" id="loan_interest" name="loan_interest"  class="form-control number" placeholder="{{__('Loan Interest')}}.." disabled />
                        </div>
                    </div>
                </div>
            </div>
            <div class="row" id="view_min_interest" style="{{@old('loan_type')  == 'variable'? 'display: block' : 'display:none'}}">
                <div class="col-md-12">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Min Interest')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input
                            @if($loan && $loan->min_interest)
                                value="{{$loan->min_interest ?: old('min_interest') }}"
                            
                               @else 
                                value="{{ @old('min_interest') }}"
                             @endif 

                             type="number" step="any" id="min_interest" name="min_interest" value="{{ @old('min_interest') }}" class="form-control number" placeholder="{{__('Min Interest')}} .." />
                            @if ($errors->has('min_interest'))
                            <div class="invalid-feedback">{{ $errors->first('min_interest') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @if($type !='fixed')

    <div class="kt-portlet" id="variable_term_dev" style="{{@old('loan_type')  == 'variable' || @old('loan_type')  == 'fixed'  ? 'display: block' : 'display:none'}}">
        <div class="kt-portlet__body">
            {{-- <h3 id="loan_choosen_type">{{@old('loan_type')  == 'variable'? 'Variable Installment' : 'Fixed Installment'}}</h3> --}}

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Tenor Duration')}} ( {{__('Months')}} )
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input 
                            @if($loan && $loan->repayment_duration)
                                value="{{$loan->repayment_duration ?: old('repayment_duration') }}"
                            
                               @else 
                                value="{{ @old('repayment_duration') }}"
                             @endif 

                            type="number" step="any" max="600" id="repayment_duration" name="repayment_duration" value="{{ @old('repayment_duration') }}" class="form-control number max-tenor-limit" placeholder="{{__('Duration')}} .." />
                            @if ($errors->has('repayment_duration'))
                            <div class="invalid-feedback">{{ $errors->first('repayment_duration') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-4" id="view_grace_period">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Grace Period')}} ( {{__('Months')}} )
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input type="text" step="any" id="grace_periodid" name="grace_period" value="{{ @old('grace_period') }}" class="form-control number grace-period-class grace_period_calc installment_condition" placeholder="{{__('Grace Period')}} .." />
                            @if ($errors->has('grace_period'))
                            <div class="invalid-feedback">{{ $errors->first('grace_period') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">

                <div class="col-md-4">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Installment Payment Interval')}}</label>
                        <div class="form-group-sub">
                            <select name="installment_interval" id="installment_interval" class="form-control">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                                <option value="monthly" {{ @old('installment_interval') == 'monthly' ? 'selected' : '' }} data-order="1">{{__('Monthly')}}</option>
                                <option value="quarterly" {{ @old('installment_interval') == 'quarterly' ? 'selected' : '' }} data-order="2">{{__('Quarterly')}}</option>
                                <option value="semi annually" {{ @old('installment_interval') == 'semi annually' ? 'selected' : '' }} data-order="3">{{__('Semi-annually')}}</option>
                            </select>
                            @if ($errors->has('installment_interval'))
                            <div class="invalid-feedback">{{ $errors->first('installment_interval') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4" id="view_interest_interval" style="{{@old('loan_type')  == 'variable' ? 'display: block' : 'display:none'}}">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Interest Payment Interval')}}</label>
                        <div class="form-group-sub">
                            <select name="interest_interval" id="interest_interval" class="form-control">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                            </select>
                            @if ($errors->has('interest_interval'))
                            <div class="invalid-feedback">{{ $errors->first('interest_interval') }}</div>
                            @endif
                        </div>
                    </div>
                </div>


            </div>
            <div class="row" id="viwe_installment_amount" style="{{@old('loan_type')  == 'variable' ? 'display: block' : 'display:none'}}">
                <div class="col-md-12">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Principle Payment Amount')}}
                        </label>
                        <div class="form-group-sub">
                            <input type="text" step="any" id="installment_amount" name="installment_amount" value="{{ @old('installment_amount') }}" class="form-control number" placeholder="{{__('Principle Payment Amount')}}.." disabled />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @push('js')
    {{-- <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.4/kt-2.7.0/r-2.3.0/rg-1.2.0/rr-1.2.8/sc-2.0.7/sb-1.3.4/sp-2.0.2/sl-1.4.0/sr-1.1.1/datatables.min.js"></script> --}}

    @endpush


    <div class="kt-portlet">
        <div class="kt-portlet__foot">
            <div class="kt-form__actions">
                <div class="row">
                    <div class="col-12">
                        <div class="{{__('right')}} text-right">
                            <input id="submit---id" type="submit" onclick="return false;" name="submit" value="{{__('Calculate')}}" onclick="return false;" class="btn active-style submit">
                        </div>
                           @if(isset($longTermFunding->financial_id))
                         <div class="{{__('left')}}">
                            <a href="{{route('fundingPlans.index',['company_id'=>$company->id , 'financial_id'=>$longTermFunding->financial_id])}}" class="btn btn-success  btn-sm" > {{__('Return To Funding Plan')}} </a>
                        </div>
                        @endif 
                        
                    </div>
                </div>
            </div>
        </div>
    </div>





</form>

{{-- <div class="" id="loan-table"></div> --}}
<a id="loan-table-a" href="#dynamic-datatable_wrapper"></a>
<div id="append-table-id">

</div>
@endsection


@section('js')
<script>
    $(document).on('change', 'input', function(e) {

    })
    $(document).on('change', 'select', function(e) {

    })

</script>
<script src="{{asset('back/assets/total_payment.js')}}" type="text/javascript"></script>

<script>
    var type = "{{$type}}";

    if (type == 'variable') {
        $('#interest_label').html('{{__('Interest Margin')}}');
        $('#view_min_interest').css('display', 'block');
        $('#view_borrowing_rate').css('display', 'block');
        $('#view_interest').css('display', 'block');
        $('#view_loan_interest').css('display', 'block');

        $('#loan_choosen_type').html('{{__('Variable Installment Loan')}}');
        $('#view_interest_interval').css('display', 'block');
        $('#view_grace_period').css('display', 'block');
        $('#viwe_installment_amount').css('display', 'block');
        $('#variable_term_dev').css('display', 'block');
        $('#short_term_dev').css('display', 'none');
        $('#settlement_duration').removeAttr('required');
        $('#repayment_duration').attr('required', true);
        $('#borrowing_rate').attr('required', true);
        $('#min_interest').attr('required', true);
        $('#grace_periodid').attr('required', true);
        $('#installment_interval').attr('required', true);
        $('#interest_interval').attr('required', true);
    }

</script>

<script>
    let start_date = '';

    //on change for the borrowing rate
    $(document).on('keyup', '#borrowing_rate', function() {
        loanInterest();
    });
    //on change for the margin_interest rate
    $(document).on('keyup', '#margin_interest', function() {
        loanInterest();
    });
    //calculate loaninterest
    function loanInterest() {
        var margin_interest = +$('#margin_interest').val();
        var borrowing_rate = +$('#borrowing_rate').val();
        var total = borrowing_rate + margin_interest;
        $('#loan_interest').val(total + " % ");
    }




    //installment_interval
    function installmentIntervalChange() {
		
        var interval = $('#installment_interval').val();
		var select = '';
        if (interval != '') {
            $('#interest_interval option:not(:first)').remove();
            var loan_amount = +$('#loan_amount').val();
            var repayment_duration = +$('#repayment_duration').val();
            var installment_amount = 0;
            if (interval == 'monthly') {
                select = '<option value="monthly">Monthly</option>\n';

                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / (repayment_duration);
                }

            } else if (interval == 'quarterly') {
                select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                    '<option value="quarterly">{{__("Quarterly")}}</option>\n';
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 4);
                }
            } else if (interval == 'semi annually') {

                select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                    '<option value="quarterly">{{__("Quarterly")}}</option>\n' +
                    '<option value="semi annually">{{__("Semi-annually")}}</option>\n';

                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 2);
                }
            }

            $('#interest_interval').append(select);
            $('#installment_amount').val(installment_amount.toFixed(2));
        }
    }

    function installmentIntervalOld(loan_interval) {
        var interval = $('#installment_interval').val();
        var select = '';
        if (interval != '') {
            if (interval == 'monthly') {

                select = '<option value="monthly" selected >{{__("Monthly")}}</option>\n';


            } else if (interval == 'quarterly') {
                if (loan_interval == 'monthly') {
                    select = '<option value="monthly" selected>{{__("Monthly")}}</option>\n' +
                        '<option value="quarterly" >{{__("Quarterly")}}</option>\n';
                } else {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quarterly" selected>{{__("Quarterly")}}</option>\n';
                }


            } else if (interval == 'semi annually') {
                if (loan_interval == 'monthly') {
                    select = '<option value="monthly" selected>{{__("Monthly")}}</option>\n' +
                        '<option value="quarterly">{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually">{{__("Semi-annually")}}</option>\n';
                } else if (loan_interval == 'quarterly') {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quarterly" selected>{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually">Semi-{{__("Annually")}}</option>\n';
                } else {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quarterly">{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually" selected>{{__("Semi-annually")}}</option>\n';
                }

            }

            $('#interest_interval').append(select);
        }
    }
    $(document).on('change', '#installment_interval', function() {
        installmentIntervalChange();

    });
    //Installment Amount

    function instalmentAmount() {
        var interval = $('#installment_interval').val();
        if (interval != '') {
            var loan_amount = +$('#loan_amount').val();
            var repayment_duration = +$('#repayment_duration').val();
            var installment_amount = 0;
            if (interval == 'monthly') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / (repayment_duration);
                }

            } else if (interval == 'quarterly') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 4);
                }
            } else if (interval == 'semi annually') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 2);
                }
            }

            $('#installment_amount').val(installment_amount.toFixed(0));
        }
    }
    $(document).on('change', '#repayment_duration', function() {
        instalmentAmount();
    });
    $(document).on('change', '#loan_amount', function() {
        instalmentAmount();
    });




    $(document).on('keypress', '.number', function(e) {
        var keyCode = (e.which) ? e.which : e.keyCode;;
        /*
        8 - (backspace)
        32 - (space)
        48-57 - (0-9)Numbers
        */
        if ((keyCode != 8 || keyCode == 32) && (keyCode != 46 && keyCode > 31) && (keyCode < 48 || keyCode > 57)) {
            return false;
        }
    });

</script>


{{-- salah --}}

<script>
    $('#fixed_loan_type').on('change', function() {
        let loanType = $(this).val();
        if (loanType == 'step-up' ||
            loanType == 'grace_step-up_with_capitalization' ||
            loanType == 'grace_step-up_without_capitalization'
        ) {
            $('#step_up_rate').closest('.item-main-parent').fadeIn(300);
            $('#step_up_interval').closest('.item-main-parent').fadeIn(300);
        } else {

            $('#step_up_rate').val(0).closest('.item-main-parent').fadeOut(300);
            $('#step_up_interval').val(0).closest('.item-main-parent').fadeOut(300);
        }


        if (loanType == 'step-down' ||
            loanType == 'grace_step-down_with_capitalization' ||
            loanType == 'grace_step-down_without_capitalization'

        ) {
            $('#step_down_rate').closest('.item-main-parent').fadeIn(300);
            $('#step_down_interval').closest('.item-main-parent').fadeIn(300);
        } else {

            $('#step_down_rate').val(0).closest('.item-main-parent').fadeOut(300);
            $('#step_down_rate').val(0).trigger('change');
            $('#step_down_interval').val(0).closest('.item-main-parent').fadeOut(300);
        }

        if (loanType != 'normal' && loanType != 'step-down' && loanType != 'step-up') {
            $('#grace_periodid').val(0).closest('.item-main-parent').fadeIn(300);
            $('#capitalization_type').val(0).closest('.item-main-parent').fadeIn(300);
            if (loanType == 'grace_step-up_with_capitalization' || loanType == 'grace_period_with_capitalization' ||
                loanType == 'grace_step-down_with_capitalization'

            ) {
                $('#capitalization_type').find('option:nth-child(1)').prop('selected', true);

            } else {
                $('#capitalization_type').find('option:nth-child(2)').prop('selected', true);
            }
        } else {
            $('#grace_periodid').val(0).closest('.item-main-parent').fadeOut(300);
            $('#capitalization_type').closest('.item-main-parent').fadeOut(300);
        }


    }).trigger('change')


    $(document).on('keyup', '.pricing-calc-item', function() {
        let base_rate = $('#base_rate').val();
        let margin_rate = $('#margin_rate').val();
        if (isPercentageNumber(base_rate) && isPercentageNumber(margin_rate)) {
            let pricing = parseFloat(base_rate) + parseFloat(margin_rate);
            $('#pricing').val(pricing);
        } else if (isPercentageNumber(base_rate)) {
            let pricing = parseFloat(base_rate);
            $('#pricing').val(pricing);
        } else if (isPercentageNumber(margin_rate)) {
            let pricing = parseFloat(margin_rate);
            $('#pricing').val(pricing);
        } else {
            $('#pricing').val(0);
        }

    })

    $(document).on('keyup', '.grace_period_calc', function() {
        let duration = parseFloat($('#duration').val());
        let gracePeriod = parseFloat($('#grace_periodid').val()) ? parseFloat($('#grace_periodid').val()) : 0;
        if (gracePeriod != 0 && gracePeriod >= duration - 1) {
            $('#grace_periodid').val(duration - 2);
        }
    });
    // $('#fixed_loan_type')

</script>

@if($type == 'fixed' )
<script>
    $(document).on('click', '.submit', function(e) {

        e.preventDefault();
        let visiableFields = getVisiablFields();
        if (!visiableFields.length && visiableFields.emptyFields.length) {
            alert("please Enter " + visiableFields.emptyFields[0].name)
            return;
        }

        let fixedType = $('#fixed_loan_type').val();

        let start_date = $('#start-date').val();

        let step_up = $('#step_up_interval').val();

        let step_down = $('#step_down_interval').val();
        let stepRate = (fixedType == 'step-up' || fixedType == 'grace_step-up_with_capitalization' ||
            fixedType == 'grace_step-up_without_capitalization'

        ) ? parseFloat($('#step_up_rate').val()) : (
            (fixedType == 'step-down' || fixedType == 'grace_step-down_with_capitalization' ||
                fixedType == 'grace_step-down_without_capitalization'

            )
        ) ? parseFloat($('#step_down_rate').val()) : 0;
        stepRate = stepRate / 100;

        let applied_step =
            (fixedType == 'step-up' || fixedType == 'grace_step-up_with_capitalization' ||
                fixedType == 'grace_step-up_without_capitalization'

            ) ? step_up : (
                (fixedType == 'step-down' || fixedType == 'grace_step-down_with_capitalization' ||
                    fixedType == 'grace_step-down_without_capitalization'

                )
            ) ? step_down : 0

        let period = parseFloat($('#duration').val());
        let start_date_formatted = new Date(start_date);
        let end_date_end = addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0));
		
        let end_date_formatted = addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0)).getFullYear() + '-' + (addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0)).getMonth() + 1) + '-' + addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0)).getDate();
        let interval = 0;
        let installment_interval = $('#installment_interval').val();
        let loanAmount = parseFloat($('#loan_amount').val())
        let gracePeriod = parseFloat($('#grace_periodid').val()) ? parseFloat($('#grace_periodid').val()) : 0;
        if ($('#store-by-ajax').length ) {
            let base_rate = $('#base_rate').val();
            let margin_rate = $('#margin_rate').val();
            $.ajax({
                url: "{{ route('save.fixed.at.end',['company'=>$company->id]) }}"
                , data: {
                    "_token": "{{ __(csrf_token()) }}"
                    , 'gracePeriod': gracePeriod
                    , 'loanAmount': loanAmount
                    , 'installment_interval': installment_interval
                    , 'start_date': start_date
                    , 'end_date': end_date_formatted
                    , "period": period
                    , "applied_step": applied_step
                    , "stepRate": stepRate
                    , "fixedType": fixedType
                    , "step_down": step_down
                    , "step_up": step_up
                    , "step_down_rate": $('#step_down_rate').val()
                    , "step_up_rate": $('#step_up_rate').val()
                    , 'base_rate': base_rate
                    , 'margin_rate': margin_rate
                    , 'margin_interest': $('#margin_interest').val()
                    , 'pricing': $('#pricing').val()
                    , 'duration': $('#duration').val()
                    , 'step_up_interval': $('#step_up_interval').val()
                    , 'loan_interest': $('#loan_interest').val()
                    , 'min_interest': $('#min_interest').val()
                    , 'repayment_duration': $('#repayment_duration').val()
                    , 'installment_amount': $('#installment_amount').val()
                    , "loanType": $('#loanTypeId').val()
                    , 'company_id': "{{ $company->id }}",
                    'financial_id':$('input[name="financial_id"]').val(),
                    'long_term_funding_id':$('input[name="long_term_funding_id"]').val(),
                }
                , type: "POST",
                success:function(res){

                   $.ajax({
            url:"{{ route('save.loan.dates',['company'=>$company->id ]) }}",
            data:{
                "_token":"{{ csrf_token() }}",
                "data":window['dataToAjax'] ,
                "loan_id":res.loan_id,
                
            },
            type:"POST",
        })

                    
                    // saveToalForLoan()
                }
            })
        }


        if (installment_interval) {
            switch (installment_interval) {
                case 'monthly':
                    installment_payment_interval = 1;
                    break;

                case 'quarterly':
                    installment_payment_interval = 3;
                    break;

                case 'semi annually':
                    installment_payment_interval = 6;
                    break;


            }



        }
        switch (applied_step) {
            case 'quarterly':
                interval = 3;
                break;

            case 'semi annually':
                interval = 6;
                break;

            case 'annually':
                interval = 12;
                break;

            default:
                interval = 1;
                break;
        }

        let installmentStartDate = getInstallmentStartDate(new Date(start_date_formatted.getTime()), gracePeriod, installment_payment_interval);
        let stepFactor = calcStepFactor(period, interval, new Date(installmentStartDate.getTime()), addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0))); // object
        
		let daysCount = calDaysCount(new Date(start_date_formatted.getTime()), period, installment_payment_interval);

        let pricing = parseFloat($('#pricing').val()) / 100
        let intersetFactor = calcIntersetFactor(daysCount, pricing);
		
        let loanFactories = calcLoanFactor(fixedType, loanAmount, intersetFactor, gracePeriod, installment_payment_interval, new Date(start_date_formatted.getTime()), period
            , addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0))

        );

        let installmentFactories = calcInstallmentFactor(new Date(installmentStartDate.getTime()), intersetFactor, stepRate, stepFactor, period, installment_payment_interval);

        let installmentAmountArr = getInstallmentAmount(loanFactories, installmentFactories, stepRate, stepFactor, new Date(installmentStartDate.getTime()), addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0))
            , period, installment_payment_interval, interval);
			
        let FormattedData = {
            ...daysCount
            , ...intersetFactor
            , ...installmentAmountArr
        };
        let newDat = [];
        for (index in FormattedData.daysCount) {
            newDate = FormattedData.daysCount[index].date;
            obj = {};
            obj.date = newDate;
            var searchedDaysCount = FormattedData.daysCount.find((item) => {
                return item.date == getDateFormatted(new Date(FormattedData.daysCount[index].date))
            });

            var searchedInstallmentAmount = FormattedData.InstallmentAmountArr.find((item) => {
                return item.date == getDateFormatted(new Date(FormattedData.daysCount[index].date))
            });
            var searchedIntersetFactor = FormattedData.interestFactor.find((item) => {
                return item.date == getDateFormatted(new Date(FormattedData.daysCount[index].date))
            });
            obj.val = {
                "daysCount": searchedDaysCount.daysDiff
                , "InstallmentAmount": searchedInstallmentAmount ? searchedInstallmentAmount.amount : 0
                , "interestFactor": searchedIntersetFactor ? searchedIntersetFactor.interestFactor : 0
            };

            newDat.push(obj);
        }
        formatTable(newDat, loanAmount, fixedType);
    })

    function calcIntersetFactor(daysCount, pricing) {
        intersetFactor = [];
        for (let i = 0; i < daysCount.daysCount.length; i++) {
            interset = (pricing / 360) * (daysCount.daysCount[i].daysDiff);
            obj = {};
            obj.date = daysCount.daysCount[i].date;
            obj.interestFactor = interset;
            intersetFactor.push(obj);

        }

        return {
            "interestFactor": intersetFactor
        };
    }

    function calDaysCount(start_date, interval, installment_payment_interval) {
        days = [];

        obj = {};
        obj.date = getDateFormatted(new Date(start_date.getTime()));
        obj.daysDiff = 0;
        days.push(obj);
        for (let i = 0; i < interval; i = i + installment_payment_interval) {
            firstMonth = new Date(start_date.getTime());
            let secondMonth = addMonths(start_date, installment_payment_interval);
            let diffInDays = getDifferenceBetweenTwoDatesInDays(firstMonth, secondMonth);

            obj = {};
            obj.date = getDateFormatted(new Date(secondMonth.getTime()));
            obj.daysDiff = diffInDays;
            days.push(obj);

            start_date = new Date(secondMonth.getTime());


        }
		
        return {
            "daysCount": days
        };
    }

    function addMonths(date, months) {

        let currentDate = parseInt($('#start-date').val().split('-')[2]);

        let newDate = date.addMonths(months);
        if (false) {
           
        } else if (currentDate <= 30) {
            if (newDate.getMonth() == 01) // feb
            {
                if (currentDate <= 28) {
                    return new Date(newDate.setDate(currentDate));
                } else {
                    return new Date(newDate.setDate(new Date(newDate.getFullYear(), newDate.getMonth() + 1, 0).getDate()));
                }
            } else {
                return new Date(newDate.setDate(
                    currentDate
                ));
            }

        } else {
            return new Date(newDate.setDate(new Date(newDate.getFullYear(), newDate.getMonth() + 1, 0).getDate()));

        }


        let formDate = $('#start-date').val();
        let formDay = parseFloat(formDate.split('-')[2]);
        let formMonth = parseFloat(formDate.split('-')[1])
        if (formDay == 31) {

            day = (new Date(date.getFullYear(), date.getMonth() + months, 0)).getDate();
            currentMonth = (new Date(date.getFullYear(), date.getMonth() + months, 0)).getMonth() + 1;
            currentYear = (new Date(date.getFullYear(), date.getMonth() + months, 0)).getFullYear();
            return new Date(currentYear + '-' + currentMonth + '-' + day);
        } else {
            date.setMonth(date.getMonth() + months);
        }

        return date;
    }
	function getDifferenceBetweenTwoDatesInDays(a, b) {
  const _MS_PER_DAY = 1000 * 60 * 60 * 24;
  // Discard the time and time-zone information.
  const utc1 = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
  const utc2 = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());

  return Math.floor((utc2 - utc1) / _MS_PER_DAY);
}
 
    function calcStepFactor(period, interval, installmentStartDate, end_date) {
        counter = 0;
        let stepFactor = [];
        for (let i = 0; i <= period; i++) {
            if (i % interval == 0 && i != 0) {
                counter = counter + 1;
            }

            obj = {};
            obj.date = getDateFormatted(addMonths(new Date(installmentStartDate.getTime()), i));
            obj.factory = counter;
            stepFactor.push(obj);

            if (end_date.getTime() == addMonths(new Date(installmentStartDate.getTime()), i).getTime()) {
                break;
            }

        }
        return {
            "stepFactors": stepFactor
        };

    }

    function calcLoanFactor(fixedLoanType, loanAmount, intersetFactor, gracePeriodVal, installment_payment_interval, start_date, interval, end_date_end) {
        // معامل القرض
        let gracePeriod = gracePeriodVal;

        if (fixedLoanType == 'grace_step-up_without_capitalization' || fixedLoanType == 'grace_step-down_without_capitalization' ||
            fixedLoanType == 'grace_period_without_capitalization'
        ) {
            loanFactorStartDate = addMonths(new Date(start_date.getTime()), installment_payment_interval + gracePeriod);
            var searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loanFactorStartDate)
            });

            var loanFactor = loanAmount * (1 + searchedInterestFactor.interestFactor);

        } else {
            loanFactorStartDate = addMonths(new Date(start_date.getTime()), installment_payment_interval);
            var searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loanFactorStartDate)
            });
            var loanFactor = loanAmount * (1 + searchedInterestFactor.interestFactor);
        }



        let loanFactoriesArr = [];

        obj = {};

        obj.date = getDateFormatted((new Date(start_date)));
	
        obj.loanFactor = 0;
        loanFactoriesArr.push(obj);


        obj = {};
        obj.date = getDateFormatted((new Date(loanFactorStartDate)));

        obj.loanFactor = loanFactor;
        loanFactoriesArr.push(obj);

        for (let i = 1; i <= (interval / installment_payment_interval); i++) {

            loopDate = addMonths(loanFactorStartDate, installment_payment_interval);
            searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });


            loanFactor = loanFactor + (loanFactor * searchedInterestFactor.interestFactor)


            obj = {};
            obj.date = getDateFormatted(new Date(loopDate.getTime()));
            obj.loanFactor = loanFactor;
            loanFactoriesArr.push(obj);

            if (end_date_end.getTime() == loopDate.getTime()) {
                break;
            }

        }
        return {
            "loanFactories": loanFactoriesArr
        };

    }

    function getInstallmentStartDate(loanStartDate, gracePeriod, installment_payment_interval) {

        let installmentDate = addMonths(loanStartDate, gracePeriod + installment_payment_interval);
        return installmentDate;
    }

    function getDateFormatted(yourDate) {
        const offset = yourDate.getTimezoneOffset()
        yourDate = new Date(yourDate.getTime() - (offset * 60 * 1000))
        return yourDate.toISOString().split('T')[0]
    }

    function calcInstallmentFactor(installmentStartDate, intersetFactor, stepRate, stepFactor, interval, installment_payment_interval) {
       
	   
        installmentFactors = [];
        installmentFactor = -1;

        obj = {};
        obj.date = getDateFormatted(new Date(installmentStartDate.getTime()));
        obj.installmentFactor = -1;
        installmentFactors.push(obj);
        for (let i = 1; i <= interval / installment_payment_interval; i++) {
            loopDate = addMonths(installmentStartDate, installment_payment_interval);
		
            searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

            stepFactorOfDate = stepFactor['stepFactors'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

            if (!searchedInterestFactor) {
                break;
            } else {
                installmentFactor = installmentFactor + (installmentFactor * searchedInterestFactor.interestFactor) - (1 * parseFloat(Math.pow((1 + parseFloat(stepRate)), parseFloat(stepFactorOfDate.factory))))
                obj = {};
                obj.date = getDateFormatted(new Date(loopDate.getTime()));
                obj.installmentFactor = installmentFactor;
                installmentFactors.push(obj);
            }


        }

        return {
            "installmentFactors": installmentFactors
        };




    }


    function getInstallmentAmount(loanFactor, InstallmentFactor, stepRate, stepFactor, installmentStartDate, end_date, period, installment_payment_interval, interval) {
        // let end_date_formatted = end_date;
        installmentsAmounts = [];
		
        loanFactoryAtEndDate = loanFactor['loanFactories'].find((item) => {
            return item.date == getDateFormatted(end_date)
        });
	

        installmentFactorAtEndDate = InstallmentFactor['installmentFactors'].find((item) => {
            return item.date == getDateFormatted(end_date)
        });

        installmentAmount = loanFactoryAtEndDate.loanFactor / (installmentFactorAtEndDate.installmentFactor * -1);
        obj = {};
        obj.date = getDateFormatted(installmentStartDate);
        obj.amount = installmentAmount;
        installmentsAmounts.push(obj);

        for (let i = 1; i <= (period / installment_payment_interval); i++) {

            loopDate = addMonths(installmentStartDate, installment_payment_interval);

            stepFactorOfDate = stepFactor['stepFactors'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

            if (!stepFactorOfDate) {
                break
            } else {
                if ((i % (interval / installment_payment_interval)) == 0 && i != 0) {
                    installmentAmount = installmentAmount * (parseFloat(Math.pow((1 + parseFloat(stepRate)), 1)))
                } else {
                    installmentAmount = installmentAmount
                }

                obj = {};
                obj.date = getDateFormatted(new Date(loopDate.getTime()));
                obj.amount = installmentAmount;
                installmentsAmounts.push(obj);

            }
        }
        return {
            "InstallmentAmountArr": installmentsAmounts
        };





    }

    function formatTable(data, loanAmount, loanType) {
        table = `<table class='table table-striped table-bordered table-hover table-checkable' id="dynamic-datatable">
        <thead>
             <tr>
                            <th class="text-center">{{__("Payment No.")}}</th>
                            <th class="text-center">{{__("Date")}}</th>
                            <th class="text-center">{{__("Days Count")}}</th>
                            <th class="text-center">{{__("Begining Balance")}}</th>
                            <th class="text-center">{{__("Schedule Payment")}}</th>
                            <th class="text-center">{{__("Interest Amount")}}</th>
                            <th class="text-center">{{__("Principle Amount")}}</th>
                            <th class="text-center">{{__("End Balance")}}</th>
                          
                        </tr>
        </thead>    

        <tbody> `;

        // return 
        let order = 0;
        totalPrincpleAmount = 0;
        totalSchedulePayment = 0;
        totalInterestAmount = 0;
        let dataToAjax = [];
        for (let i = 0; i < data.length; i++) {
            table += `<tr>
            <td class="text-center"> 
                ${ order++ }
            </td>
            <td class="text-center">
            ${formatDate(new Date(data[i].date))}
            </td>            

            <td class="text-center"> 
                ${data[i].val.daysCount}
            </td>    
			
			
			`
            i == 0 ? (Begining = loanAmount) : Begining = endBalance;

            intresetAmount = Begining * data[i].val.interestFactor;
            totalInterestAmount += intresetAmount
             
            table += `
            
            <td class="text-center"> 
            
            
            ` ;
            table +=`
               ${numberFormat(Begining)}
            </td>
            <td class="text-center">`
            let withoutCapitalization = loanType.split('_').includes('without') && loanType.split('_').includes('capitalization')

            schedulePayment = (withoutCapitalization) && data[i].val.InstallmentAmount == 0 ? intresetAmount : data[i].val.InstallmentAmount;
            totalSchedulePayment = totalSchedulePayment + schedulePayment
            table +=

                `
                ${ number_format(schedulePayment,2)}
            </td>
           `;


            table +=
                `
            <td class="text-center">
            
            ${number_format(intresetAmount,2)}
            </td>
            <td class="text-center"> `;
            principleAmout = parseFloat(schedulePayment) - intresetAmount;
		
            // principleAmout = data[i].val.InstallmentAmount - intresetAmount ;
            totalPrincpleAmount = totalPrincpleAmount + principleAmout;
            table +=
                `
                ${(number_format(principleAmout,2)) }
            </td>

            <td class="text-center">`;
            endBalance = Begining + intresetAmount - schedulePayment;
            dataToAjax.push({
                'date':formatDate(new Date(data[i].date)) , 
                'beginningBalance':Begining ,
                'principle':principleAmout,
                'endBalance':endBalance,
                'new_interest_amount':intresetAmount,
                'financial_id':$('input[name="financial_id"]').val()
            });

            table += ` 
            ${ numberFormat(endBalance) == '-0' ? 0 : numberFormat(endBalance) } 
            </td>
            </tr>`
        }
        window['dataToAjax'] = dataToAjax ;

        table += `
        <tr class="custom-color-for-last-tr">
        <th class="text-center">
        
        {{ __('Total') }}
        </th>
       

        <th class="text-center">
        -
        </th>
 <th>
        -
        </th >

        <th class="text-center">
        -
        </th>


                <th class="text-center">
        
        ${number_format(totalSchedulePayment,2)}
        
        </th>
        <th class="text-center">
        ${number_format(totalInterestAmount,2)}
        </th>

        <th class="text-center">
        ${number_format(totalPrincpleAmount,2)}
        </th>

        <th class="text-center">

        -
        
        </th>


        </tr> `

        table +=

            `</tbody>
        </table>
        
        
        `;

       

        $('#append-table-id').empty().append(table);

        $('#dynamic-datatable').DataTable({
            paginate: false
            , searching: false
            , ordering: false
            , fixedHeader: {
                header: true
                , footer: false
                , headerOffset: 78
            }
            , dom: 'Bfrtip'
            , buttons: ['copy', 'csv', {
                "extend": "excel"
                , title: ''
                , filename: 'Fixed Payments At The End'
                , customize: function(xlsx) {

                    exportToExcel(xlsx)

                }
            }, 'pdf', 'print']
        });

        
         

    }

    function formatDate(d) {
        return ("0" + d.getDate()).slice(-2) + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" +
            d.getFullYear()
    }

    function numberFormat(number) {
        // num = number.toFixed();
        return number.toLocaleString('en-US').split('.')[0]
    }

    $(document).on('keyup', '.negative-numbers', function(e) {
        let val = $(this).val();

        var re = /^-?\d*\.?\d{0,6}$/;
        var text = $(this).val();

        var isValid = (text.match(re) !== null);

        if (!isValid || val > 0) {
            $(this).val(0);
        }
    })

</script>

<script>
    $('.max-tenor-limit').on('keyup', function() {
        let val = parseFloat($(this).val());
        if (val > 420) {
            $(this).val(420).trigger('change')
        }
    })
    // $('#gracePeriodId')

</script>




<script>
    $('#installment_interval').on('change', function() {
        let selectedOption = $(this).find('option:selected').data('order');
        $('#step_up_interval').find('option').each(function(index, opt) {
            if ($(opt).data('order') < selectedOption) {
                $(opt).prop('disabled', true);
                $(opt).css('display', 'none');
            } else {
                $(opt).prop('disabled', false);
                $(opt).css('display', 'initial');

            }
        });



        $('#step_down_interval').find('option').each(function(index, opt) {
            if ($(opt).data('order') < selectedOption) {
                $(opt).prop('disabled', true);
                $(opt).css('display', 'none');
            } else {
                $(opt).prop('disabled', false);
                $(opt).css('display', 'initial');

            }
        });
        if (!$('#step_up_interval').find('option:nth-child(4):selected').length) {
            if(! $('#page-is-loading').length)
            {
                $('#step_up_interval').find('option:nth-child(1)').prop('selected', 1);

            }
        }

        if (!$('#step_down_interval').find('option:nth-child(4):selected').length) {
            if(! $('#page-is-loading').length)
            {
                $('#step_down_interval').find('option:nth-child(1)').prop('selected', 1);
            }
        }

    $('#page-is-loading').remove();
    }).trigger('change');



</script>
<script>

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment.min.js" type="text/javascript">

</script>
<script src="/custom/js/loan.js"></script>
@if(isset($triggerClick))
<script>
    $(function(){
        $('.submit').trigger('click');
        $('#loan-table-a').trigger('click');
    });
</script>
@endif 
@endif
@endsection
