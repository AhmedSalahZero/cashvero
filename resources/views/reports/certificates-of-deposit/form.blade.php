@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }

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
{{ __('Certificates Of Deposit Form') }}
[{{ $financialInstitution->getName() }} ]
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('update.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'certificatesOfDeposit'=>$model->id]) :route('store.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
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
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Certificates Of Deposit')"></x-sectionTitle>
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
                                        {{__('Main Information')}}
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
                                    <div class="col-md-2 ">
                                        <x-form.input :model="$model??null" :label="__('Account Number')" :type="'text'" :placeholder="__('Account Number')" :name="'account_number'" :required="true"></x-form.input>
                                    </div>
                                    <div @if($company->hasOdooIntegrationCredentials())
                                        class="col-md-1"
                                        @else
                                        class="col-md-2"
                                        @endif

                                        >
                                        <label>{{__('Currency')}} </label>
                                        <div class="input-group">
                                            <select name="currency" class="form-control repeater-select js-update-current-accounts">
                                                @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    @if($company->hasOdooIntegrationCredentials())
                                    <div class="col-1	">
                                        <label class="form-label font-weight-bold ">{{ __('Odoo Code') }}

                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input placeholder="{{ __('Odoo Code') }}" type="text" class="form-control  exclude-text" name="odoo_code" value="{{ isset($model) ? $model->getOdooCode() : old('odoo_code') }}">
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <div class="col-md-4">
                                        <label>{{__('Deducted From Account #')}}
                                            @include('star')
                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select data-current-selected="{{ isset($model) ? $model->getDeductedFromAccountId(): 0 }}" name="deducted_from_account_id" class="form-control js-append-current-accounts-with-select js-account-id-2">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>




                                    <div class="col-md-2">
                                        <x-form.date :id="'start-date-id'" :classes="'recalculate-interest-amount'" :label="__('Start Date')" :required="true" :model="$model??null" :name="'start_date'" :placeholder="__('Select Start Date')"></x-form.date>
                                    </div>
                                    <div class="col-md-2">
                                        <x-form.date :id="'end-date-id'" :classes="'recalculate-interest-amount'" :label="__('End Date')" :required="true" :model="$model??null" :name="'end_date'" :placeholder="__('Select End Date')"></x-form.date>
                                    </div>

                                    <div class="col-md-2 ">
                                        <x-form.input :id="'amount-id'" :model="$model??null" :label="__('Amount')" :type="'text'" :placeholder="__('Amount')" :name="'amount'" :class="'only-greater-than-or-equal-zero-allowed recalculate-interest-amount'" :required="true"></x-form.input>
                                    </div>

                                    <div class="col-md-2 ">
                                        <x-form.input :id="'interest-rate-id'" :model="$model??null" :class="'only-percentage-allowed recalculate-interest-amount'" :label="__('Interest Rate (%)')" :type="'text'" :placeholder="__('Borrowing Rate (%)')" :name="'interest_rate'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-2 ">
                                        <x-form.input :readonly="true" :id="'interest-amount-id'" :model="$model??null" :label="__('Interest Amount [At Maturity]')" :type="'text'" :placeholder="__('Interest Amount')" :name="'interest_amount'" :class="'only-greater-than-or-equal-zero-allowed'" :required="true"></x-form.input>
                                    </div>


                                    <div class="col-md-2">
                                        <label>{{__('Add Maturity Amount To Account')}} @include('star')</label>
                                        <div class="input-group">
                                            <select required name="maturity_amount_added_to_account_id" class="form-control repeater-select js-append-current-accounts">
                                                {{-- <option selected>{{__('Select')}}</option> --}}
                                                @foreach($accounts as $account)
                                                <option value="{{ $account->id  }}" @if(isset($model) && ($account->id == $model->getMaturityAmountAddedToAccountId())) selected @endif >{{$account->getAccountNumber()}} </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6 ">
                                        <div class="row">
                                            <div class="col-md-12 mb-0 mt-4 text-left">
                                                <div class="form-group d-inline-block">
                                                    <div class="kt-radio-inline">
                                                        <label class="mr-3">
                                                            {{ __('Interest Amount Interval') }}

                                                        </label>
                                                        @php
                                                        $isPeriodic = isset($model) && $model->isPeriodically() ;
                                                        @endphp
                                                        <label class="kt-radio kt-radio--success text-black font-size-18px font-weight-bold">
                                                            <input type="radio" value="1" name="is_at_maturity" @if(!$isPeriodic) checked @endisset> {{ __('At Maturity') }}
                                                            <span></span>
                                                        </label>

                                                        <label class="kt-radio kt-radio--danger text-black font-size-18px font-weight-bold">
                                                            <input type="radio" value="0" name="is_at_maturity" @if($isPeriodic) checked @endisset> {{ __('Periodically [e.g: biweekly / monthly / quarterly / etc...]') }}
                                                            <span></span>
                                                        </label>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>




                        <x-submitting :backTo="route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])" />
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

            <script>
                $(document).on('change', '.recalculate-interest-amount', function(e) {
                    e.preventDefault()

                    const startDate = $('#start-date-id').val();
                    const endDate = $('#end-date-id').val();
                    const amount = number_unformat($('#amount-id').val());
                    const intrestRate = number_unformat($('#interest-rate-id').val())
                    const diffBetweenEndDateAndStartDate = getDiffBetweenTwoDateInDays(startDate, endDate)
                    if (diffBetweenEndDateAndStartDate && amount && intrestRate) {
                        const interestAmount = intrestRate / 100 / 365 * diffBetweenEndDateAndStartDate * amount
                        $('#interest-amount-id').val(number_format(interestAmount, 2))
                    }
                })

                $(function() {
                    $('#start-date-id').trigger('change')
                })

            </script>
            <script>
                $(document).on('change', 'select.js-update-current-accounts', function(e) {
                    const currency = $(this).val();
                    if (currency) {
                        $.ajax({
                            url: "{{ route('update.current.account.based.on.currency',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ]) }}"
                            , data: {
                                "currency": currency
                            }
                            , success: function(res) {
                                let options = '';
                                let optionsWithSelect = '<option selected value="0" >Opening Balance</option>';
                                var selected = "";
                                let currentSelected = $('select[name="deducted_from_account_id"]').attr('data-current-selected');

                                for (var i = 0; i < res.data.length; i++) {
                                    id = Object.keys(res.data[i])[0];
									selected = '';
                                    if (id == currentSelected) {
                                        selected = "selected";
                                    }

                                    accountNumber = res.data[i][id]
                                    options += ' <option value="' + id + '">' + accountNumber + '</option> '
                                    optionsWithSelect += ' <option ' + selected + ' value="' + id + '">' + accountNumber + '</option> '
                                }
                                $('select.js-append-current-accounts').empty().append(options)
                                $('select.js-append-current-accounts-with-select').empty().append(optionsWithSelect)
                            }
                        })

                    }
                })
                $('select.js-update-current-accounts').trigger('change');

            </script>
            @endsection
