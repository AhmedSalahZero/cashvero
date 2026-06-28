@extends('layouts.dashboard')
@section('css')
@php
use App\Models\MoneyPayment ;
use App\Models\SupplierInvoice;
$banks =[];
$selectedBanks = [];
@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark input,
    .money-flow-dark select,
    .money-flow-dark .dropdown-toggle.bs-placeholder {
        border: 1px solid #CCE2FD !important
    }

    .money-flow-dark .form-control:disabled,
    .money-flow-dark .form-control[readonly] {
        background-color: #f7f8fa;
        opacity: 1;
    }

    .money-flow-dark .action-class {
        color: white !important;
        background-color: #0742A6 !important;
    }

    .money-flow-dark label {
        text-align: left !important;
    }

    .money-flow-dark .max-w-6 {
        max-width: initial !important;
        width: 6% !important;
        flex: initial !important;
    }

    .money-flow-dark .max-w-15 {
        max-width: initial !important;
        width: 15% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-9-5 {
        max-width: initial !important;
        width: 9% !important;
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


    .money-flow-dark .width-40 {
        max-width: initial !important;
        width: 40% !important;
        flex: initial !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Payment Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
@php
	$routeAction = isset($model) ?  route('update.money.payment',['company'=>$company->id,'moneyPayment'=>$model->id]) :route('store.money.payment',['company'=>$company->id])
@endphp
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->
        <form method="post" action="{{ $routeAction }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input id="js-money-payment-id" type="hidden" name="money_payment_id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="current_cheque_id" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->id : 0 }}">
            <input type="hidden" name="cash_id" value="{{ isset($model) && $model->cashPayment ? $model->cashPayment->id : 0 }}">
            {{-- <input type="hidden" id="js-down-payment-id" value="{{ isset($model) && $model->downPayment ? $model->downPayment->id : 0  }}"> --}}
            <input type="hidden" id="ajax-invoice-item" data-single-model="{{ $singleModel ? 1 : 0 }}" value="{{ $singleModel ? $singleModel : 0 }}">
            <input id="js-down-payment-id" type="hidden" name="down_payment_id" value="{{ isset($model) ? $model->id : 0 }}">

            @if(isset($model))
            <input type="hidden" name="modelId" value="{{ $model->id }}">
            <input type="hidden" name="modelType" value="MoneyPayment">
            @endif


            @csrf
            @if(isset($model))
            @method('put')
            @endif

            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Money Payment')}}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2">
                            <label>{{__('Payment Date')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input type="text" name="delivery_date" value="{{ isset($model) ? formatDateForDatePicker($model->getDeliveryDate()) : '' }}" class="form-control balance-date exchange-rate-date update-exchange-rate is-date-css" readonly placeholder="Select date" id="kt_datepicker_max_date_is_today" />
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
                                        @foreach( getAllPartnerTypesForSuppliers() as $type => $title)
                                        <option @if(isset($model) && $model->getPartnerType()==$type ) selected @endif value="{{ $type }}">{{$title}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        @php
                        $currentPaymentCurrency = null ;
                        @endphp

                        <div class="col-md-1" id="invoice-currency-div-id">
                            <label class="text-nowrap">{{__('Invoice Currency')}} @include('star')</label>
                            @php
                            $selectedFound = false ;
                            @endphp

                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="invoice-currency-id" name="currency" class="form-control
							 
							currency-class
							 
							@if(!$singleModel && !isset($model) )
							invoice-currency-class 
							@endif
							 update-exchange-rate 
							 current-invoice-currency  ajax-get-invoice-numbers {{ $selectedCurrency }}">
                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                        @foreach(isset($currencies) ? $currencies : getBanksCurrencies () as $currencyId=>$currentName)
                                        @php
                                        $selected = $selectedCurrency == $currentName ;

                                        if($selected){
                                        $selectedFound = true ;
                                        }

                                        if(!$selected && !$selectedFound){
                                        $selected = isset($model) ? $model->getCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency();
                                        if($selected){
                                        $selectedFound = true ;

                                        }
                                        }
                                        $selected = $selected ? 'selected':'';
                                        if(($selected || (isset($singleModel) && $singleModel)) && !isset($model)){
                                        $currentPaymentCurrency = $currencyId ;
                                        }
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
                                        <select data-current-selected="{{ isset($model) ? $model->getSupplierName() : '' }}" data-live-search="true" data-actions-box="true" id="supplier_name" name="supplier_id" class="form-control select2-select ajax-get-invoice-numbers ajax-update-contracts supplier-select supplier-js">
                                            <option value="" selected>{{__('Select')}}</option>
                                            @foreach($suppliers as $supplierId => $supplierName)
                                            <option @if($singleModel) selected @endif @if(isset($model) && $model->getSupplierName() == $supplierName ) selected @endif value="{{ $supplierId }}">{{$supplierName}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>
                        @php
                        $selectedFound = false ;
                        @endphp
                        <div class="col-md-1">
                            <label class="text-nowrap">{{__('Pay Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="receiving-currency-id" when-change-trigger-account-type-change name="payment_currency" class="form-control
							contract-currency
							ajax-update-contracts
							currency-class
							receiving-currency-class
							update-exchange-rate
							 current-currency">

                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                        @foreach(getCurrencies() as $currencyId=>$currentName)
                                        @php
                                        $selected = isset($model) ? $model->getPaymentCurrency() == $currencyId : false;
                                        $selected = $selected ? 'selected':'';
                                        if((!$selected && $currentPaymentCurrency == $currencyId) && !$selectedFound){
                                        $selected = 'selected';
                                        $selectedFound = true ;
                                        }
                                        if(!$selected && !$selectedFound){
                                        $selected = isset($singleModel) && in_array($currentName,$currencies) ? 'selected':$selected;
                                        $selectedFound= true ;
                                        }
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
                                        <option @if(isset($model) && $model->isCashPayment() ) selected @endif value="{{ MoneyPayment::CASH_PAYMENT }}">{{__('Cash Payment')}}</option>
                                        <option @if(isset($model) && $model->isPayableCheque() ) selected @endif value="{{ MoneyPayment::PAYABLE_CHEQUE }}">{{__('Payable Cheques')}}</option>
                                        <option @if(isset($model) && $model->isOutgoingTransfer()) selected @endif value="{{ MoneyPayment::OUTGOING_TRANSFER }}">{{__('Outgoing Transfer')}}</option>
                                    </select>
                                </div>
                            </div>
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
            <div class="kt-portlet js-section-parent hidden" id="{{ MoneyPayment::CASH_PAYMENT}}">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label flex-1">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Cash Payment Information')}}
                        </h3>
                        <div class=" flex-1 d-flex justify-content-end pt-3">
                            <div class="col-md-3 mb-3">
                                <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                <div class="kt-input-icon">
                                    <input value="0" type="text" disabled class="form-control cash-balance-js" data-type="{{  MoneyPayment::PAYABLE_CHEQUE }}" placeholder="{{__('Account Balance')}}">
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-5 width-40 ">
                                <label>{{__('Paying Branch')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select id="branch-id" name="delivery_branch_id" class="form-control">
                                            {{-- <option value="-1">{{__('Select Branch')}}</option> --}}
                                            @foreach($selectedBranches as $branchId=>$branchName)
                                            <option value="{{ $branchId }}" {{ isset($model) && $model->getCashPaymentBranchId() == $branchId ? 'selected' : '' }}>{{ $branchName }}</option>
                                            @endforeach
                                        </select>
                                        {{-- <button id="js-delivery-branch" class="btn btn-sm btn-primary">{{ __('Add New Branch') }}</button> --}}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 closest-parent">
                                <label>{{__('Paid Amount')}} <span class="currency-span"></span> @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-current-value="{{ isset($model) ? $model->getPaidAmount():0 }}" data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount() :0 }}" name="paid_amount[{{ MoneyPayment::CASH_PAYMENT}}]" class="form-control only-greater-than-or-equal-zero-allowed  {{ 'js-'. MoneyPayment::CASH_PAYMENT.'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::CASH_PAYMENT }}" placeholder="{{__('Paid Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}" />
                                </div>
                            </div>
                            <div class="col-md-3 width-12">
                                <label>{{__('Receipt Number')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="receipt_number" value="{{ isset($model) ?  $model->getCashPaymentReceiptNumber()  : '' }}" class="form-control" placeholder="{{__('Receipt Number')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}" />
                                </div>
                            </div>
                            <div class="col-md-2 width-12 show-only-when-invoice-currency-not-equal-receiving-currency">
                                <label>{{__('Exchange Rate')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::CASH_PAYMENT }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::CASH_PAYMENT }}">
                                </div>
                            </div>

                            <div class="col-md-3 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden">
                                <label>{{__('Amount In Invoice Currency')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyPayment::CASH_PAYMENT }}]" class="form-control  amount-after-exchange-rate-class" data-type="{{ MoneyPayment::CASH_PAYMENT }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>





















            {{-- Cheques Information--}}
            <div class="kt-portlet js-section-parent hidden" id="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label flex-1">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Payable Cheque Information')}}
                        </h3>
                        <div class=" flex-1 d-flex justify-content-end pt-3">
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
                                    {{-- <x-tool-tip title="{{__('Kash Vero')}}" /> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="kt-portlet__body">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6  mb-3">
                                <label> {!! __('Payment Bank') !!} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">

                                        <select js-when-change-trigger-change-account-type data-financial-institution-id name="delivery_bank_id[{{ MoneyPayment::PAYABLE_CHEQUE  }}]" class="form-control financial-institution-id">
                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getPayableChequePaymentBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                            @endforeach
                                        </select>
                                        {{-- <button id="js-delivery-bank" class="btn btn-sm btn-primary">{{ __('Add New Bank') }}</button> --}}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label> {!! __('Account Type') !!} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="account_type[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control js-update-account-number-based-on-account-type">
                                            <option value="" selected>{{__('Select')}}</option>
                                            @foreach($accountTypes as $index => $accountType)
                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getPayableChequeAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-2 width-12">
                                <label> {!! __('Account Number') !!} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select data-current-selected="{{ isset($model) ? $model->getPayableChequeAccountNumber() : 0 }}" name="account_number[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control js-account-number">
                                            <option value="" selected>{{__('Select')}}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 closest-parent">
                                <label>{{__('Cheque Amount')}} <span class="currency-span"></span> @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-max-cheque-value="0" value="{{ isset($model) ? $model->getPaidAmount() : 0 }}" placeholder="{{ __('Please insert the cheque amount') }}" type="text" name="paid_amount[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. MoneyPayment::PAYABLE_CHEQUE .'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                                </div>
                            </div>



                            <div class="col-md-3">
                                <label>{{__('Due Date')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="text" value="{{ isset($model) && $model->payableCheque ? formatDateForDatePicker($model->payableCheque->getDueDate()):'' }}" name="due_date" class="form-control is-date-css" readonly placeholder="Select date" id="kt_datepicker_2" />
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="la la-calendar-check-o"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-3">
                                <label>{{__('Cheque Number')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="cheque_number" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->getChequeNumber() : 0 }}" class="form-control" placeholder="{{__('Cheque Number')}}">
                                </div>
                            </div>

                            <div class="col-md-2 width-12 show-only-when-invoice-currency-not-equal-receiving-currency">
                                <label>{{__('Exchange Rate')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                                </div>
                            </div>

                            <div class="col-md-3 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                                <label>{{__('Amount In Invoice Currency')}} <span class="currency-span"></span> @include('star')</label>
                                <div class="kt-input-icon">
                                    <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control amount-after-exchange-rate-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                                </div>
                            </div>



                            {{-- <div class="col-md-4">
                        <label>{{__('Select Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="currency" class="form-control">
                                        <option value="" selected>{{__('Select')}}</option>
                                        <option>EGP</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                        <option>GBP</option>
                                    </select>
                                </div>
                            </div>
                        </div> --}}
                    </div>
                </div>

            </div>
    </div>

    {{-- Outgoing Transfer Information--}}
    <div class="kt-portlet js-section-parent hidden" id="{{ MoneyPayment::OUTGOING_TRANSFER }}">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label flex-1">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{__('Outgoing Transfer Information')}}
                </h3>

                <div class=" flex-1 d-flex justify-content-end pt-3">
                    <div class="col-md-3 mb-3">
                        <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                        <div class="kt-input-icon">
                            <input value="0" type="text" disabled class="form-control balance-js" data-type="{{  MoneyPayment::OUTGOING_TRANSFER }}" placeholder="{{__('Account Balance')}}">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>{{__('Net Balance')}} <span class="net-balance-date-js"></span> </label>
                        <div class="kt-input-icon">
                            <input value="0" type="text" disabled class="form-control net-balance-js" placeholder="{{__('Net Balance')}}">
                            {{-- <x-tool-tip title="{{__('Kash Vero')}}" /> --}}
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="kt-portlet__body">
            <div class="form-group">
                <div class="row">
                    <div class="col-md-5 width-40">
                        <label> {!! __('Payment <br> Bank') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">

                                <select js-when-change-trigger-change-account-type data-financial-institution-id name="delivery_bank_id[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control financial-institution-id">
                                    @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                    <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getOutgoingTransferDeliveryBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                    @endforeach
                                </select>

                            </div>
                        </div>
                    </div>




                    <div class="col-md-3">
                        <label> {!! __('Account <br> Type') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select name="account_type[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control js-update-account-number-based-on-account-type">
                                    <option value="" selected>{{__('Select')}}</option>
                                    @foreach($accountTypes as $index => $accountType)
                                    <option value="{{ $accountType->id }}" @if(isset($model) && $model->getOutgoingTransferAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 width-12">
                        <label> {!! __('Account <br> Number') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select data-current-selected="{{ isset($model) ? $model->getOutgoingTransferAccountNumber() : 0 }}" name="account_number[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control js-account-number">
                                    <option value="" selected>{{__('Select')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 max-w-15 closest-parent ">
                        <label> {!! __('Outgoing <br> Transfer Amount') !!} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-current-value="{{ isset($model) ? $model->getPaidAmount():0 }}" data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount():0 }}" name="paid_amount[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed  {{ 'js-'. MoneyPayment::OUTGOING_TRANSFER .'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}" placeholder="{{__('Insert Amount')}}">
                        </div>
                    </div>



                    <div class="col-md-3 mt-4  show-only-when-invoice-currency-not-equal-receiving-currency">
                        <label>{!! __('Exchange Rate') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-current-value="{{ isset($model) ? $model->getExchangeRate() : 1 }}" value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}">
                        </div>
                    </div>

                    <div class="col-md-3 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden">
                        <label>{{__('Amount In Invoice Currency')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control  amount-after-exchange-rate-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}">
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </div>






    {{-- Settlement Information "Commen Card" --}}
    @if(!isset($model) || isset($model) && $model->partner->getSupplierType() == 'is_supplier' )
	@if(!(isset($model) && $model->isOpenBalance()))
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
                <div class="col-md-12 js-duplicate-node">
                </div>
            </div>


            <div class="js-template hidden">
                <div class="col-md-12 js-duplicate-node">

                    <div class=" kt-margin-b-10 border-class">
                        <div class="form-group row align-items-end settlement-row-parent">

                            <div class="col-md-1 width-10">
                                <label> {{ __('Invoice Number') }} </label>
                                <div class="kt-input-icon">
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <input type="hidden" name="settlements[][invoice_id]" value="0" class="js-invoice-id">
                                            <input readonly class="form-control js-invoice-number" data-invoice-id="0" name="settlements[][invoice_number]" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-1 width-9">
                                <label> {{ __('Invoice Date') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input name="settlements[][invoice_date]" type="text" class="form-control js-invoice-date" disabled />

                                    </div>
                                </div>
                            </div>

                            <div class="col-md-1 width-9">
                                <label> {{ __('Due Date') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input name="settlements[][invoice_due_date]" type="text" class="form-control js-invoice-due-date" disabled />

                                    </div>
                                </div>
                            </div>


                            <div class="col-md-1 width-8">
                                <label> {{ __('Currency') }} </label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][currency]" type="text" disabled class="form-control js-currency">
                                </div>
                            </div>

                            <div class="col-md-1 width-12">
                                <label> {{ __('Net Invoice Amount') }} </label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][net_invoice_amount]" type="text" disabled class="form-control js-net-invoice-amount">
                                </div>
                            </div>


                            <div class="col-md-2 width-12">
                                <label> {{ __('Paid Amount') }} </label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][paid_amount]" type="text" disabled class="form-control js-paid-amount">
                                </div>
                            </div>

                            <div class="col-md-2 width-12">
                                <label> {{ __('Net Balance') }} </label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][net_balance]" type="text" readonly class="form-control js-net-balance">
                                </div>
                            </div>



                            <div class="col-md-1 width-9-5">
                                <label> {{ __('Settlement Amount') }} <span class="text-danger ">*</span></label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][settlement_amount]" placeholder="" type="text" class="form-control js-settlement-amount only-greater-than-or-equal-zero-allowed settlement-amount-class">
                                </div>
                            </div>
                            <div class="col-md-1 width-9-5">
                                <label> {{ __('Withhold Amount') }} <span class="text-danger ">*</span> </label>
                                <div class="kt-input-icon">
                                    <input name="settlements[][withhold_amount]" placeholder="" type="text" class="form-control js-withhold-amount only-greater-than-or-equal-zero-allowed ">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="add-new btn btn-primary d-block" data-toggle="modal" data-target="#add-new-customer-modal--0">
                                    {{ __('Allocate') }}
                                </button>

                                <div class="modal fade modal-class-js allocate-modal-class" id="add-new-customer-modal--0" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-xl" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">{{ __('Allocate') }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">

                                                <div class="form-group row justify-content-center">
                                                    @php
                                                    $index = 0 ;
                                                    @endphp

                                                    {{-- start of fixed monthly repeating amount --}}
                                                    @php
                                                    $tableId = 'allocations';

                                                    $repeaterId = 'm_repeater--0';

                                                    @endphp
                                                    {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                                                    <x-tables.repeater-table :initialJs="false" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                                        <x-slot name="ths">
                                                            @foreach([
                                                            __('Customer')=>'th-main-color',
                                                            __('Contract Name')=>'th-main-color',
                                                            __('Contract Code')=>'th-main-color',
                                                            __('Contract Amount')=>'th-main-color',
                                                            __('Allocate Amount')=>'th-main-color',
                                                            ] as $title=>$classes)
                                                            <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                                            @endforeach
                                                        </x-slot>
                                                        <x-slot name="trs">
                                                            @php
                                                            $rows = [-1] ;
                                                            /// $rows = isset($model) ? $model->settlementAllocations :[-1] ;

                                                            @endphp
                                                            @foreach( count($rows) ? $rows : [-1] as $settlementAllocation)
                                                            @php
                                                            $fullPath = new \App\Models\SettlementAllocation;
                                                            if( !($settlementAllocation instanceof $fullPath) ){
                                                            unset($settlementAllocation);
                                                            }
                                                            @endphp
                                                            <tr @if($isRepeater) data-repeater-item @endif>

                                                                <td class="text-center">
                                                                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                                    <div class="">
                                                                        <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                                        </i>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <x-form.select :insideModalWithJs="true" :selectedValue="isset($settlementAllocation) && $settlementAllocation->partner_id ? $settlementAllocation->partner_id : ''" :options="formatOptionsForSelect($clientsWithContracts)" :add-new="false" class=" suppliers-or-customers-js custom-w-25" data-filter-type="{{ 'create' }}" :all="false" data-name="partner_id" name="partner_id"></x-form.select>
                                                                </td>

                                                                <td>
                                                                    <x-form.select :insideModalWithJs="true" data-current-selected="{{ isset($settlementAllocation) ? $settlementAllocation->id : '' }}" :selectedValue="isset($settlementAllocation) ? $settlementAllocation->contract_id : ''" :options="[]" :add-new="false" class=" contracts-js   custom-w-25" data-filter-type="{{ 'create' }}" :all="false" data-name="contract_id" name="contract_id"></x-form.select>
                                                                </td>

                                                                <td>
                                                                    <div class="kt-input-icon custom-w-20">
                                                                        <div class="input-group">
                                                                            <input disabled type="text" class="form-control contract-code " value="">
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="kt-input-icon custom-w-15">
                                                                        <div class="input-group">
                                                                            <input disabled type="text" class="form-control contract-amount" value="0">
                                                                        </div>
                                                                    </div>
                                                                </td>


                                                                <td>
                                                                    <div class="kt-input-icon custom-w-15">
                                                                        <div class="input-group">
                                                                            <input type="text" data-name="allocation_amount" name="allocation_amount" class="form-control allocation-amount-class" value="{{ isset($settlementAllocation) ? number_format($settlementAllocation->getAmount(),2): 0 }}">
                                                                        </div>
                                                                    </div>
                                                                </td>


                                                            </tr>



                                                            @endforeach

                                                        </x-slot>




                                                    </x-tables.repeater-table>
                                                    {{-- end of fixed monthly repeating amount --}}


                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                {{-- <button type="button" class="btn btn-primary ">{{ __('Save') }}</button> --}}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>


                </div>
            </div>

            <hr>
            @include('reports.moneyPayments.unapplied-contract')

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
                    <label class="label">{{ __('Unapplied Amount') }}

                        <span class="invoice-currency-span"></span>
                    </label>
                    <input readonly id="remaining-settlement-js" class="form-control" placeholder="{{ __('Unapplied Amount') }}" type="text" name="unapplied_amount" value="0">
                </div>
            </div>
        </div>
    </div>
	@else 
	
	@endif 
	
    @endif
    @include('user_comment',['model'=>$model??null])
    {{-- <x-submitting /> --}}
    <x-submitting-by-ajax :backTo="route('view.money.payment',['company'=>$company->id])" />

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
    $('#type').change(function() {

        const parent = $(this).closest('.js-section-parent');
        const branchId = parent.find('select#delivery_branch_id').val()
        type = $(this).val();


        $('.js-section-parent').addClass('hidden');
        if (type) {
            $('#' + type).removeClass('hidden');

        }


    });
    $('#type').trigger('change')

</script>
<script src="/custom/money-payment.js">

</script>

<script>
	function getBranchFromCurrency()
	{					const branchQuery = $('select#branch-id') ;
						const currentFromBranchId = branchQuery.attr('data-current-selected');
        	            const currencyName = $('select#receiving-currency-id').val();
					
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
								branchQuery.empty().append(branchOptions);
								branchQuery.trigger('change');
                            }
                        })
	}
	getBranchFromCurrency();
    $(document).on('change', 'select#receiving-currency-id', getBranchFromCurrency);
	
    $(document).on('change', 'select#branch-id', function() {
        const branchId = $('select#branch-id').val();
        const currencyName = $('select#receiving-currency-id').val();
        const modelId = $('#js-money-payment-id').val();
        const modelType = 'MoneyPayment';
        const balanceDate = $('.balance-date').val();
        if (branchId != '-1') {
            $.ajax({
                url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                , data: {
                    branchId
                    , currencyName
                    , modelId
                    , modelType
                    , balanceDate
                    //,additionalBalanceInEditMode
                }
                , success: function(res) {
                    const endBalance = res.end_balance;
                    $('.cash-balance-js').val(number_format(endBalance))
                }
            })
        }
    })

    $(function() {
        $('#type').trigger('change');
    });

    $(document).on('change', 'select.currency-class', function() {
        const invoiceCurrency = $('select#invoice-currency-id').val();
        const receivingCurrency = $('select#receiving-currency-id').val();
        const moneyType = $('select#type').val();

        $('.main-amount-class').closest('.closest-parent').find('.currency-span').html(" [ " + receivingCurrency + " ]")
        $('.amount-after-exchange-rate-class').closest('.closest-parent').find('.currency-span').html(" [ " + invoiceCurrency + " ]")

        const partnerType = $('select#partner_type').val();
        if (partnerType && partnerType != 'is_supplier') {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
            return;
        }


        if (invoiceCurrency != receivingCurrency && invoiceCurrency && receivingCurrency) {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')

        } else {
            // hide 

            $('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
        }

        if (receivingCurrency != invoiceCurrency) {
            $('#remaining-settlement-taking-js').closest('.closest-parent').removeClass('visibility-hidden');
            $('#remaining-settlement-taking-js').closest('.closest-parent').find('.taking-currency-span').html('[ ' + receivingCurrency + ' ]')
        } else {
            $('#remaining-settlement-taking-js').closest('.closest-parent').addClass('visibility-hidden');
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

</script>
<script>
    $(document).on('change', '.balance-date', function() {
        $('select.js-account-number').trigger('change');
        $('select#branch-id,select#receiving-currency-id').trigger('change');
    })

    $(document).on('change', '.js-account-number', function() {
        const parent = $(this).closest('.js-section-parent');
        const financialInstitutionId = parent.find('select.financial-institution-id').val()
        const accountNumber = $(this).val();
        const accountType = parent.find('select.js-update-account-number-based-on-account-type').val();
        const modelId = $('#js-money-payment-id').val();
        const modelType = 'MoneyPayment';
        const balanceDate = $('.balance-date').val();

        $.ajax({
            url: "{{ route('update.balance.and.net.balance.based.on.account.number',['company'=>$company->id]) }}"
            , data: {
                accountNumber
                , accountType
                , financialInstitutionId
                , modelType
                , modelId
                , balanceDate
            }
            , type: "get"
            , success: function(res) {
                if (res.balance_date) {
                    $(parent).find('.balance-date-js').html('[ ' + res.balance_date + ' ]')
                }
                if (res.net_balance_date) {
                    $(parent).find('.net-balance-date-js').html('[ ' + res.net_balance_date + ' ]')
                }
                $(parent).find('.net-balance-js').val(number_format(res.net_balance))
                $(parent).find('.balance-js').val(number_format(res.balance))

            }
        })
    })
    $(function() {
        $('select.currency-class').trigger('change')
        $('.recalculate-amount-class').trigger('change')
    })

</script>


<script>
    $(document).on('change', 'select.suppliers-or-customers-js', function() {
        const parent = $(this).closest('tr')
        const partnerId = parseInt($(this).val())
        const model = $('#model_type').val()
        let inEditMode = "{{ $inEditMode ?? 0 }}";

        $.ajax({
            url: "{{ route('get.contracts.for.customer.or.supplier',['company'=>$company->id]) }}"
            , data: {
                partnerId
                , model
                , inEditMode
            }
            , type: "get"
            , success: function(res) {
                let contracts = '';
                const currentSelected = $(parent).find('select.contracts-js').data('current-selected')
                for (var contract of res.contracts) {
                    contracts += `<option ${currentSelected ==contract.id ? 'selected' :'' } value="${contract.id}" data-code="${contract.code}" data-amount="${contract.amount}" data-currency="${contract.currency}" >${contract.name}</option>`;
                }
                parent.find('select.contracts-js').empty().append(contracts).trigger('change')
                parent.find('select.contracts-js').selectpicker("refresh")
            }
        })
    })
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency').toUpperCase()
        $(parent).find('.contract-code').val(code)
        $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)

    })




    $(document).on('change', '.ajax-update-contracts', function(e) {
        e.preventDefault()
        const supplierId = $('select.supplier-select').val()
        const currency = $('select.contract-currency').val()

        if (supplierId && currency) {
            $.ajax({
                url: "{{ route('get.contracts.for.supplier',['company'=>$company->id]) }}"
                , data: {
                    supplierId
                    , currency
                }
                , success: function(res) {
                    let options = '<option value="general-down">{{ __("General Down Payment") }}</option>';
                    let selectedContractId = $('#contracts').attr('data-current-selected')
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
