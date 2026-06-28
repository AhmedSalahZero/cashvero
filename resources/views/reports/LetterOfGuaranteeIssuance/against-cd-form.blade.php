@extends('layouts.dashboard')
@section('css')
@php
use App\Models\LetterOfGuaranteeIssuance;
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

@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('update.letter.of.guarantee.issuance',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$source]) :route('store.letter.of.guarantee.issuance',['company'=>$company->id,'source'=>$source]) }}" class="kt-form kt-form--label-right">
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
                                    {{ __((isset($model) ? 'Edit' : 'Add') . ' Against Certificate Of Deposit Letter Of Guarantee Issuance')}}
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
                                        {{__('Letter Of Guarantee Type')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">

                                    <div class="col-md-2">
                                        <label>{{__('Issuance Type')}}
                                            @include('star')
                                        </label>
                                        <div class="input-group">
                                            <select name="category_name" required class="form-control repeater-select">
                                                <option value="">{{ __('Select') }}</option>
                                                @foreach(LetterOfGuaranteeIssuance::getCategories() as $key => $title )
                                                <option value="{{ $key }}" @if(isset($model) && $model->getCategoryName() == $key ) selected @endif > {{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>

                                    <div class="col-md-3">
                                        <x-form.input :model="$model??null" :label="__('Transaction Name')" :type="'text'" :placeholder="__('Transaction Name')" :name="'transaction_name'" :class="''" :required="true"></x-form.input>
                                    </div>


                                    <div class="col-md-1">
                                        <label>{{__('LG Currency')}}
                                            @include('star')
                                        </label>
                                        <div class="input-group">
                                            <select name="lg_currency" class="form-control current-currency" js-when-change-trigger-change-account-type>
                                                <option selected>{{__('Select')}}</option>
                                                @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                <option value="{{ $currencyName }}" @if(isset($model) && $model->getLgCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>


                                    <div class="col-md-6">
                                        <label> {{ __('Financial Bank') }}
                                            @include('star')
                                        </label>
                                        <select required id="financial-instutition-id" required js-update-outstanding-balance-and-limits js-when-change-trigger-change-account-type data-financial-institution-id required name="financial_institution_id" class="form-control">
                                            <option value="">{{__('Select')}}</option>
                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getFinancialInstitutionBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label>{{ __('Account Type') }} <span class=""></span> </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select id="account_type_id" name="cd_or_td_account_type_id" class="form-control js-update-account-id-based-on-account-type">
                                                    @foreach($cdOrTdAccountTypes as $index => $accountType)
                                                    <option @if(isset($model) && ($accountType->id == $model->getCdOrTdAccountTypeId()) ) selected @endif value="{{ $accountType->id }}">{{ $accountType->getName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>{{ __('Account Number') }} <span class=""></span> </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select js-cd-or-td-account-number js-update-outstanding-balance-and-limits data-current-selected="{{ isset($model) ? $model->getCdOrTdId(): 0 }}" name="cd_or_td_id" class="form-control js-account-number">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>



                                    <div class="col-md-2 ">
                                        <x-form.input :id="'cd-or-td-amount-id'" :readonly="true" :default-value="0" :model="$model??null" :label="__('Amount')" :type="'text'" :placeholder="''" :name="'amount'" :class="'recalculate-cd-or-td-free-to-use'" :required="false"></x-form.input>
                                    </div>
                                    <div class="col-md-2 ">
                                        <x-form.input :id="'current-lg-outstanding-balance-id'" :default-value="0" :model="$model??null" :label="__('LG Outstanding Balance')" :type="'text'" :placeholder="__('LG Outstanding Balance')" :class="'recalculate-cd-or-td-free-to-use'" :required="false"></x-form.input>
                                    </div>
                                    <div class="col-md-2 ">
                                        <x-form.input :id="'against-cash-cover-amount-id'" :default-value="0" :model="$model??null" :label="__('Against Cash Cover')" :type="'text'" :placeholder="__('Against Cash Cover')" :class="'recalculate-cd-or-td-free-to-use'" :required="false"></x-form.input>
                                    </div>

                                    <div class="col-md-3 ">
                                        <x-form.input :id="'cd-or-td-free-to-use-amount-id'" :default-value="0" :model="$model??null" :label="__('CD Free To Use')" :type="'text'" :placeholder="__('')" :class="''" :required="false"></x-form.input>
                                    </div>


                                    <div class="col-md-3">
                                        <label> {{ __('LG Type') }}
                                            @include('star')
                                        </label>
                                        <select js-update-outstanding-balance-and-limits id="lg-type" name="lg_type" class="form-control js-toggle-bond">
                                            <option selected>{{__('Select')}}</option>
                                            @foreach(getLgTypes() as $name => $nameFormatted )
                                            <option value="{{ $name  }}" @if(isset($model) && $model->getLgType() == $name ) selected @endif > {{ $nameFormatted }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3 ">
                                        <x-form.input :id="'current-lg-type-outstanding-balance-id'" :default-value="0" :model="$model??null" :label="__('LG Type Outstanding Balance')" :type="'text'" :placeholder="__('LG Type Outstanding Balance')" :name="'lg_type_outstanding_balance'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :model="$model??null" :label="__('LG Code')" :type="'text'" :placeholder="__('LG Code')" :name="'lg_code'" :class="''" :required="true"></x-form.input>
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
                                                    <select required data-current-selected="{{ isset($model) ? $model->getBeneficiaryId():0 }}" data-current-selected="{{ isset($model) ? $model->getBeneficiaryId():0 }}" js-update-contracts-based-on-customers data-live-search="true" data-actions-box="true" id="customer_name" name="partner_id" class="form-control select2-select">
                                                        <option value="" selected>{{__('Select')}}</option>
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

                                    <div class="col-md-3 hidden hide-only-bond only-with-customer">

                                        <label> {{ __('Contract Reference') }}
                                            @include('star')
                                        </label>
                                        <select js-update-purchase-orders-based-on-contract id="contract-id" data-current-selected="{{ isset($model) ?  $model->getContractId() : 0 }}" name="contract_id" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                            {{-- @foreach($contracts as $contract)
                                            <option @if(isset($model) && $model->getContractId() == $contract->id ) selected @endif value="{{ $contract->getId() }}">{{ $contract->getName() }}</option>
                                            @endforeach --}}
                                        </select>
                                    </div>





                                    <div class="col-md-2 hidden hide-only-bond only-with-customer">

                                        <label> {{ __('Purchase Order') }}
                                            @include('star')
                                        </label>

                                        <select id="purchase-order-id" data-current-selected="{{ isset($model) ? $model->getPurchaseOrderId() : 0 }}" name="purchase_order_id" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                            {{-- @foreach($purchaseOrders as $purchaseOrder)
                                            <option @if(isset($model) && $model->getPurchaseOrderId() == $purchaseOrder->getId() ) selected @endif value="{{ $purchaseOrder->getId() }}">{{ $purchaseOrder->getName() }}</option>
                                            @endforeach --}}
                                        </select>
                                    </div>

                                    <div class="col-md-2 hidden hide-only-bond only-with-customer">

                                        <x-form.date :label="__('Purchase Order Date')" :required="true" :model="$model??null" :name="'purchase_order_date'" :placeholder="__('Select Purchase Order Date')"></x-form.date>
                                    </div>
                                    <div class="col-md-3 hidden show-only-bond">

                                        <x-form.date :label="__('Transaction Date')" :required="true" :model="$model??null" :name="'transaction_date'" :placeholder="__('Select Transaction Date')"></x-form.date>
                                    </div>

                                </div>
                            </div>
                        </div>



                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        {{__('Letter Of Guarantee Information')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">

                                    <div class="col-md-3">

                                        <x-form.date :classes="'recalc-renewal-date issuance-date-js'" :label="__('Issuance Date')" :required="true" :model="$model??null" :name="'issuance_date'" :placeholder="__('Select Purchase Order Date')"></x-form.date>
                                    </div>

                                    <div class="col-md-3">
                                        <x-form.input :default-value="1" :model="$model??null" :label="__('LG Duration Months')" :type="'numeric'" :placeholder="__('LG Duration Months')" :name="'lg_duration_months'" :class="'recalc-renewal-date lg-duration-months-js'" :required="true"></x-form.input>
                                    </div>


                                    <div class="col-md-3">

                                        <x-form.date :classes="'renewal-date-js'" :readonly="true" :label="__('Renewal Date')" :required="true" :model="$model??null" :name="'renewal_date'" :placeholder="__('Select Renewal Date')"></x-form.date>
                                    </div>



                                    <div class="col-md-3">
                                        <label> {{ __('LG Amount') }}
                                            @include('star')
                                        </label>
                                        <div>
                                            <input data-current-value="{{ isset($model) ? $model->getLgAmount() : 0 }}" required value="{{ (isset($model) ? number_format($model->getLgAmount(),0) : 0) }}" class="form-control only-greater-than-or-equal-zero-allowed recalculate-cash-cover-amount-js recalculate-lg-commission-amount-js lg-amount-js" type="text" placeholder="{{ __('Lg Amount') }}">
                                            <input data-current-value="{{ isset($model) ? $model->getLgAmount() : 0 }}" type="hidden" value="{{ (isset($model) ? $model->getLgAmount() : 0) }}" name="lg_amount" class="only-greater-than-zero-allowed ">

                                        </div>
                                    </div>







                                    <div class="col-md-3">
                                        <x-form.input :id="'lg_commission_rate-id'" :default-value="0" :model="$model??null" :label="__('LG Commission Rate %')" :type="'text'" :placeholder="__('LG Commission Rate %')" :name="'lg_commission_rate'" :class="'only-greater-than-or-equal-zero-allowed recalculate-lg-commission-amount-js lg-commission-rate-js'" :required="true"></x-form.input>
                                    </div>


                                    <div class="col-md-3">
                                        <x-form.input :default-value="0" :readonly="true" :model="$model??null" :label="__('LG Commission Amount')" :type="'text'" :placeholder="__('LG Commission Amount')" :name="'lg_commission_amount'" :class="'only-greater-than-or-equal-zero-allowed lg-commission-amount-js'" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :id="'min_lg_commission_fees_id'" :default-value="0" :readonly="false" :model="$model??null" :label="__('Min LG Commission Fees')" :type="'text'" :placeholder="__('Min LG Commission Fees')" :name="'min_lg_commission_fees'" :class="'only-greater-than-or-equal-zero-allowed '" :required="true"></x-form.input>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.input :id="'issuance_fees_id'" :default-value="0" :readonly="false" :model="$model??null" :label="__('Issuance Fees')" :type="'text'" :placeholder="__('Issuance Fees')" :name="'issuance_fees'" :class="'only-greater-than-or-equal-zero-allowed '" :required="true"></x-form.input>
                                    </div>



                                    <div class="col-md-3">
                                        <label>{{__('LG Commission Interval')}}
                                            @include('star')
                                        </label>
                                        <div class="input-group">
                                            <select name="lg_commission_interval" class="form-control repeater-select">
                                                {{-- <option selected>{{__('Select')}}</option> --}}
                                                @foreach(getCommissionInterval() as $key => $title )
                                                <option value="{{ $key }}" @if(isset($model) && $model->getLgCommissionInterval() == $key ) selected @endif > {{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>



                                    <div class="col-md-3">
                                        <label>{{__('Account Type')}}
                                            @include('star')
                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select name="lg_fees_and_commission_account_type" class="form-control js-update-account-id-based-on-account-type">
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
                                                <select data-current-selected="{{ isset($model) ? $model->getFeesAndCommissionAccountId(): 0 }}" name="lg_fees_and_commission_account_id" class="form-control js-account-number">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>



                                    {{-- <div class="col-md-3">
                                        <x-form.input :default-value="1" :model="$model??null" :label="__('Cash Cover Account Number')" :type="'numeric'" :placeholder="__('Cash Cover Account Naumber')" :name="'cash_cover_account_number'" :class="''" :required="true"></x-form.input>
                                    </div> --}}


                                </div>
                            </div>
                        </div>


                        @include('user_comment',['model'=>$model??null])










						<x-submitting-by-ajax :validation="1" :backTo="route('view.letter.of.guarantee.issuance',['company'=>$company->id])" />

                        {{-- <x-submitting /> --}}
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
                        $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

                    }
                })

            </script>

            <script src="/custom/money-receive.js">

            </script>

            <script>
                $(document).on('change', '.recalc-renewal-date', function(e) {
                    e.preventDefault()
                    let date = $('.issuance-date-js').val();
                    date = date.replaceAll('-', '/')

                    const issuanceDate = new Date(date);
                    const duration = $('.lg-duration-months-js').val();
                    if (issuanceDate || duration == '0') {
                        const numberOfMonths = duration

                        let renewalDate = issuanceDate.addMonths(numberOfMonths)

                        renewalDate = formatDateForSelect2(renewalDate)
                        $('.renewal-date-js').val(renewalDate).trigger('change')
                    }

                })
                $(document).on('change', '.recalculate-cash-cover-amount-js', function() {
                    const lgAmount = number_unformat($('.lg-amount-js').val())
                    const cashCoverRateJs = number_unformat($('.cash-cover-rate-js').val()) / 100
                    const cashCoverAmount = lgAmount * cashCoverRateJs
                    $('.cash-cover-amount-js').val(toFixed(cashCoverAmount))
                })

                $(document).on('change', '.recalculate-lg-commission-amount-js', function() {
                    const lgAmount = number_unformat($('.lg-amount-js').val())
                    const rate = number_unformat($('.lg-commission-rate-js').val()) / 100
                    const lgCommissionAmount = lgAmount * rate
                    $('.lg-commission-amount-js').val(toFixed(lgCommissionAmount))
                })

                $('.recalc-renewal-date').trigger('change')
                $('.recalculate-cash-cover-amount-js').trigger('change')
                $('.recalculate-lg-commission-amount-js').trigger('change')

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
                $(document).on('change', '[js-update-contracts-based-on-customers]', function(e) {
                    const customerId = $('select#customer_name').val()
                    if (!customerId) {
                        return;
                    }
                    $.ajax({
                        url: "{{route('update.contracts.based.on.customer',['company'=>$company->id])}}"
                        , data: {
                            customerId
                        , }
                        , type: "GET"
                        , success: function(res) {
                            var isCustomer = res.is_customer;
                            if (!isCustomer) {
                                $('.only-with-customer .required-label').addClass('visibility-hidden')

                            } else {
                                $('.only-with-customer .required-label').removeClass('visibility-hidden')

                            }
                            var contractsOptions = '';
                            var currentSelectedId = $('select#contract-id').attr('data-current-selected')
                            for (var contractName in res.contracts) {
								var contractId = res.contracts[contractName].id ;
								var contractCurrency = res.contracts[contractName].currency ;
								contractsOptions += `<option data-contract-currency="${contractCurrency}" ${currentSelectedId == contractId ? 'selected' : '' } value="${contractId}"> ${contractName}  </option> `;
							}
                            $('select#purchase-order-id').empty().selectpicker("refresh");
                            $('select#contract-id').empty().append(contractsOptions).selectpicker("refresh");
                            $('select#contract-id').trigger('change')
                        }
                    })
                })
                $('[js-update-contracts-based-on-customers]').trigger('change')

            </script>





            <script>
                $(document).on('change', '[js-update-purchase-orders-based-on-contract]', function(e) {
                    const contractId = $('select#contract-id').val()
                    if (!contractId) {
                        return
                    }
                    $.ajax({
                        url: "{{route('update.sales.orders.based.on.contract',['company'=>$company->id])}}"
                        , data: {
                            contractId
                        , }
                        , type: "GET"
                        , success: function(res) {
                            var purchaseOrdersOptions = '<option value="null"> {{ __("All") }} </option> ';
                            var currentSelectedId = $('select#purchase-order-id').attr('data-current-selected')
                            for (var purchaseOrderId in res.purchase_orders) {
                                var contractName = res.purchase_orders[purchaseOrderId];
                                purchaseOrdersOptions += `<option ${currentSelectedId == purchaseOrderId ? 'selected' : '' } value="${purchaseOrderId}"> ${contractName}  </option> `;
                            }
                            $('select#purchase-order-id').empty().append(purchaseOrdersOptions).selectpicker("refresh");
                        }
                    })
                })
                $('[js-update-purchase-orders-based-on-contract]').trigger('change')

            </script>
            <script>
                $(document).on('change', '[js-cd-or-td-account-number]', function() {
                    const parent = $(this).closest('.kt-portlet__body');
                    const accountType = parent.find('.js-update-account-id-based-on-account-type').val()
                    const accountId = parent.find('[js-cd-or-td-account-number]').val();
                    let financialInstitutionId = $('select#financial-instutition-id').val();

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
                $('[js-cd-or-td-account-number]').trigger('change')

            </script>
            @include('reports.LetterOfGuaranteeIssuance.commonJs')


            <script>
                $(document).on('change', '[js-update-outstanding-balance-and-limits]', function(e) {

                    const financialInstitutionId = $('select#financial-instutition-id').val()
                    const lgType = $('select#lg-type').val()
                    const source = "{{ $source }}"
                    const lgIssuanceId = "{{ isset($model) ? $model->id : 0 }}"
                    const accountTypeId = $('select#account_type_id').val()
                    const cdOrTdAccountId = $('select[name="cd_or_td_id"]').val()

                    $.ajax({
                        url: "{{ route('update.letter.of.guarantee.outstanding.balance.and.limit',['company'=>$company->id]) }}"
                        , data: {
                            financialInstitutionId
                            , lgIssuanceId
                            , lgType
                            , source
                            , accountTypeId
                            , cdOrTdAccountId
                        }
                        , type: "GET"
                        , success: function(res) {

                            let customerOptions = '<option value="">{{ __("Please Select") }}</option>';
                            let currentSelectedCustomerId = $('select#customer_name').attr('data-current-selected');

                           for(var customerName in res.customers ){
								var customerId = res.customers[customerName];
                                var isSelected = customerId == currentSelectedCustomerId ? 'selected' : '';
                                customerOptions += '<option ' + isSelected + ' value="' + customerId + '">' + customerName + '</option> ';
                            }
                            $('select#customer_name').empty().append(customerOptions).trigger('change');
                            $('#limit-id').val(res.limit).prop('readonly', true)
                            $('#total-lg-for-all-types-id').val(res.total_lg_outstanding_balance).prop('readonly', true)
                            $('#total-room-id').val(res.total_room).prop('readonly', true)
                            $('#input[name="lg_currency"]').val(res.currency_name)
                            $('#current-lg-outstanding-balance-id').val(res.total_lg_outstanding_balance).prop('readonly', true).trigger('change')
                            $('#against-cash-cover-amount-id').val(res.total_cash_cover_statement_debit).prop('readonly', true).trigger('change')
                            // $('#cd-or-td-free-to-use-amount-id').val(cdOrTdFreeToUseAmount).prop('readonly', true)
                            $('#current-lg-type-outstanding-balance-id').val(res.current_lg_type_outstanding_balance).prop('readonly', true)
                            $('#min_lg_commission_fees_id').val(res.min_lg_commission_rate).trigger('change');
                            //        $('#lg_commission_rate-id').val(res.lg_commission_rate).trigger('change');
                            $('#issuance_fees_id').val(res.min_lg_issuance_fees_for_current_lg_type).trigger('change');
                            $('#cash-cover-rate-id').val(res.min_lg_cash_cover_rate_for_current_lg_type).trigger('change');
                            $('[js-update-contracts-based-on-customers]').trigger('change')
                        }
                    })
                })

            </script>
            <script>
            </script>
            @if(!isset($model))
            <script>
                $('[js-update-outstanding-balance-and-limits]').trigger('change')

            </script>
            @endif
            @endsection
