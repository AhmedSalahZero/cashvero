@extends('layouts.dashboard')
@section('Title')
<style>
    tbody td {
        font-weight: bold;
        color: black !important;
    }

</style>
<span class="kt-portlet__head-icon">
    <i class="kt-font-brand flaticon2-line-chart fa-fw flaticon-house-sketch pull-{{__('left')}}"></i>
    {{ __('Loan Calculator') . ' ( ' .str_to_upper(Request()->segments()[count(Request()->segments())-1]) . ' )'}}
</span>
@endsection

@section('content')

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
                                <option value="{{ $fixedType }}" {{ @old('fixed_loan_type') == $fixedType ? 'selected' : '' }}>{{str_to_upper($fixedType)}}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('installment_interval'))
                            <div class="invalid-feedback">{{ $errors->first('fixed_loan_type') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- </div> --}}

                @endif


                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Loan Start Date')}}
                            <span class="astric">*</span>

                        </label>
                        <div class="form-group-sub">
                            <input required type="date" id="start-date" name="start_date" class="form-control number interval-calcs" placeholder="{{__('Loan Start Date')}} {{__('Autoload')}}  .." />
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Installment Amount')}}
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input type="number" step="any" id="loan_amount" name="loan_amount" value="{{ @old('loan_amount') }}" class="form-control number" placeholder="{{__('Installment Amount')}} .." required />
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
                            <input type="number" step="any" id="base_rate" name="base_rate" value="{{ @old('base_rate') }}" class="form-control number pricing-calc-item" placeholder="{{__('Base Rate')}} .." required />
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
                            <input type="number" step="any" id="margin_rate" name="margin_rate" value="{{ @old('margin_rate') }}" class="form-control number pricing-calc-item" placeholder="{{__('Margin Rate')}} .." required />
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
                            <input disabled type="number" step="any" min="0" id="pricing" name="pricing" value="{{ @old('pricing') }}" class="form-control number pricing-calc-item" placeholder="{{__('Pricing')}} .." required />
                            @if ($errors->has('pricing'))
                            <div class="invalid-feedback">{{ $errors->first('pricing') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($type =='fixed')
                <div class="col-md-4  item-main-parent">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Tenor (Duration In Months) ')}}
                        </label><span class="astric">*</span>
                        <div class="form-group-sub">
                            <input type="number" step="1" min="1" id="duration" name="duration" value="{{ @old('duration') }}" class="form-control number grace_period_calc max-tenor-limit installment_condition" placeholder="{{__('Duration In Months')}} .." required />
                            @if ($errors->has('duration'))
                            <div class="invalid-feedback">{{ $errors->first('duration') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 item-main-parent item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Grace Period')}} ( {{__('Months')}} )
                            <span class="astric">*</span>
                        </label>
                        <div class="form-group-sub">
                            <input type="text" step="any" id="grace_periodid" name="grace_period" value="{{ @old('grace_period') }}" class="form-control number grace_period_calc" placeholder="{{__('Grace Period')}} .." />
                            @if ($errors->has('grace_period'))
                            <div class="invalid-feedback">{{ $errors->first('grace_period') }}</div>
                            @endif
                        </div>
                    </div>
                </div>


                <div class="col-md-4 item-main-parent item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">{{__('Capitalization Type')}}</label>
                        <div class="form-group-sub">
                            <select disabled name="capitalization_type" id="capitalization_type" class="form-control">
                                <option value="with_capitalization" {{ @old('capitalization_type') == 'with_capitalization' ? 'selected' : '' }}>{{__('With Capitalization')}}</option>
                                <option value="without_capitalization" {{ @old('capitalization_type') == 'without_capitalization' ? 'selected' : '' }}>{{__('Without Capitalization')}}</option>
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






                <div class="col-md-4 item-main-parent" style="display: none">
                    <div class="form-group validated">
                        <label class="col-form-label take">
                            {{__('Step-up Rate ( % ) ')}}
                            {{-- <span class="astric">*</span> --}}
                        </label><span class="astric">*</span>
                        <div class="" id="step-up-id">
                            <div class="form-group-sub">
                                <input type="number" step="any" min="0" max="100" id="step_up_rate" name="step_up_rate" value="{{ @old('step_up_rate') }}" class="form-control number" placeholder="{{__('Step-up Rate')}} .." required />
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
                                <option value="quarterly" {{ @old('step_up_interval') == 'quarterly' ? 'selected' : '' }} data-order="1">{{__('Quarterly')}}</option>
                                <option value="semi annually" {{ @old('step_up_interval') == 'semi annually' ? 'selected' : '' }} data-order="2">{{__('Semi-annually')}}</option>
                                <option value="annually" {{ @old('step_up_interval') == 'annually' ? 'selected' : '' }} data-order="3">{{__('Annually')}}</option>
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
                            <input type="text" step="any" {{-- min="-100" --}} {{-- max="0" --}} id="step_down_rate" name="step_down_rate" value="{{ @old('step_down_rate') }}" class="form-control negative-numbers" placeholder="{{__('Step-down Rate')}} .." required />
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
                                <option value="quarterly" {{ @old('step_down_interval') == 'quarterly' ? 'selected' : '' }} data-order="1">{{__('Quarterly')}}</option>
                                <option value="semi annually" {{ @old('step_down_interval') == 'semi annually' ? 'selected' : '' }} data-order="2">{{__('Semi-annually')}}</option>
                                <option value="annually" {{ @old('step_down_interval') == 'annually' ? 'selected' : '' }} data-order="3">{{__('Annually')}}</option>
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
                            <input type="number" step="any" id="borrowing_rate" name="borrowing_rate" value="{{ @old('borrowing_rate') }}" class="form-control number" placeholder="{{__('Borrowing Rate')}} .." />
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
                            <input type="number" step="any" id="margin_interest" name="margin_interest" value="{{ @old('margin_interest') }}" class="form-control number" placeholder="{{__('Interest Margin')}} .." />
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
                            <input type="text" step="any" id="loan_interest" name="loan_interest" value="{{ @old('loan_interest') }}" class="form-control number" placeholder="{{__('Loan Interest')}}.." disabled />
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
                            <input type="number" step="any" id="min_interest" name="min_interest" value="{{ @old('min_interest') }}" class="form-control number" placeholder="{{__('Min Interest')}} .." />
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
                            <input type="number" step="any" id="repayment_duration" name="repayment_duration" value="{{ @old('repayment_duration') }}" class="form-control number max-tenor-limit" placeholder="{{__('Duration')}} .." />
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
                            <select name="installment_interval" id="installment_interval" class="form-control installment_condition">
                                <option value="" selected disabled>{{__('Select')}} ..</option>
                                <option value="monthly" {{ @old('installment_interval') == 'monthly' ? 'selected' : '' }}>{{__('Monthly')}}</option>
                                <option value="quarterly" {{ @old('installment_interval') == 'quarterly' ? 'selected' : '' }}>{{__('Quarterly')}}</option>
                                <option value="semi annually" {{ @old('installment_interval') == 'semi annually' ? 'selected' : '' }}>{{__('Semi-annually')}}</option>
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


    <div class="kt-portlet">
        <div class="kt-portlet__foot">
            <div class="kt-form__actions">
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex gap-x-px">
                            <h2 class="d-inline-block" style="margin-right:4px;">{{ __('Loan Amount = ') }}</h2>
                            <h2 id="calc-loan-amount-val">--</h2>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="{{__('right')}} text-right">
                            <input id="submit---id" type="submit" onclick="return false;" name="submit" value="{{__('Calculate')}}" class="btn active-style submit">
                        </div>
                    </div>

            </div>
        </div>
    </div>
    </div>





</form>


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
        $('#interest_label').html('{{__('Interest Margin ')}}');
        $('#view_min_interest').css('display', 'block');
        $('#view_borrowing_rate').css('display', 'block');
        $('#view_interest').css('display', 'block');
        $('#view_loan_interest').css('display', 'block');
        $('#loan_choosen_type').html('{{__('Variable Installment Loan ')}}');
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

            // $('#interest_interval').append(select);
            // $('#installment_amount').val(installment_amount.toFixed(2));
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
            $('#grace_periodid').closest('.item-main-parent').fadeIn(300);
            //$('#grace_periodid').val(0).closest('.item-main-parent').fadeIn(300);
            $('#capitalization_type').val(0).closest('.item-main-parent').fadeIn(300);
            if (loanType == 'grace_step-up_with_capitalization' || loanType == 'grace_period_with_capitalization' ||
                loanType == 'grace_step-down_with_capitalization'

            ) {
                $('#capitalization_type').find('option:nth-child(1)').prop('selected', true);

            } else {
                $('#capitalization_type').find('option:nth-child(2)').prop('selected', true);
            }
        } else {
            $('#grace_periodid').closest('.item-main-parent').fadeOut(300);
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
        let interval = 0;
        let installment_interval = $('#installment_interval').val();
        let loanAmount = parseFloat($('#loan_amount').val())
        let gracePeriod = parseFloat($('#grace_periodid').val()) ? parseFloat($('#grace_periodid').val()) : 0;


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

        // if(applied_step){
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
            , period, installment_payment_interval, interval, loanAmount

        );

        let getLoanVal = calcLoan(loanFactories, installmentFactories, stepRate, stepFactor, new Date(installmentStartDate.getTime()), addMonths(new Date(start_date_formatted.getTime()), (period ? period : 0))
            , period, installment_payment_interval, interval, loanAmount
        );

        //  ;
        //  let allData = Array.concat(daysCount , intersetFactor,installmentAmountArr);
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


            // return ;
            obj.val = {
                "daysCount": searchedDaysCount.daysDiff
                , "InstallmentAmount": searchedInstallmentAmount ? searchedInstallmentAmount.amount : 0
                , "interestFactor": searchedIntersetFactor ? searchedIntersetFactor.interestFactor : 0
            };

            newDat.push(obj);
        }
        formatTable(newDat, loanAmount, fixedType, installmentAmountArr, getLoanVal);
        // }
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
            return new Date(newDate.setDate(currentDate));
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
        //    date.setMonth(date.getMonth() + months) ;
        //    return date ;


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

        for (let i = 0; i < period; i++) {
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
            var loanFactor = 1 * (1 + searchedInterestFactor.interestFactor);

        } else {
            loanFactorStartDate = addMonths(new Date(start_date.getTime()), installment_payment_interval);
            var searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loanFactorStartDate)
            });
            var loanFactor = 1 * (1 + searchedInterestFactor.interestFactor);
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



        for (let i = 0; i <= interval / installment_payment_interval; i++) {
            // firstMonth = new Date(loanFactorStartDate.getTime()) ;

            loopDate = addMonths(loanFactorStartDate, installment_payment_interval);
            // console.log (searchedInterestFactor);
            searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

      



            loanFactor = loanFactor + (loanFactor * searchedInterestFactor.interestFactor)

            // loanFactoriesArr.push(loopDate + " " + loanFactor);


            obj = {};
            obj.date = getDateFormatted(new Date(loopDate.getTime()));
            obj.loanFactor = loanFactor;
            loanFactoriesArr.push(obj);

            if (end_date_end.getTime() == loopDate.getTime()) {
                break;
            }

            // loanFactorStartDate = addMonths(loopDate , installment_payment_interval)

            // start_date = new Date(secondMonth.getTime());
        }
        return {
            "loanFactories": loanFactoriesArr
        };

    }

    function getInstallmentStartDate(loanStartDate, gracePeriod, installment_payment_interval) {
        // 01-01-2022               06            03
        // 01-10-2022


        let installmentDate = addMonths(loanStartDate, gracePeriod + installment_payment_interval);
        return installmentDate;
    }

    function getDateFormatted(yourDate) {
        const offset = yourDate.getTimezoneOffset()
        yourDate = new Date(yourDate.getTime() - (offset * 60 * 1000))
        return yourDate.toISOString().split('T')[0]
    }

    function calcInstallmentFactor(installmentStartDate, intersetFactor, stepRate, stepFactor, interval, installment_payment_interval) {

        let firstInstallmentStartDate = installmentStartDate;
        installmentFactors = [];
        installmentFactor = -1;
        for (let i = 1; i <= interval / installment_payment_interval; i++) {
            loopDate = addMonths(installmentStartDate, installment_payment_interval);
            searchedInterestFactor = intersetFactor['interestFactor'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

            stepFactorOfDate = stepFactor['stepFactors'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });
            if (!searchedInterestFactor) {
                break
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

    function calcLoan(loanFactor, InstallmentFactor, stepRate, stepFactor, installmentStartDate, end_date, period, installment_payment_interval, interval, loanAmount) {
        installmentsAmounts = [];
        loanFactoryAtEndDate = loanFactor['loanFactories'].find((item) => {
            return item.date == getDateFormatted(end_date)
        });

        installmentFactorAtEndDate = InstallmentFactor['installmentFactors'].find((item) => {
            return item.date == getDateFormatted(end_date)
        });

        return loanAmount * (installmentFactorAtEndDate.installmentFactor * -1) / loanFactoryAtEndDate.loanFactor;
    }

    function getInstallmentAmount(loanFactor, InstallmentFactor, stepRate, stepFactor, installmentStartDate, end_date, period, installment_payment_interval, interval, loanAmount) {
        // let end_date_formatted = end_date;
        installmentsAmounts = [];


        loanAmount = parseFloat($('#loan_amount').val());

        loopDate = addMonths(installmentStartDate, 0);

        // loanAmount = 
        for (let i = 0; i <= period / installment_payment_interval; i++) {
            if (i != 0) {
                loopDate = addMonths(installmentStartDate, installment_payment_interval);
            }
            stepFactorOfDate = stepFactor['stepFactors'].find((item) => {
                return item.date == getDateFormatted(loopDate)
            });

            if (!stepFactorOfDate) {
                break
            } else {

                if ((i % (interval / installment_payment_interval)) == 0 && i != 0) {
                    loanAmount = loanAmount * (parseFloat(Math.pow((1 + parseFloat(stepRate)), 1)))
                } else {
                    loanAmount = loanAmount
                }

                obj = {};
                obj.date = getDateFormatted(new Date(loopDate.getTime()));
                obj.amount = loanAmount;
                installmentsAmounts.push(obj);

            }
        }
        return {
            "InstallmentAmountArr": installmentsAmounts
        };





    }

    function formatTable(data, loanAmount, loanType, installmentAmount, LoanVal) {

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
            </td>`
            i == 0 ? (Begining = LoanVal) : Begining = endBalance;
            $('#calc-loan-amount').fadeIn(300);
            $('#calc-loan-amount-val').html(numberFormat(LoanVal))
            intresetAmount = Begining * data[i].val.interestFactor;
            totalInterestAmount += intresetAmount

            table += `
            <td class="text-center">
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
                ${ (number_format(principleAmout,2)) }
            </td>

            <td class="text-center">`;
            endBalance = Begining + intresetAmount - schedulePayment;

            table += ` 
            ${ numberFormat(endBalance) == '-0' ? 0 : numberFormat(endBalance) } 
            </td>
            </tr>`
        }

        table += `
        <tr class="custom-color-for-last-tr">
        <th>
        
        {{ __('Total') }}
        </th>
        <th class="text-center">
        -
        </th>

        <th class="text-center">
        -
        </th>

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
                    , filename: 'Calculate Loan Amount'
                    , customize: function(xlsx) {

                        exportToExcel(xlsx)

                    }
                }
                , 'pdf', 'print'
            ]
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
        if (val > 270) {
            $(this).val(270).trigger('change')
        }
    })
    $('#gracePeriodId')

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
            $('#step_up_interval').find('option:nth-child(1)').prop('selected', 1);
        }

        if (!$('#step_down_interval').find('option:nth-child(4):selected').length) {
            $('#step_down_interval').find('option:nth-child(1)').prop('selected', 1);
        }


    }).trigger('change');

</script>



@endif
<script src="/custom/js/loan.js"></script>

@endsection
