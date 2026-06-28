@extends('layouts.dashboard')
@section('css')
@php
use App\Models\LetterOfCreditIssuance;
@endphp
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
{{ __('Letter Of Credit Issuance Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('update.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$source]) :route('store.letter.of.credit.issuance',['company'=>$company->id,'source'=>$source]) }}" class="kt-form kt-form--label-right">

            <input type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="created_by" value="{{ auth()->user()->id }}">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <input type="hidden" name="source" value="{{ $source }}">

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
                                    {{ __((isset($model) ? 'Edit' : 'Add') . ' Letter Of Credit Issuance')}}
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
                                        {{__('Letter Of Credit Type')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">

                                <div class="form-group row">
                                    <input type="hidden" name="to-currency" class="update-exchange-rate to-currency" value="">
                                    <div class="col-md-3">
                                        <label>{{__('Issuance Type')}}
                                            @include('star')
                                        </label>
                                        <div class="input-group">
                                            <select name="category_name" required class="form-control repeater-select">
                                                <option value="">{{ __('Select') }}</option>
                                                @foreach(LetterOfCreditIssuance::getCategories() as $key => $title )
                                                <option value="{{ $key }}" @if(isset($model) && $model->getCategoryName() == $key ) selected @endif > {{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>

                                    <div class="col-md-4">
                                        <x-form.input :model="$model??null" :label="__('Transaction Name')" :type="'text'" :placeholder="__('Transaction Name')" :name="'transaction_name'" :class="''" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-5">
                                        <label> {{ __('Bank') }}
                                            @include('star')
                                        </label>
                                        <select js-update-interest-rate js-when-change-trigger-change-account-type change-financial-instutition-js id="financial-instutition-id" js-get-lc-facility-based-on-financial-institution js-when-change-trigger-change-account-type data-financial-institution-id required name="financial_institution_id" class="form-control">
                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getFinancialInstitutionBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                            @endforeach
                                        </select>
                                    </div>


                                    <div class="col-md-3">
                                        <label>{{__('LC Facility')}}
                                            @include('star')
                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select js-update-interest-rate required js-update-outstanding-balance-and-limits data-current-selected="{{ isset($model) ? $model->getLcFacilityId() : 0 }}" id="lc-facility-id" name="lc_facility_id" class="form-control">
                                                </select>
                                            </div>
                                        </div>
                                    </div>





                                    <div class="col-md-3">
                                        <x-form.input :id="'limit-id'" :default-value="0" :model="$model??null" :label="__('LC Limit')" :type="'text'" :placeholder="__('LC Limit')" :name="'limit'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :id="'total-lc-for-all-types-id'" :default-value="0" :model="$model??null" :label="__('Total LCs Outstanding Balance')" :type="'text'" :name="'total_lc_outstanding_balance'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :id="'total-room-id'" :default-value="0" :model="$model??null" :label="__('Total LCs Room')" :type="'text'" :placeholder="__('Total LCs Room')" :name="'total_lc_outstanding_balance'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>

                                    <div class="col-md-3">
                                        <label> {{ __('LC Type') }}
                                            @include('star')
                                        </label>

                                        <select js-update-outstanding-balance-and-limits id="lc-type" name="lc_type" class="form-control js-toggle-bond">
                                            {{-- <option selected>{{__('Select')}}</option> --}}
                                            @foreach(getLcTypes() as $name => $nameFormatted )
                                            <option value="{{ $name  }}" @if(isset($model) && $model->getLcType() == $name ) selected @endif > {{ $nameFormatted }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3 ">
                                        <x-form.input :id="'current-lc-type-outstanding-balance-id'" :default-value="0" :model="$model??null" :label="__('LC Type Outstanding Balance')" :type="'text'" :placeholder="__('LC Type Outstanding Balance')" :name="'lc_type_outstanding_balance'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :model="$model??null" :label="__('LC Code')" :type="'text'" :placeholder="__('LC Code')" :name="'lc_code'" :class="''" :required="true"></x-form.input>
                                    </div>


                                </div>
                            </div>
                        </div>







                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        {{__('Beneficiary Information')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">


                                    <div class="col-md-5">

                                        <label>{{__('Beneficiary Name')}}
                                            @include('star')
                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="kt-input-icon">
                                                <div class="input-group date">
                                                    <select required data-current-selected="{{ isset($model) ? $model->getBeneficiaryId():0 }}" js-update-contracts-based-on-customers data-live-search="true" data-actions-box="true" id="customer_name" name="partner_id" class="form-control select2-select">
                                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                        @foreach($beneficiaries as $customer)
                                                        <option @if(isset($model) && $model->getBeneficiaryId() == $customer->getId() ) selected @endif value="{{ $customer->getId() }}">{{ $customer->getName() }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>




                                    </div>
                                    <div class="col-md-1 hidden show-only-bond">
                                        <label style="visibility:hidden !important;"> *</label>
                                        <button type="button" class="add-new btn btn-primary d-block" data-toggle="modal" data-target="#add-new-customer-modal">
                                            {{ __('Add New') }}
                                        </button>
                                    </div>
                                    <div class="modal fade" id="add-new-customer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Add New Customer') }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <form>
                                                        <input value="" class="form-control" name="new_customer_name" id="new_customer_name" placeholder="{{ __('Enter New Customer Name') }}">
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                    <button type="button" class="btn btn-primary js-add-new-customer-if-not-exist">{{ __('Save') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>



                                    <div class="col-md-3 hidden show-only-bond">


                                        <x-form.input :default-value="1" :model="$model??null" :label="__('Transaction Reference')" :type="'text'" :placeholder="__('Transaction Reference')" :name="'transaction_reference'" :class="''" :required="true"></x-form.input>

                                    </div>

                                    @include('reports/LetterOfCreditIssuance/_contract-inputs')

                                    {{-- <div class="col-md-3 hidden show-only-bond">

                                        <x-form.date :label="__('Transaction Date')" :required="true" :model="$model??null" :name="'transaction_date'" :placeholder="__('Select Transaction Date')"></x-form.date>
                                    </div> --}}

                                </div>
                            </div>
                        </div>



                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        {{__('Letter Of Credit Information')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">

                                    <div class="col-md-3">

                                        <x-form.date :classes="'recalc-due-date issuance-date-js exchange-rate-date update-exchange-rate'" :label="__('Issuance Date (mm/dd/yy)')" :required="true" :model="$model??null" :name="'issuance_date'" :placeholder="__('Select Purchase Order Date')"></x-form.date>
                                    </div>

                                    <div class="col-md-3">
                                        <x-form.input :default-value="1" :model="$model??null" :label="__('LC Duration (Days)')" :type="'numeric'" :placeholder="__('LC Duration (Days)')" :name="'lc_duration_days'" :class="'recalc-due-date lc-duration-days-js'" :required="true"></x-form.input>
                                    </div>


                                    <div class="col-md-3">

                                        <x-form.date :classes="'due-date-js'" :readonly="true" :label="__('Due Date (mm/dd/yy)')" :required="true" :model="$model??null" :name="'due_date'" :placeholder="__('Select Due Date')"></x-form.date>
                                    </div>

                                    <div class="col-md-3">
                                        <label> {{ __('Lc Amount') }}
                                            @include('star')
                                        </label>
                                        <div>
                                            <input required value="{{ (isset($model) ? number_format($model->getLcAmount(),0) : 0) }}" class="form-control  only-greater-than-or-equal-zero-allowed amount-js  recalculate-amount-in-main-currency recalculate-cash-cover-amount-js recalculate-lc-commission-amount-js lc-amount-js" type="text" placeholder="{{ __('Lc Amount') }}">
                                            <input type="hidden" value="{{ (isset($model) ? $model->getLcAmount() : 0) }}" name="lc_amount" class="only-greater-than-zero-allowed ">

                                        </div>
                                    </div>


                                <div class="col-md-3">
                                    <label>{{__('LC Currency')}}
                                        @include('star')
                                    </label>
                                    <div class="input-group">
                                        <select  name="lc_currency" class="form-control lc-currency update-exchange-rate current-invoice-currency">
                                            <option selected>{{__('Select')}}</option>
                                            @foreach(getCurrencies() as $currencyName => $currencyValue )
                                            <option value="{{ $currencyName }}" @if(isset($model) && $model->getLcCurrency() == $currencyName ) selected @elseif($currencyName == 'USD' ) selected @endif > {{ $currencyValue }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <x-form.input :readonly="false" :default-value="1" :model="$model??null" :label="__('Exchange Rate')" :type="'text'" :placeholder="__('Exchange Rate')" :name="'exchange_rate'" :class="'exchange-rate-class recalculate-amount-in-main-currency exchange-rate-js only-greater-than-or-equal-zero-allowed'" :required="true"></x-form.input>
                                </div>
                                <div class="col-md-3">
                                    <x-form.input :readonly="true" :default-value="0" :model="$model??null" :label="__('Amount In Payment Currency')" :type="'text'" :placeholder="__('Amount In Payment Currency')" :name="'amount_in_main_currency'" :class="'amount-in-main-currency-js-hidden recalculate-cash-cover-amount-js '" :required="true"></x-form.input>
                                </div>
                                <div class="col-md-3">
                                    <x-form.input :id="$source != LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER ?  'cash-cover-rate-id' : 'cash-cover-rate-id2'" :default-value="$source == LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER ? 100 : 0 " :readonly="$source == LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER" :model="$model??null" :label="__('Cash Cover Rate %')" :type="'text'" :placeholder="__('Cash Cover Rate %')" :name="'cash_cover_rate'" :class="'only-greater-than-or-equal-zero-allowed recalculate-cash-cover-amount-js cash-cover-rate-js'" :required="true"></x-form.input>
                                </div>


                                <div class="col-md-3">
                                    <label>{{__('LC Cash Cover Currency')}}
                                        @include('star')
                                    </label>
                                    <div class="input-group">
                                        <select data-current-selected="{{ isset($model) ? $model->getLcCashCoverCurrency() : ''  }}" name="lc_cash_cover_currency" class="form-control update-exchange-rate current-currency receiving-currency-class" js-when-change-trigger-change-account-type>
                                            <option selected>{{__('Select')}}</option>
                                            @foreach(getCurrencies() as $currencyName => $currencyValue )
                                            <option value="{{ $currencyName }}" @if(isset($model) && $model->getLcCashCoverCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <x-form.input :default-value="0" :readonly="true" :model="$model??null" :label="__('Cash Cover Amount')" :type="'text'" :placeholder="__('Cash Cover Amount')" :name="'cash_cover_amount'" :class="'only-greater-than-or-equal-zero-allowed cash-cover-amount-js' " :required="true"></x-form.input>
                                </div>





                                <div class="col-md-3">
                                    <x-form.input :id="'lc_commission_rate-id'" :default-value="0" :model="$model??null" :label="__('LC Commission Rate %')" :type="'text'" :placeholder="__('LC Commission Rate %')" :name="'lc_commission_rate'" :class="'only-greater-than-or-equal-zero-allowed recalculate-lc-commission-amount-js lc-commission-rate-js'" :required="true"></x-form.input>
                                </div>


                                <div class="col-md-3">
                                    <x-form.input :default-value="0" :readonly="true" :model="$model??null" :label="__('LC Commission Amount')" :type="'text'" :placeholder="__('LC Commission Amount')" :name="'lc_commission_amount'" :class="'only-greater-than-or-equal-zero-allowed lc-commission-amount-js'" :required="true"></x-form.input>
                                </div>

                                <div class="col-md-3">
                                    <x-form.input :id="'min_lc_commission_fees_id'" :default-value="0" :readonly="true" :model="$model??null" :label="__('Min LC Commission Fees')" :type="'text'" :placeholder="__('Min LC Commission Fees')" :name="'min_lc_commission_fees'" :class="'only-greater-than-or-equal-zero-allowed '" :required="true"></x-form.input>
                                </div>

                                <div class="col-md-3">
                                    <x-form.input :id="'issuance_fees_id'" :default-value="0" :readonly="true" :model="$model??null" :label="__('Issuance Fees')" :type="'text'" :placeholder="__('Issuance Fees')" :name="'issuance_fees'" :class="'only-greater-than-or-equal-zero-allowed '" :required="true"></x-form.input>
                                </div>



                                <div class="col-md-3">
                                    <label>{{ __('Cash Cover From Account Type') }} <span class=""></span> </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <select id="account_type_id" name="cash_cover_deducted_from_account_type" class="form-control js-update-account-id-based-on-account-type">
                                                @foreach($cashCoverAccountTypes as $index => $accountType)
                                                <option @if(isset($model) && ($accountType->id == $model->getCashCoverDeductedFromAccountTypeId()) ) selected @endif value="{{ $accountType->id }}">{{ $accountType->getName() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label>{{ __('Account Number') }} <span class=""></span> </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <select js-cd-or-td-account-number data-current-selected="{{ isset($model) ? $model->getCashCoverDeductedFromAccountId(): 0 }}" name="cash_cover_deducted_from_account_id" class="form-control js-account-number">
                                                <option value="" selected>{{__('Select')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :id="'cd-or-td-amount-id'" :readonly="true" :default-value="0" :model="$model??null" :label="__('Amount')" :type="'text'" :placeholder="''" :name="'amount'" :class="''" :required="true"></x-form.input>
                                </div>


                                <div class="col-md-3">
                                    <label>{{__('Fees & Commission Account Type')}}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <select data-append-to-query=".js-account-id-2" name="lc_fees_and_commission_account_type" class="form-control 
												js-update-account-id-based-on-account-type
												">
                                                {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                @foreach($accountTypes as $index => $accountType)
                                                <option value="{{ $accountType->id }}" @if(isset($model) && $model->getFeesAndCommissionAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label>{{__('Deducted From Account # (Fees & Commission)')}}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <select data-current-selected="{{ isset($model) ? $model->getFeesAndCommissionAccountId(): 0 }}" name="lc_fees_and_commission_account_id" class="form-control js-account-id-2">
                                                <option value="" selected>{{__('Select')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <label>{{__('Self Financed Or By Bank')}}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <select name="financed_by_bank_or_self" id="financed-by-bank-or-self-select-id" class="form-control ">
                                                {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                @foreach(['bank'=>__('By Bank') , 'self'=>__('Self')] as $key => $title)
                                                <option value="{{ $key }}" @if(isset($model) && $model->getFinancedBy() == $key) selected @endif>{{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>



                                <div class="col-md-3 " id="financing-duration-div-id">
                                    <x-form.input :id="'financing-duration-id'" :model="$model??null" :label="__('Financing Duration (Days)')" :type="'text'" :placeholder="__('Financing Duration (Days)')" :name="'financing_duration'" :class="'only-greater-than-or-equal-zero-allowed'" :required="true"></x-form.input>
                                </div>


                            </div>
                        </div>
                </div>








 @include('user_comment',['model'=>$model??null])

                <x-submitting :backTo="route('view.letter.of.credit.issuance',['company'=>$company->id])" />
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




    $(document).on('click', '.js-add-new-customer-if-not-exist', function(e) {
        const customerName = $('#new_customer_name').val()
        const url = "{{ route('add.new.partner',['company'=>$company->id,'type'=>'Customer']) }}"
        if (customerName) {
            $.ajax({
                url
                , data: {
                    customerName
                }
                , type: "post"
                , success: function(response) {
                    if (response.status) {
                        $('select#customer_name').append('<option selected value="' + response.customer.id + '"> ' + customerName + ' </option>  ')
                        $('#add-new-customer-modal').modal('hide')
                    } else {
                        Swal.fire({
                            icon: "error"
                            , title: response.message
                        })
                    }
                }
            })
        }
    })

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
            var parentTag = $(this).parent().prop("tagName");
            $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)
        }
    })

</script>

<script src="/custom/money-receive.js">

</script>
<script>
    $(document).on('change', '.recalculate-amount-in-main-currency', function() {
        const parent = $(this).closest('.kt-portlet__body');
        const amount = parseFloat(number_unformat($(parent).find('.amount-js').val()))

        const exchangeRate = parseFloat($(parent).find('.exchange-rate-js').val())
        const amountInMainCurrency = parseFloat(amount * exchangeRate);

        $(parent).find('.amount-in-main-currency-js-hidden').val(amountInMainCurrency).trigger('change')
        $(parent).find('.amount-in-main-currency-js').val(number_format(amountInMainCurrency))
    })

</script>
<script>
    $(document).on('change', '.recalc-due-date', function(e) {
        e.preventDefault()
        let date = $('.issuance-date-js').val();
        date = date.replaceAll('-', '/')

        const issuanceDate = new Date(date);
        const duration = parseInt($('.lc-duration-days-js').val());
        if (issuanceDate || duration == '0') {
            const numberOfDays = duration
            let dueDate = issuanceDate.addDays(numberOfDays)
            dueDate = formatDateForSelect2(dueDate)
            $('.due-date-js').val(dueDate).trigger('change')
        }

    })
    $(document).on('change', '.recalculate-cash-cover-amount-js', function() {
        const lcAmount = number_unformat($('.amount-in-main-currency-js-hidden').val())
        const cashCoverRateJs = number_unformat($('.cash-cover-rate-js').val()) / 100
        const cashCoverAmount = lcAmount * cashCoverRateJs
        $('.cash-cover-amount-js').val(toFixed(cashCoverAmount))
    })

    $(document).on('change', '.recalculate-lc-commission-amount-js', function() {
        const lcAmount = number_unformat($('.lc-amount-js').val())
        const rate = number_unformat($('.lc-commission-rate-js').val()) / 100
        const lcCommissionAmount = lcAmount * rate
        $('.lc-commission-amount-js').val(toFixed(lcCommissionAmount))
    })

    $('.recalc-due-date').trigger('change')
    $('.recalculate-cash-cover-amount-js').trigger('change')
    $('.recalculate-lc-commission-amount-js').trigger('change')

</script>
<script>
    $(document).on('change', '.js-toggle-bond', function() {
        const isBond = $(this).val() == 'bid-bond'
        if (isBond) {
            $('.show-only-bond').removeClass('hidden')
            $('.hide-only-bond').addClass('hidden')
        } else {
            $('.hide-only-bond').removeClass('hidden')
            $('.show-only-bond').addClass('hidden')
        }
    })
    $(function() {
        $('.js-toggle-bond').trigger('change')
    })

</script>
<script>
    $(document).on('change', '[js-update-outstanding-balance-and-limits]', function(e) {
        e.preventDefault()
        const source = "{{ $source }}"
        const financialInstitutionId = $('select#financial-instutition-id').val()
        const lcType = $('select#lc-type').val()
        const lcIssuanceId = "{{ isset($model) ? $model->id : 0 }}"
        const letterOfCreditFacilityId = $('select#lc-facility-id').val();
        $.ajax({
            url: "{{ route('update.letter.of.credit.outstanding.balance.and.limit',['company'=>$company->id]) }}"
            , data: {
                financialInstitutionId
                , letterOfCreditFacilityId
                , lcType
                , lcIssuanceId
                , source
            }
            , type: "GET"
            , success: function(res) {
                $('#limit-id').val(res.limit).prop('readonly', true)
                $('#total-lc-for-all-types-id').val(res.total_lc_outstanding_balance).prop('readonly', true)
                $('#total-room-id').val(res.total_room).prop('readonly', true)
                $('#current-lc-type-outstanding-balance-id').val(res.current_lc_type_outstanding_balance).prop('readonly', true)
                $('#min_lc_commission_fees_id').val(res.min_lc_commission_rate).trigger('change');
			
                $('#lc-currency-id').val(res.currency_name).trigger('change');
                $('#lc_commission_rate-id').val(res.lc_commission_rate).trigger('change');
                $('#issuance_fees_id').val(res.min_lc_issuance_fees_for_current_lc_type).trigger('change');
                $('#cash-cover-rate-id').val(res.min_lc_cash_cover_rate_for_current_lc_type).trigger('change');

                $('input[type="hidden"].to-currency').val(res.currency_name).trigger('change')
                $('[js-update-contracts-based-on-customers]').trigger('change')
            }
        })
    })

</script>
@if(!isset($model))
<script>
    $('[js-update-outstanding-balance-and-limits]').trigger('change')

</script>
@endif
<script>


</script>






@include('reports.LetterOfCreditIssuance.commonJs')

<script>
    $(document).on('change', '[js-update-interest-rate]', function() {
        const financialInstitutionId = $('select#financial-instutition-id').val();
        const letterOfCreditFacilityId = $('select#lc-facility-id').val();
        if (!financialInstitutionId || !letterOfCreditFacilityId) {
            return;
        }
        $.ajax({
            url: "{{ route('get.interest.rate.for.financial.institution.id',['company'=>$company->id]) }}"
            , data: {
                financialInstitutionId
                , letterOfCreditFacilityId
            }
            , success: function(res) {
                // $('#interest-rate-id').val(res.interest_rate)
            }
        })
    })




    $(document).on('change', 'select[js-get-lc-facility-based-on-financial-institution]', function() {
        const financialInstitutionId = $('#financial-instutition-id').val();
        const currentSelected = $('select#lc-facility-id').attr('data-current-selected');

        $.ajax({
            url: "{{ route('get.lc.facility.based.on.financial.institution',['company'=>$company->id]) }}"
            , data: {
                financialInstitutionId
            }
            , success: function(res) {
                const lcFacilities = res.letterOfCreditFacilities;
                let options = '<option value="">{{ __("Select") }}</option>';
                for (id in lcFacilities) {
                    var name = lcFacilities[id];
                    options += `<option ${currentSelected == id ? 'selected' : '' } value="${id}"  >${name}</option>`
                }
                $('select#lc-facility-id').empty().append(options).trigger('change')
            }
        })
    })

    $('select[js-get-lc-facility-based-on-financial-institution]').trigger('change')

</script>
<script>
    $(document).on('change', '[js-cd-or-td-account-number]', function() {
        const parent = $(this).closest('.kt-portlet__body');
        const financialInstitutionId = $('select#financial-instutition-id').val();

        const accountType = parent.find('.js-update-account-id-based-on-account-type').val()
        const accountId = parent.find('[js-cd-or-td-account-number]').val();
        let url = "{{ route('get.account.amount.based.on.account.id',['company'=>$company->id , 'accountType'=>'replace_account_type' , 'accountId'=>'replace_account_id','financialInstitutionId'=>'replace_financial_institution_id' ]) }}";
        url = url.replace('replace_account_type', accountType);
        url = url.replace('replace_account_id', accountId);
        url = url.replace('replace_financial_institution_id', financialInstitutionId);
        if (accountType && accountId && financialInstitutionId) {
            $.ajax({
                url
                , success: function(res) {
                    parent.find('#cd-or-td-amount-id').attr('data-value', res.amount).val(number_format(res.amount) + ' ' + res.currencyName).trigger('change')
                }
            });

        } else {
            parent.find('#cd-or-td-amount-id').attr('data-value', 0).val(0).trigger('change')

        }
    })

</script>
<script>
    $(document).on('change', 'select#financed-by-bank-or-self-select-id', function(e) {
        const value = $(this).val();
        if (value == 'bank') {
            $('#financing-duration-div-id').show();
        } else {
            $('#financing-duration-id').val(0).trigger('change');
            $('#financing-duration-div-id').hide();
        }
    })
    $('select#financed-by-bank-or-self-select-id').trigger('change');

</script>


@endsection
