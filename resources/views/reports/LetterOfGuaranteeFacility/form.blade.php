@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark label {
        white-space: nowrap !important
    }

    .money-flow-dark [class*="col"] {
        margin-bottom: 1.5rem !important;
    }

    .money-flow-dark label {
        text-align: left !important;
    }

    .money-flow-dark .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Letter Of Guarantee Facility Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->
        {{-- <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__('Money Received')}}
        </h3>
    </div>
</div>
</div> --}}
<form method="post" action="{{ isset($model) ?  route('update.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'letterOfGuaranteeFacility'=>$model->id]) :route('store.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id]) }}" class="kt-form kt-form--label-right">
    <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
    <input id="js-money-received-id" type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
    {{-- <input type="hidden" name="financial_institutions_id" value="{{ $financialInstitution->id }}"> --}}
    @csrf
    @if(isset($model))
    @method('put')
    @endif

    <div class="row">
        <div class="col-md-12">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __((isset($model) ? 'Edit' : 'Add') . ' Letter Of Guarantee')}}
                        </h3>
                    </div>
                </div>
            </div>
            <!--begin::Form-->
            <form class="kt-form kt-form--label-right">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Contract Main Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">


                        <div class="form-group row">
                            <div class="col-md-4 ">
                                <label>{{__('Financial Institution Name')}} </label>
                                <div class="kt-input-icon">
                                    <input disabled value="{{ $financialInstitution->getName()  }}" type="text" class="form-control" placeholder="{{__('Financial Institution Name')}}">
                                </div>
                            </div>
							
							   <div class="col-md-4 ">
                                <x-form.input :model="$model??null" :label="__('Name')" :type="'text'" :placeholder="__('Name')" :name="'name'" :class="''" :required="true"></x-form.input>
                            </div>
							
                            <div class="col-md-2">

                                <x-form.date :label="__('Contract Start Date')" :required="true" :model="$model??null" :name="'contract_start_date'" :placeholder="__('Select Contract Start Date')"></x-form.date>
                            </div>
                            <div class="col-md-2">
                                <x-form.date :label="__('Contract End Date')" :required="true" :model="$model??null" :name="'contract_end_date'" :placeholder="__('Select Contract End Date')"></x-form.date>
                            </div>


                            <div class="col-md-2 ">
                                <x-form.input :model="$model??null" :label="__('Limit')" :type="'text'" :placeholder="__('Limit')" :name="'limit'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                            </div>

                            <div class="col-md-2">
                                <label>{{__('Select Currency')}} @include('star')</label>
                                <div class="input-group">
                                    <select name="currency" class="form-control repeater-select">
                                        {{-- <option selected>{{__('Select')}}</option> --}}
                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2 hidden">
                                <x-form.date :label="__('Outstanding Date')" :required="true" :model="$model??null" :name="'outstanding_date'" :placeholder="__('Select Outstanding Date')"></x-form.date>
                            </div>


                            <div class="col-md-2 hidden">
                                <x-form.input :model="$model??null" :label="__('Outstanding Amount')" :type="'text'" :placeholder="__('Outstanding Amount')" :name="'outstanding_amount'" :class="'only-greater-than-or-equal-zero-allowed'" 
								{{-- :required="true" --}}
								></x-form.input>
                            </div>




                        </div>
                    </div>
                </div>

                <div class="kt-portlet ">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Terms & Conditions')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">

                        @php
                        $index = 0 ;
                        @endphp

                        @foreach(getLgTypes() as $name => $nameFormatted )
                        @php
                        $termAndCondition = isset($model) && isset($model->termAndConditions[$index]) ? $model->termAndConditions[$index] : null;
                        @endphp
                        <div class="form-group row" style="flex:1;">

                            <div class="col-md-4">
                                <label class="label">{!! __('LG  <br> Type') !!}</label>
                                <input class="form-control" type="hidden" readonly value="{{ $name }}" name="termAndConditions[{{ $index }}][lg_type]">
                                <input class="form-control" type="text" readonly value="{{ $nameFormatted }}">
                            </div>



                            <div class="col-2 hidden">
                                <label class="form-label font-weight-bold ">
								{!! __('Outstanding  <br> Balance') !!}
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input placeholder="{{ __('Outstanding Balance') }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed" name="termAndConditions[{{ $index }}][outstanding_balance]" value="{{ isset($termAndCondition) ? $termAndCondition->getOutstandingBalance() : old('outstanding_balance',0) }}">
                                    </div>
                                </div>
                            </div>






                            <div class="col-1">
                                <label class="form-label font-weight-bold text-center">
								{!! __('Cash <br> Cover (%)') !!}
                                    @include('star')
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="termAndConditions[{{ $index }}][cash_cover_rate]" type="text" class="form-control only-percentage-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->cash_cover_rate : old('cash_cover_rate',0)) }}">
                                    </div>
                                </div>
                            </div>

                            <div class="col-1">
                                <label class="form-label font-weight-bold text-center "> 
								{!! __('Commission <br> Rate (%)') !!}
                                    @include('star')
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="termAndConditions[{{ $index }}][commission_rate]" type="text" class="form-control only-percentage-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->commission_rate : old('commission_rate',0)) }}">
                                    </div>
                                </div>
                            </div>



                            <div class="col-lg-2">
                                <label >
								{!! __('Commission <br> Interval') !!}
                                    @include('star')
                                </label>
                                <div class="input-group">
                                    <select name="termAndConditions[{{ $index }}][commission_interval]" class="form-control repeater-select">
                                        {{-- <option selected>{{__('Select')}}</option> --}}
                                        @foreach(getCommissionInterval() as $name => $nameFormatted )
                                        <option value="{{ $name  }}" @if(isset($termAndCondition) && ($termAndCondition->getCommissionInterval() == $name ) ) selected @elseif(!isset($termAndCondition) && $name == 'monthly') selected @endif > {{ $nameFormatted }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
							
							
							   <div class="col-2">
                                <label class="form-label font-weight-bold">	{!! __('Min Commissions <br> Fees Amount') !!}
                                 
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="termAndConditions[{{ $index }}][min_commission_fees]" type="text" class="form-control only-greater-than-or-equal-zero-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->min_commission_fees : old('min_commission_fees',0)) }}">
                                    </div>
                                </div>
                            </div>
							
							
								   <div class="col-2">
                                <label class="form-label font-weight-bold">{!! __('Issuance <br> Fees Amount') !!}
                                 
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="termAndConditions[{{ $index }}][issuance_fees]" type="text" class="form-control only-greater-than-or-equal-zero-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->issuance_fees : old('issuance_fees',0)) }}">
                                    </div>
                                </div>
                            </div>
							
							



                        </div>
                        @php
                        $index = $index + 1 ;
                        @endphp

                        @endforeach





                    </div>
                </div>


                <x-submitting :backTo="route('view.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])" />
            </form>

            <!--end::Form-->

            <!--end::Portlet-->
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
        $(document).find('.datepicker-input').datepicker({
            dateFormat: 'mm-dd-yy'
            , autoclose: true
        })
        $('#m_repeater_0').repeater({
            initEmpty: false
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

    <script>
        let oldValForInputNumber = 0;
        $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
            oldValForInputNumber = $(this).val();
            $(this).val('')
        })
        $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

            if ($(this).val() == '') {
                $(this).val(oldValForInputNumber)
            }
        })

        $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
            if (!$(this).hasClass('exclude-text')) {
                let val = $(this).val()
                val = number_unformat(val)
                $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

            }
        })

    </script>
    @endsection
