@extends('layouts.dashboard')
@section('css')
@php
use App\Models\CustomerInvoice;
use App\Models\MoneyReceived ;
@endphp

<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
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
        width: 12.5% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-17 {
        max-width: initial !important;
        width: 17% !important;
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
{{ __('Money Received Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ?  route('update.money.receive',['company'=>$company->id,'moneyReceived'=>$model->id]) :route('store.money.receive',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input type="hidden" name="current_cheque_id" value="{{ isset($model) && $model->cheque ? $model->cheque->id : 0 }}">
            <input type="hidden" name="cash_id" value="{{ isset($model) && $model->cashInSafe ? $model->cashInSafe->id : 0 }}">
            <input id="js-money-received-id" type="hidden" name="money_received_id" value="{{ isset($model) ? $model->id : 0 }}">
            {{-- <input type="hidden" id="js-down-payment-id" value="{{ isset($model) && $model->downPayment ? $model->downPayment->id : 0  }}"> --}}
            <input type="hidden" id="ajax-invoice-item" data-single-model="{{ $singleModel ? 1 : 0 }}" value="{{ $singleModel ? $singleModel : 0 }}">
            <input id="js-down-payment-id" type="hidden" name="down_payment_id" value="{{ isset($model) ? $model->id : 0 }}">
            @if(isset($model))
            <input type="hidden" name="modelId" value="{{ $model->id }}">
            <input type="hidden" name="modelType" value="MoneyReceived">
            @endif
            @csrf
            @if(isset($model))
            @method('put')
            @endif
            {{-- Money Received --}}
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Money Received')}}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2 mb-3">
                            <label>{{__('Receiving Date')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input type="text" name="receiving_date" max-date="{{ formatDateForDatePicker(now()) }}" value="{{ isset($model) ? formatDateForDatePicker($model->getReceivingDate()) : '' }}" class="form-control is-date-css exchange-rate-date update-exchange-rate" required readonly placeholder="Select date" id="kt_datepicker_max_date_is_today" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-2">
                            <label>{{__('Partner Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required name="partner_type" id="partner_type" class="form-control">
                                        @foreach(getAllPartnerTypesForCustomers() as $type =>$title)
                                        <option @if(isset($model) && $model->getPartnerType() == $type ) selected @endif value="{{ $type }}">{{$title}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>



                        <div class="col-md-1" id="invoice-currency-div-id">
                            <label class="text-nowrap">{{__('Invoice Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="invoice-currency-id" name="currency" class="form-control 
							currency-class
							
							
							@if(!$singleModel && !isset($model))
							invoice-currency-class 
							@endif
							update-exchange-rate
							current-invoice-currency
							
							 ajax-get-invoice-numbers">
                                        @foreach(isset($currencies) ? $currencies : getBanksCurrencies () as $currencyId=>$currentName)
                                        @php

                                        $selected = isset($model) ? $model->getCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency() ;
                                        $selected = $selected ? 'selected':'';
                                        @endphp
                                        <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-3">
                            <label>{{__('Name')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select data-current-selected="{{ isset($model) ? $model->getCustomerName() : '' }}" data-live-search="true" data-actions-box="true" id="customer_name" name="customer_id" class="form-control select2-select ajax-get-invoice-numbers  ajax-update-contracts customer-select">
                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}

                                            @foreach($customers as $customerId => $partnerName)
                                            <option @if($singleModel) selected @endif @if(isset($model) && $model->getPartnerName() == $partnerName ) selected @endif value="{{ $customerId }}">{{$partnerName}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>


                        <div class="col-md-1 ">
                            <label class="text-nowrap">{{__('Receive Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="receiving-currency-id" when-change-trigger-account-type-change name="receiving_currency" class="form-control 
							current-currency
							ajax-update-contracts
							contract-currency
							currency-class
							receiving-currency-class update-exchange-rate
							{{-- 
							 ajax-get-invoice-numbers --}}
							">
                                        <option value="" selected>{{__('Select')}}</option>
                                        @foreach(getCurrencies() as $currencyId=>$currentName)
                                        @php
                                        $selected = isset($model) ? $model->getReceivingCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency() ;
                                        $selected = isset($singleModel) && in_array($currentName,$currencies) ? 'selected':$selected;
                                        $selected = $selected ? 'selected':'';

                                        @endphp
                                        <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label>{{__('Money Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required name="type" id="type" class="form-control">
                                        <option value="" selected>{{__('Select')}}</option>

                                        <option @if(isset($model) && $model->isCashInSafe() ) selected @endif value="{{ MoneyReceived::CASH_IN_SAFE }}">{{__('Cash In Safe')}}</option>
                                        <option @if(isset($model) && $model->isCashInBank() ) selected @endif value="{{ MoneyReceived::CASH_IN_BANK }}">{{__('Bank Deposit')}}</option>
                                        <option @if(isset($model) && $model->isCheque() ) selected @endif value="{{ MoneyReceived::CHEQUE }}">{{__('Cheque')}}</option>
                                        <option @if(isset($model) && $model->isIncomingTransfer()) selected @endif value="{{ MoneyReceived::INCOMING_TRANSFER }}">{{__('Incoming Transfer')}}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="modal fade" id="js-choose-bank-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Select Bank') }}</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">

                                            <select id="js-bank-names" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                                @foreach($banks as $bankId => $bankEnAndAr)
                                                <option data-name="{{ $bankEnAndAr }}" value="{{ $bankId }}">{{ $bankEnAndAr }}</option>
                                                @endforeach
                                            </select>

                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                            <button id="js-append-bank-name-if-not-exist" type="button" class="btn btn-primary">{{ __('Save') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>







                            <div class="modal fade" id="js-choose-receiving-bank-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Receiving Bank') }}</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <select id="js-receiving-bank-names" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                                @foreach($banks as $bankId => $bankEnAndAr)
                                                <option data-name="{{ $bankEnAndAr }}" value="{{ $bankId }}">{{ $bankEnAndAr }}</option>
                                                @endforeach
                                            </select>

                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                            <button id="js-append-receiving-bank-name-if-not-exist" type="button" class="btn btn-primary">{{ __('Save') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>






                            {{-- <div class="modal fade" id="js-choose-receiving-branch-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Add Branch') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="text" id="js-receiving-branch-names" class="form-control">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button id="js-append-receiving-branch-name-if-not-exist" type="button" class="btn btn-primary">{{ __('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div> --}}




    </div>

    <div class="col-md-2" data-current-selected="{{ isset($mode) ? $model->getTransactionType() : '' }}" id="transaction-type-parent">
        <label>{{__('Transaction')}} @include('star')</label>
        <div class="kt-input-icon">
            <div class="input-group date">
                <select required name="transaction_type" id="transaction_type" class="form-control">
                </select>
            </div>
        </div>
    </div>

</div>
</div>
</div>

{{-- Cash In Safe Information--}}
<div class="kt-portlet js-section-parent hidden" id="{{ MoneyReceived::CASH_IN_SAFE }}">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label flex-1">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{__('Cash Information')}}
            </h3>
            <div class=" flex-1 d-flex justify-content-end pt-3">
                <div class="col-md-3 mb-3">
                    <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                    <div class="kt-input-icon">
                        <input value="0" type="text" disabled class="form-control cash-balance-js" data-type="{{  MoneyReceived::CASH_IN_SAFE }}" placeholder="{{__('Account Balance')}}">
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="form-group">
            <div class="row">
                <div class="col-md-5 width-45 ">
                    <label>{{__('Receiving Branch')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select data-current-selected="{{ isset($model) ? $model->getCashInSafeReceivingBranchId()  : 0 }}" id="branch-id" name="receiving_branch_id" class="form-control">
                                <option value="-1">{{__('Select Branch')}}</option>
                                @foreach($selectedBranches as $branchId=>$branchName)
                                <option value="{{ $branchId }}" {{ isset($model) && $model->getCashInSafeReceivingBranchId() == $branchId ? 'selected' : '' }}>{{ $branchName }}</option>
                                @endforeach
                            </select>
                            {{-- <button id="js-receiving-branch" class="btn btn-sm btn-primary">{{ __('Add New Branch') }}</button> --}}
                        </div>
                    </div>
                </div>
                <div class="col-md-3 closest-parent">
                    <label>{{__('Received Amount')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getReceivedAmount() :0 }}" name="received_amount[{{ MoneyReceived::CASH_IN_SAFE }}]" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. MoneyReceived::CASH_IN_SAFE .'-received-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyReceived::CASH_IN_SAFE }}" placeholder="{{__('Received Amount')}}">
                        <x-tool-tip title="{{__('Kash Vero')}}" />
                    </div>
                </div>
                <div class="col-md-3 width-12">
                    <label>{{__('Receipt Number')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <input type="text" name="receipt_number" value="{{ isset($model) ?  $model->getCashInSafeReceiptNumber()  : '' }}" class="form-control" placeholder="{{__('Receipt Number')}}">
                        <x-tool-tip title="{{__('Kash Vero')}}" />
                    </div>
                </div>

                <div class="col-md-2  width-12 show-only-when-invoice-currency-not-equal-receiving-currency">
                    <label>{{__('Exchange Rate')}} <span>dd</span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyReceived::CASH_IN_SAFE }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyReceived::CASH_IN_SAFE }}">
                    </div>
                </div>

                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                    <label>{{__('Amount In Invoice Currency')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyReceived::CASH_IN_SAFE }}]" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ MoneyReceived::CASH_IN_SAFE }}">
                    </div>
                </div>



            </div>
        </div>
    </div>
</div>


{{-- Bank Deposit Information--}}
{{-- Incoming Transfer Information--}}
<div class="kt-portlet js-section-parent hidden" id="{{ MoneyReceived::CASH_IN_BANK }}">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{__('Bank Deposit Information')}}
            </h3>
        </div>
    </div>

    <div class="kt-portlet__body">
        <div class="form-group">
            <div class="row">
                <div class="col-md-5 width-45">
                    <label>{{__('Receiving Bank')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select js-when-change-trigger-change-account-type data-financial-institution-id name="receiving_bank_id[{{ MoneyReceived::CASH_IN_BANK  }}]" class="form-control ">
                                @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getCashInBankReceivingBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 closest-parent">
                    <label>{{__('Deposit Amount')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getReceivedAmount():0 }}" name="received_amount[{{ MoneyReceived::CASH_IN_BANK }}]" class="form-control greater-than-or-equal-zero-allowed {{ 'js-'. MoneyReceived::CASH_IN_BANK .'-received-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyReceived::CASH_IN_BANK }}" placeholder="{{__('Insert Amount')}}">
                    </div>
                </div>



                <div class="col-md-3">
                    <label>{{__('Account Type')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select name="account_type[{{ MoneyReceived::CASH_IN_BANK }}]" class="form-control js-update-account-number-based-on-account-type">
                                <option value="" selected>{{__('Select')}}</option>
                                @foreach($accountTypes as $index => $accountType)
                                <option value="{{ $accountType->id }}" @if(isset($model) && $model->getCashInBankAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 width-12">
                    <label>{{__('Account Number')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select data-current-selected="{{ isset($model) ? $model->getCashInBankAccountNumber(): 0 }}" name="account_number[{{ MoneyReceived::CASH_IN_BANK }}]" class="form-control js-account-number">
                                <option value="" selected>{{__('Select')}}</option>
                            </select>
                        </div>
                    </div>
                </div>


                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency">
                    <label>{{__('Exchange Rate')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyReceived::CASH_IN_BANK }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyReceived::CASH_IN_BANK }}">
                    </div>
                </div>

                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                    <label>{{__('Amount In Invoice Currency')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyReceived::CASH_IN_BANK }}]" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ MoneyReceived::CASH_IN_BANK }}">
                    </div>
                </div>


            </div>
        </div>

    </div>
</div>




















{{-- Cheques Information--}}
<div class="kt-portlet js-section-parent hidden" id="{{ MoneyReceived::CHEQUE }}">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{__('Cheque Information')}}
            </h3>
        </div>
    </div>



    <div class="kt-portlet__body">
        <div class="form-group">
            <div class="row">
                <div class="col-md-5 width-45">
                    <label>{{__('Select Drawee Bank')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            {{-- drawee_bank_id
							**					هو البنك اللي العميل جابلي منه الشيك وبالتالي مش شرط يكون من بنوك لانة ممكن يكون من بنك خاص بالعميل

                                 --}}
                            <select name="drawee_bank_id" class="form-control ">
                                @foreach($selectedBanks as $bankId=>$bankName)
                                <option value="{{ $bankId }}" {{ isset($model) && $model->cheque && $model->cheque->getDraweeBankId() == $bankId ? 'selected':'' }}>{{ $bankName }}</option>
                                @endforeach
                            </select>
                            <button id="js-drawee-bank" class="btn btn-sm btn-primary">{{ __('Add New Bank') }}</button>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 width-12 closest-parent">
                    <label>{{__('Cheque Amount')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-max-cheque-value="0" value="{{ isset($model) ? $model->getReceivedAmount() : 0 }}" placeholder="{{ __('Please insert the cheque amount') }}" type="text" name="received_amount[{{ MoneyReceived::CHEQUE }}] " class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. MoneyReceived::CHEQUE .'-received-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyReceived::CHEQUE }}">
                    </div>
                </div>




                <div class="col-md-2 width-12">
                    <label>{{__('Due Date')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <input type="text" value="{{ isset($model) && $model->cheque ? formatDateForDatePicker($model->cheque->getDueDate()):'' }}" name="due_date" class="form-control is-date-css" readonly placeholder="Select date" id="kt_datepicker_2" />
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="la la-calendar-check-o"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-md-2 width-12">
                    <label>{{__('Cheque Number')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <input type="text" name="cheque_number" value="{{ isset($model) && $model->cheque ? $model->cheque->getChequeNumber() : 0 }}" class="form-control" placeholder="{{__('Cheque Number')}}">
                    </div>
                </div>

                <div class="col-md-2 ">
                    <label>{{__('Branch')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select name="cheque_branch_id" class="form-control">
                                {{-- <option value="-1">{{__('Branch')}}</option> --}}
                                @foreach($selectedBranches as $branchId=>$branchName)
                                <option value="{{ $branchId }}" {{ isset($model) && $model->getCashInSafeReceivingBranchId() == $branchId ? 'selected' : '' }}>{{ $branchName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 mt-4  width-12 show-only-when-invoice-currency-not-equal-receiving-currency">
                    <label>{{__('Exchange Rate')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyReceived::CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyReceived::CHEQUE }}">
                    </div>
                </div>

                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                    <label>{{__('Amount In Invoice Currency')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyReceived::CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ MoneyReceived::CHEQUE }}">
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- Incoming Transfer Information--}}
<div class="kt-portlet js-section-parent hidden" id="{{ MoneyReceived::INCOMING_TRANSFER }}">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{__('Incoming Transfer Information')}}
            </h3>
        </div>
    </div>

    <div class="kt-portlet__body">
        <div class="form-group">
            <div class="row">
                <div class="col-md-5 width-45">
                    <label>{{__('Receiving Bank')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">

                            <select js-when-change-trigger-change-account-type data-financial-institution-id name="receiving_bank_id[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control ">
                                @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getIncomingTransferReceivingBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                @endforeach
                            </select>

                        </div>
                    </div>
                </div>
                <div class="col-md-2 closest-parent">
                    <label>{{__('Incoming Transfer Amount')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getReceivedAmount():0 }}" name="received_amount[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control greater-than-or-equal-zero-allowed {{ 'js-'. MoneyReceived::INCOMING_TRANSFER .'-received-amount' }} main-amount-class recalculate-amount-class" data-type="{{ MoneyReceived::INCOMING_TRANSFER }}" placeholder="{{__('Insert Amount')}}">
                    </div>
                </div>



                <div class="col-md-3">
                    <label>{{__('Account Type')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select name="account_type[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control js-update-account-number-based-on-account-type">
                                <option value="" selected>{{__('Select')}}</option>
                                @foreach($accountTypes as $index => $accountType)
                                <option value="{{ $accountType->id }}" @if(isset($model) && $model->getIncomingTransferAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 width-12">
                    <label>{{__('Account Number')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select data-current-selected="{{ isset($model) ? $model->getIncomingTransferAccountNumber() : 0 }}" name="account_number[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control js-account-number">
                                <option value="" selected>{{__('Select')}}</option>
                            </select>
                        </div>
                    </div>
                </div>


                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency">
                    <label>{{__('Exchange Rate')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyReceived::INCOMING_TRANSFER }}">
                    </div>
                </div>

                <div class="col-md-2 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                    <label>{{__('Amount In Invoice Currency')}} <span class="currency-span"></span> @include('star')</label>
                    <div class="kt-input-icon">
                        <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyReceived::INCOMING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ MoneyReceived::INCOMING_TRANSFER }}">
                    </div>
                </div>



            </div>
        </div>

    </div>
</div>






{{-- Settlement Information "Commen Card" --}}

@if(!isset($model) || isset($model) && $model->partner->getCustomerType() == 'is_customer')
<div class="kt-portlet" id="settlement-card-id">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{__('Settlement Information')}}
            </h3>
        </div>
    </div>

    <div class="kt-portlet__body">


        <div class="js-append-to">
        </div>
        <div class="js-template hidden">
            <div class="col-md-12 js-duplicate-node">
                {!! CustomerInvoice::getSettlementsTemplate() !!}
            </div>
        </div>

        <hr>
        @include('reports.moneyReceived.unapplied-contract')



        <div class="row">
            <div class="col-md-1 width-10"></div>
            <div class="col-md-1 width-8"></div>
            <div class="col-md-1 width-8"></div>
            <div class="col-md-1 width-8"></div>
            <div class="col-md-1 width-12"></div>
            <div class="col-md-2 width-12"></div>
            <div class="col-md-2 width-12"></div>
            <div class="col-md-2 width-12 closest-parent">
                <label class="label text-nowrap">{{ __('Unapplied Amount') }}
                    <span class="taking-currency-span"></span>
                </label>
                <input readonly id="remaining-settlement-taking-js" class="form-control" placeholder="{{ __('Unapplied Amount') }}" type="text" value="0">
            </div>
            <div class="col-md-2 width-12 closest-parent">
                <label class="label text-nowrap">{{ __('Unapplied Amount') }}
                    <span class="invoice-currency-span "></span>
                </label>
                <input readonly id="remaining-settlement-js" class="form-control" placeholder="{{ __('Unapplied Amount') }}" type="text" name="unapplied_amount" value="0">
            </div>

        </div>
    </div>
</div>
@endif
@include('user_comment',['model'=>$model??null])
<x-submitting-by-ajax :backTo="route('view.money.receive',['company'=>$company->id])" />
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
    $('#type').change(function() {
        selected = $(this).val();
        $('.js-section-parent').addClass('hidden');
        if (selected) {
            $('#' + selected).removeClass('hidden');

        }


    });
    $('#type').trigger('change')

</script>
<script src="/custom/money-receive.js">

</script>

<script>
    $(document).on('change', 'input[name="receiving_date"]', function() {
        $('select#receiving-currency-id').trigger('change');
    })

    function getBalanceFromBranch() {
        const branchId = $('select#branch-id').val();
        const currencyName = $('select#receiving-currency-id').val();
        const modelId = $('#js-money-received-id').val();
        const modelType = 'MoneyReceived';
        const balanceDate = $('input[name="receiving_date"]').val();
        if (branchId != '-1') {
            $.ajax({
                url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                , data: {
                    branchId
                    , currencyName
                    , modelId
                    , modelType
                    , balanceDate
                }
                , success: function(res) {
                    const endBalance = res.end_balance;
                    $('.cash-balance-js').val(number_format(endBalance))
                }
            })
        }
    }

    function getBranchFromCurrency() {
        const branchQuery = $('select#branch-id');
        const currentFromBranchId = branchQuery.attr('data-current-selected');
        const currencyName = $('select#receiving-currency-id').val();

        $.ajax({
            url: "{{ route('get.branch.based.on.currency',['company'=>$company->id]) }}"
            , data: {
                currencyName
            }
            , success: function(res) {
                var branchOptions = '';
                for (var branchName in res.branches) {
                    var branchId = res.branches[branchName];
                    var selected = branchId == currentFromBranchId ? 'selected' : '';
                    branchOptions += `<option value="${branchId}" ${selected} >${branchName}</option>`
                }
                branchQuery.empty().append(branchOptions);
                branchQuery.trigger('change');
            }
        })
    }
    getBranchFromCurrency();
    $(document).on('change', 'select#receiving-currency-id', getBranchFromCurrency);
    $(document).on('change', 'select#branch-id', getBalanceFromBranch)


    $(function() {
        $('#type').trigger('change');
    })

    $(document).on('change', 'select.currency-class', function() {
        const invoiceCurrency = $('select#invoice-currency-id').val();
        const receivingCurrency = $('select#receiving-currency-id').val();
        const moneyType = $('select#type').val();
        const partnerType = $('select#partner_type').val();
        if (receivingCurrency != invoiceCurrency) {
            $('#remaining-settlement-taking-js').closest('.closest-parent').removeClass('visibility-hidden');
            $('#remaining-settlement-taking-js').closest('.closest-parent').find('.taking-currency-span').html('[ ' + receivingCurrency + ' ]')
        } else {
            $('#remaining-settlement-taking-js').closest('.closest-parent').addClass('visibility-hidden');
        }

        $('.main-amount-class').closest('.closest-parent').find('.currency-span').html(" [ " + receivingCurrency + " ]")
        $('.amount-after-exchange-rate-class').closest('.closest-parent').find('.currency-span').html(" [ " + invoiceCurrency + " ]")



        if (partnerType && partnerType != 'is_customer') {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
            return;
        }
        if (invoiceCurrency != receivingCurrency && invoiceCurrency && receivingCurrency) {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')
        } else {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
        }

    })
    $(document).on('change', '.recalculate-amount-class', function() {

        const moneyType = $(this).attr('data-type')
        const amount = number_unformat($('.main-amount-class[data-type="' + moneyType + '"]').val());
        const exchangeRate = number_unformat($('.exchange-rate-class[data-type="' + moneyType + '"]').val());
        const amountAfterExchangeRate = roundToTwo(amount / exchangeRate,2);
        $('.amount-after-exchange-rate-class[data-type="' + moneyType + '"]').val(amountAfterExchangeRate).trigger('change')
        $('.js-settlement-amount:eq(0)').trigger('change')
    })
    $(document).on('change', 'select[when-change-trigger-account-type-change]', function(e) {
        $('select.js-update-account-number-based-on-account-type').trigger('change')
    });
    $(function() {
        $('select.currency-class').trigger('change')
        $('.recalculate-amount-class').trigger('change')
    })




    $(document).on('change', '.ajax-update-contracts', function(e) {
        e.preventDefault()
        const customerId = $('select.customer-select').val()
        const currency = $('select.contract-currency').val()

        if (customerId && currency) {
            $.ajax({
                url: "{{ route('get.contracts.for.customer',['company'=>$company->id]) }}"
                , data: {
                    customerId
                    , currency
                }
                , success: function(res) {
                    let options = '<option value="general-down">{{ __("General Down Payment") }}</option>';
                    let selectedContractId = $('#contract-id').attr('data-current-selected')
                    for (id in res.contracts) {
                        options += `<option ${selectedContractId == id ? 'selected' :''} value="${id}">${res.contracts[id]}</option>`
                    }
                    $('select#contract-id').empty().append(options);
                    $('select#contract-id').trigger('change')
                }
            })
        }
    })

</script>



@if (!$singleModel&&!isset($model))
<script>
    $(function() {

        setTimeout(function() {
            $('select.ajax-get-invoice-numbers:eq(0)').trigger('change')
        }, 1500)
    })

</script>
<script>
    $('select#partner_type').trigger('change')

</script>
@endif

@endsection
