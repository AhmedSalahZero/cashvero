@extends('layouts.dashboard')
@section('css')
@php
use App\Models\BuyOrSellCurrency ;
$bankToBankConst = BuyOrSellCurrency::BANK_TO_BANK;
$bankToSafeConst = BuyOrSellCurrency::BANK_TO_SAFE;
$safeToBankConst = BuyOrSellCurrency::SAFE_TO_BANK;
$safeToSafeConst = BuyOrSellCurrency::SAFE_TO_SAFE;

@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="{{ asset('assets/jquery-calculator/jquery.calculator.css') }}">

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
{{-- @section('sub-header')
{{ __('Internal Money Transfer Form') }}
@endsection --}}
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('buy-or-sell-currencies.update',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) :route('buy-or-sell-currencies.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input id="model-id" type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            <input  type="hidden" name="modelId" value="{{ isset($model) ? $model->id : 0 }}">
            <input  type="hidden" name="modelType" value="BuyOrSellCurrency">
            <input type="hidden" name="company_id" value="{{ $company->id }}">

            @if(isset($model))
            <input type="hidden" name="updated_by" value="{{ auth()->user()->id }}">
            @else
            <input type="hidden" name="created_by" value="{{ auth()->user()->id }}">

            @endif
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
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' '  . __('Sell Or Buy Currencies') )"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->
                    <form class="kt-form kt-form--label-right">
                        <div class="kt-portlet">


                            <div class="kt-portlet ">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label flex-1">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                            {{-- {{__('Sell Or Buy Currencies Information')}} --}}
                                        </h3>

                                        <div data-type="{{ $bankToBankConst.','.$bankToSafeConst }}" class="show-only-if flex-1 d-flex justify-content-end pt-3">
                                            <div class="col-md-3 mb-3">
                                                <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                                <div class="kt-input-icon">
                                                    <input value="0" type="text" disabled class="form-control balance-js" placeholder="{{__('Account Balance')}}">
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label>{{__('Net Balance')}} <span class="net-balance-date-js"></span> </label>
                                                <div class="kt-input-icon">
                                                    <input value="0" type="text" disabled class="form-control net-balance-js" placeholder="{{__('Net Balance')}}">
                                                </div>
                                            </div>
                                        </div>


                                        <div data-type="{{ $safeToBankConst.','.$safeToSafeConst }}" class=" flex-1 d-flex justify-content-end pt-3 show-only-if">
                                            <div class="col-md-3 mb-3">
                                                <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                                <div class="kt-input-icon">
                                                    <input value="0" type="text" disabled class="form-control cash-balance-js" placeholder="{{__('Account Balance')}}">
                                                </div>
                                            </div>

                                        </div>


                                    </div>
                                </div>

                                <div class="kt-portlet__body">
                                    <div class="form-group">
                                        <div class="row">

                                            <div class="col-md-3">
                                                <label>{{__('Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select name="type" class="form-control type">
                                                        @foreach(BuyOrSellCurrency::getAllTypes() as $key => $title)
                                                        <option value="{{ $key }}" @if(isset($model) && $model->getType() == $key ) selected @endif > {{ $title }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <x-form.date :classes="'balance-date update-exchange-rate exchange-rate-date'" :label="__('Transaction Date')" :required="true" :model="$model??null" :name="'transaction_date'"  :placeholder="__('Select Date')"></x-form.date>
                                            </div>


                                            <div class="col-md-3">
                                                <label>{{__('Currency To Sell')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select data-current-selected="{{ isset($model) ? $model->getCurrencyToSell() : '' }}" js-from-when-change-trigger-change-account-type name="currency_to_sell" class="form-control current-from-currency update-exchange-rate current-invoice-currency" js-from-when-change-trigger-change-account-type>
                                                        <option selected>{{__('Select')}}</option>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrencyToSell() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <label>{{__('Currency To Buy')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select data-current-selected="{{ isset($model) ? $model->getCurrencyToBuy() : '' }}" js-from-when-change-trigger-change-account-type name="currency_to_buy" class="form-control current-to-currency update-exchange-rate receiving-currency-class" js-to-when-change-trigger-change-account-type>
                                                        <option selected>{{__('Select')}}</option>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrencyToBuy() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>


                                            <div class="col-md-3 ">
                                                <label>{{__('Currency To Sell Amount')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input name="currency_to_sell_amount" id="multiplierField" type="text" value="{{ isset($model) ? number_format($model->getAmountToSell()):0 }}" class="form-control recalculate-amount-in-main-currency amount-js greater-than-or-equal-zero-allowed " placeholder="{{__('Insert Amount')}}">
                                                    {{-- <input  type="hidden" value="{{ isset($model) ? $model->getAmountToSell():0 }}" name="currency_to_sell_amount" > --}}
                                                </div>
                                            </div>



                                            <div class="col-md-3 ">
                                                <label>{{__('Exchange Rate')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input id="calcField" type="text" value="{{ isset($model) ? $model->getExchangeRate():0 }}" name="exchange_rate" class="
													form-control exchange-rate-js exchange-rate-class recalculate-amount-in-main-currency 
													" placeholder="{{__('Exchange Rate')}}">
                                                </div>


                                            </div>

                                            {{-- {{ exchange rate *  Currency To Sell Amount }} --}}
                                            <div class="col-md-3 ">
                                                <label>{{__('Currency To Buy Amount')}}
                                                    {{-- @include('star') --}}
                                                </label>
                                                <div class="kt-input-icon">
                                                    {{-- <input type="hidden" class="amount-in-main-currency-js-hidden" name="currency_to_buy_amount" value="{{ isset($model) ? $model->getAmountToBuy():0 }}"> --}}
                                                    <input name="currency_to_buy_amount" id="resultField" readonly type="text" value="{{ isset($model) ? $model->getAmountToBuy():0 }}" class="
													form-control greater-than-or-equal-zero-allowed amount-in-main-currency-js
													" placeholder="{{__('Insert Amount')}}">
                                                </div>
                                            </div>










                                            {{-- {{  common between bank to bank and bank to safe   }} --}}

                                            <div class="col-md-6 show-only-if" data-type="{{ $bankToBankConst.','.$bankToSafeConst }}">
                                                <label>{{__('From Bank')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required js-from-when-change-trigger-change-account-type data-from-financial-institution-id name="from_bank_id" class="form-control from-financial-institution">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getFromBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>




                                            <div data-type="{{ $bankToBankConst.','.$bankToSafeConst }}" class="col-md-3 show-only-if">
                                                <label>{{__('From Account Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required required name="from_account_type_id" class="form-control js-from-update-account-number-based-on-account-type">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getFromAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3  show-only-if" data-type="{{ $bankToBankConst.','.$bankToSafeConst }}">
                                                <label>{{__('From Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required data-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0 }}" data-from-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0 }}" name="from_account_number" class="form-control js-from-account-number">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div data-type="{{ $bankToBankConst}}" class="col-md-6 show-only-if">
                                                <label>{{__('To Bank')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">

                                                        <select required js-to-when-change-trigger-change-account-type data-to-financial-institution-id name="to_bank_id" class="form-control ">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getToBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>
                                            <div data-type="{{ $bankToBankConst }}" class="col-md-3 show-only-if ">
                                                <label>{{__('To Account Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required name="to_account_type_id" class="form-control js-to-update-account-number-based-on-account-type">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getToAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div data-type="{{ $bankToBankConst }}" class="col-md-3 show-only-if ">
                                                <label>{{__('To Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required data-current-selected="{{ isset($model) ? $model->getToAccountNumber(): 0 }}" name="to_account_number" class="form-control js-to-account-number">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>



                                            {{-- only safe to bank   --}}
                                            <div data-type="{{ $safeToBankConst.','.$safeToSafeConst }}" class="col-md-2 mb-4 show-only-if">
                                                <label>{{ __('From Branch') }} <span class="multi_selection"></span> </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select id="from-branch-id" data-current-selected="{{ isset($model) ? $model->getFromBranchId() : 0 }}" data-live-search="true" data-actions-box="true" name="from_branch_id" required class="form-control customers-js kt-bootstrap-select select2-select kt_bootstrap_select ">
                                                            @foreach($selectedBranches as $id => $name)
                                                            <option @if(isset($model) && $id==$model->getFromBranchId()) selected @endif value="{{ $id }}">{{ $name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>


                                            {{-- only safe to bank  --}}
                                            <div data-type="{{ $safeToSafeConst.','.$bankToSafeConst	 }}" class="col-md-2 mb-4 show-only-if">
                                                <label>{{ __('To Branch') }} <span class="multi_selection"></span> </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select data-current-selected="{{ isset($model) ? $model->getToBranchId() : 0 }}" id="to-branch-id" data-live-search="true" data-actions-box="true" name="to_branch_id" required class="form-control customers-js kt-bootstrap-select select2-select kt_bootstrap_select ">
                                                            @foreach($selectedBranches as $id => $name)
                                                            <option @if(isset($model) && $id==$model->getToBranchId()) selected @endif value="{{ $id }}">{{ $name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>






                                            <div class="col-md-6 show-only-if" data-type="{{ $safeToBankConst }}">
                                                <label>{{__('To Bank')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">

                                                        <select required js-to-when-change-trigger-change-account-type data-to-financial-institution-id name="to_bank_id" class="form-control ">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getToBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 show-only-if " data-type="{{ $safeToBankConst }}">
                                                <label>{{__('To Account Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required name="to_account_type_id" class="form-control js-to-update-account-number-based-on-account-type">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getToAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3 show-only-if" data-type="{{ $safeToBankConst }}">
                                                <label>{{__('To Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required data-current-selected="{{ isset($model) ? $model->getToAccountNumber(): 0 }}" name="to_account_number" class="form-control js-to-account-number">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>







                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>


                        <!--end::Form-->

                        <!--end::Portlet-->
                </div>
            </div>
			@include('user_comment',['model'=>$model??null])
									<x-submitting-by-ajax :validation="1" :backTo="route('buy-or-sell-currencies.index',['company'=>$company->id])" />

            {{-- <x-submitting :backTo="route('buy-or-sell-currencies.index',['company'=>$company->id])" /> --}}
        </form>

        </div>
        @endsection
        @section('js')
        <!--begin::Page Scripts(used by this page) -->
        <script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
        <script src="{{ url('custom/math.js') }}" type="text/javascript"></script>
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
            $(document).on('change', 'select.js-from-update-account-number-based-on-account-type', function() {
                const val = $(this).val()
                const lang = $('body').attr('data-lang')
                const companyId = $('body').attr('data-current-company-id')
                const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
                const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
                const data = []
                let currency = $(this).closest('form').find('select.current-from-currency').val()
                let financialInstitutionBankId = parent.find('[data-from-financial-institution-id]').val()
                financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
                if (!val || !currency || !financialInstitutionBankId) {
                    return
                }
                const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
                $.ajax({
                    url
                    , data
                    , success: function(res) {
                        options = ''
                        var selectToAppendInto = $(parent).find('.js-from-account-number[name]')

                        for (key in res.data) {
                            var val = res.data[key]
                            var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
                            options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
                        }

                        selectToAppendInto.empty().append(options).trigger('change')
                    }
                })


		
				
				
				
				
				
            })
			
				$(document).on('change','.balance-date',function(){
				$('select#from-branch-id').trigger('change');
				$('select#to-branch-id').trigger('change');
				//,select.current-from-currency
			})
			$(document).on('change','.balance-date',function(){
				$('select.js-from-account-number').trigger('change');	
			})
               
				
				function getBranchFromCurrency()
				{
					const currentFromBranchId = $('select#from-branch-id').attr('data-current-selected');
                    const currencyName = $('select.current-from-currency').val();
						const modelId = $('#model-id').val();
						const modelType = 'BuyOrSellCurrency';
                        $.ajax({
                            url: "{{ route('get.branch.based.on.currency',['company'=>$company->id]) }}"
                            , data: {
								 currencyName
                            }
                            , success: function(res) {
								var branchOptions ='';
								for(var branchName in res.branches){
									var branchId = res.branches[branchName];
									var selected = branchId == currentFromBranchId ? 'selected':''; 
									branchOptions+=`<option value="${branchId}" ${selected} >${branchName}</option>`
								}
								$('select[name="from_branch_id"]').empty().append(branchOptions);
								$('select[name="from_branch_id"]').trigger('change');
                            }
                        })
				}
				 $(document).on('change', 'select.current-from-currency', getBranchFromCurrency) ;
				 getBranchFromCurrency();
				function handleToCurrency()
				{
					const currentToBranchId = $('select#to-branch-id').attr('data-current-selected');
                    const currencyName = $('select.current-to-currency').val();
						const modelId = $('#model-id').val();
						const modelType = 'BuyOrSellCurrency';
                        $.ajax({
                            url: "{{ route('get.branch.based.on.currency',['company'=>$company->id]) }}"
                            , data: {
								 currencyName,
								modelType,
								modelId
                            }
                            , success: function(res) {
								var branchOptions ='';
								for(var branchName in res.branches){
									var branchId = res.branches[branchName];
									var selected = branchId == currentToBranchId ? 'selected':''; 
									branchOptions+=`<option value="${branchId}" ${selected} >${branchName}</option>`
								}
								$('select[name="to_branch_id"]').empty().append(branchOptions);
                            }
                        })
                 
                
				
				}
				$(document).on('change', 'select.current-to-currency', handleToCurrency)
				handleToCurrency();
				
				
				 $(document).on('change', 'select#from-branch-id', function() {
					const currentFromBranchId = $('select#from-branch-id').attr('data-current-selected');
                    const branchId = $('select#from-branch-id').val();
                    const currencyName = $('select.current-from-currency').val();
						const modelId = $('#model-id').val();
						const modelType = 'BuyOrSellCurrency';
						const balanceDate = $('.balance-date').val();
                    if (branchId != '-1') {
                        $.ajax({
                            url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                            , data: {
                                branchId,
								 currencyName,
								modelType,
								modelId,
								balanceDate
                            }
                            , success: function(res) {
                                const endBalance = res.end_balance;
                                $('.cash-balance-js').val(number_format(endBalance))
                            }
                        })
                    }
                })
				
            $(document).on('change', 'select[js-from-when-change-trigger-change-account-type]', function() {
                if ($(this).attr('name')) {
                    $(this).closest('.kt-portlet__body').find('select.js-from-update-account-number-based-on-account-type').trigger('change')
                }
            })
			
            $(function() {
		
			   $('select.js-from-update-account-number-based-on-account-type[name]').trigger('change')
			
            })


            $(document).on('change', 'select.js-to-update-account-number-based-on-account-type', function() {
                if (!$(this).attr('name')) {
                    return
                }
                const currentType = $(this).closest('[data-type]').attr('data-type');
                const val = $(this).val()
                const lang = $('body').attr('data-lang')
                const companyId = $('body').attr('data-current-company-id')
                const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
                const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
                const data = []
                let currency = $(this).closest('form').find('select.current-to-currency').val()
                let financialInstitutionBankId = parent.find('[data-type="' + currentType + '"]').find('select[data-to-financial-institution-id]').val()

                financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
                if (!val || !currency || !financialInstitutionBankId) {
                    return
                }
                const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
                $.ajax({
                    url
                    , data
                    , success: function(res) {
                        options = ''
                        var selectToAppendInto = $(parent).find('.js-to-account-number[name]')
                        for (key in res.data) {
                            var val = res.data[key]
                            var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
                            options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
                        }
                        selectToAppendInto.empty().append(options).trigger('change')
                    }
                })






            })
            $(document).on('change', 'select[js-to-when-change-trigger-change-account-type]', function() {
                $(this).closest('.kt-portlet__body').find('select.js-to-update-account-number-based-on-account-type').trigger('change')
            })
            $(function() {
                $('select.js-to-update-account-number-based-on-account-type').trigger('change')
            })

        </script>

        <script>
            $(document).on('change', '.recalculate-amount-in-main-currency', function() {
                const parent = $(this).closest('.kt-portlet__body');
                const amount = parseFloat(number_unformat($(parent).find('.amount-js').val()))
                const exchangeRate = parseFloat($(parent).find('.exchange-rate-js').val())
                const amountInMainCurrency = parseFloat(amount * exchangeRate);
                $(parent).find('.amount-in-main-currency-js-hidden').val(amountInMainCurrency)
                $(parent).find('.amount-in-main-currency-js').val(number_format(amountInMainCurrency))
            })
            $(document).on('change', '.type', function(e) {
                const type = $(this).val()
                $('.show-only-if').addClass('hidden');
                $('.show-only-if [name]').each(function(index, element) {
                    $(element).attr('data-name', $(element).attr('name'))
                })

                $('.show-only-if input , .show-only-if select').each(function(index, element) {
                    $(element).removeAttr('name')
                })
                $('.show-only-if[data-type*="' + type + '"]').removeClass('hidden')
                $('.show-only-if[data-type*="' + type + '"] input,.show-only-if[data-type*="' + type + '"] select').each(function(index, element) {
                    $(element).attr('name', $(element).attr('data-name'))
                })

                $('div.show-only-if.hidden input,div.show-only-if.hidden select').removeAttr('required')
                $('div.show-only-if:not(.hidden) input,div.show-only-if.hidden select').removeAttr('required')
				
				$('select.current-from-currency').trigger('change');
				$('select.current-to-currency').trigger('change');
				
            })

            $('.type').trigger('change')

        </script>

        <script>
            function formatNumberWithCommas(number, decimals = 2) {
                return parseFloat(number).toLocaleString('en-US', {
                    minimumFractionDigits: decimals
                    , maximumFractionDigits: decimals
                });
            }

            function calculateResult() {
                let baseValue = document.getElementById("calcField").value.trim();
                let multiplierValue = document.getElementById("multiplierField").value.replace(/,/g, ""); // Remove commas for calculation

                let multiplier = parseFloat(multiplierValue);

                if (baseValue.startsWith("=")) {
                    try {
                        baseValue = math.evaluate(baseValue.substring(1)); // Evaluate formula
                    } catch (e) {
                        baseValue = 0;
                    }
                } else {
                    baseValue = parseFloat(baseValue);
                }

                if (!isNaN(baseValue)) {
                    baseValue = baseValue.toFixed(10); // Format to 5 decimals
                    document.getElementById("calcField").value = baseValue; // Update input field
                }

                if (!isNaN(baseValue) && !isNaN(multiplier)) {
                    let result = (baseValue * multiplier).toFixed(2); // Format result to 2 decimals
                    document.getElementById("resultField").value = formatNumberWithCommas(result, 2); // Format with commas
                } else {
                    document.getElementById("resultField").value = "";
                }
            }

            // Auto-calculate formula when leaving the field (blur)
            document.getElementById("calcField").addEventListener("blur", function() {
                calculateResult();
            });


      
            // Ensure proper formatting when leaving the multiplier field
            document.getElementById("multiplierField").addEventListener("blur", function() {
                let value = this.value.replace(/,/g, "").trim(); // Remove commas before parsing
                if (!isNaN(value) && value !== "") {
                    this.value = formatNumberWithCommas(value, 2); // Format after leaving the field
                }
                calculateResult();
            });

            // Allow smooth number input without interference
            document.getElementById("multiplierField").addEventListener("input", function() {
                let value = this.value.replace(/,/g, "").trim(); // Remove commas while typing
                if (!isNaN(value) || value === "") {
                    this.value = value; // Allow user to type naturally
                }
            });



			
            $(document).on('change', 'select.js-from-account-number', function() {
                const parent = $(this).closest('.kt-portlet__body');
                const financialInstitutionId = parent.find('select.from-financial-institution').val()
                const accountNumber = $(this).val();
                const accountType = parent.find('select.js-from-update-account-number-based-on-account-type').val();
                const modelId = $('#model-id').val();
				const modelType = 'BuyOrSellCurrency';
				const balanceDate = $('.balance-date').val();
                $.ajax({
                    url: "{{ route('update.balance.and.net.balance.based.on.account.number',['company'=>$company->id]) }}"
                    , data: {
                        accountNumber
                        , accountType
                        , financialInstitutionId,
				modelType,
				modelId,
				balanceDate
                    }
                    , type: "get"
                    , success: function(res) {
                        if (res.balance_date) {
                            $('.balance-date-js').html('[ ' + res.balance_date + ' ]')
                        }
                        if (res.net_balance_date) {
                            $('.net-balance-date-js').html('[ ' + res.net_balance_date + ' ]')
                        }
                        $('.net-balance-js').val(number_format(res.net_balance))
                        $('.balance-js').val(number_format(res.balance))

                    }
                })
            })

        </script>


        @endsection
