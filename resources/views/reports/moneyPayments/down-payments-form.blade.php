@extends('layouts.dashboard')
@section('css')
@php
use App\Models\MoneyPayment ;
use App\Models\SupplierInvoice;
use App\Models\Partner;
$banks =[];
$selectedBanks = [];
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
{{ __('Money Payment Form') }}
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
<form method="post" action="{{ isset($model) ?  route('update.money.payment',['company'=>$company->id,'moneyPayment'=>$model->id]) :route('store.money.payment',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
    <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">


    <input type="hidden" name="current_cheque_id" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->id : 0 }}">
	 <input type="hidden" name="is_down_payment" id="is-down-payment-id" value="1">
    <input id="js-money-payment-id" type="hidden" name="money_payment_id" value="{{ isset($model) ? $model->id : 0 }}">
	  <input type="hidden" name="cash_id" value="{{ isset($model) && $model->cashPayment ? $model->cashPayment->id : 0 }}">
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
        {{-- <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{__('Money Payment [ DownPayment ]')}}
                </h3>
            </div>
        </div> --}}
        <div class="kt-portlet__body">
            <div class="form-group row">
            
			
			   <div class="col-md-2 ">
                    <label>{{__('Payment Date')}}</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <input type="text" name="delivery_date" value="{{ isset($model) ? formatDateForDatePicker($model->getDeliveryDate()) : '' }}" class="form-control is-date-css" readonly placeholder="Select date" id="kt_datepicker_max_date_is_today" />
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="la la-calendar-check-o"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
				
					<div class="col-md-2 ">
                    <label>{{__('Down Payment Type')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select required name="down_payment_type" id="down_payment_type" class="form-control">
                                <option @if(isset($model) && $model->isDownPaymentOverContract() ) selected @endif value="{{ MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT }}">{{__('Contract Down Payment')}}</option>
                                <option @if(isset($model) && $model->isGeneralDownPayment() ) selected @endif value="{{ MoneyPayment::DOWN_PAYMENT_GENERAL }}">{{__('General Down Payment')}}</option>
								{{-- <option @if(isset($model) && $model->isSettlementOfOpeningBalance() ) selected @endif value="{{ MoneyPayment::SETTLEMENT_OF_OPENING_BALANCE }}">{{__('Settlement Of Opening Balance')}}</option> --}}
                            </select>
                        </div>
                    </div>

                </div>

				
				
                <div class="col-md-2" id="invoice-currency-div">
                    <label>{{__('Contract Currency')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select id="invoice-currency-id" name="currency" class="form-control 
							currency-class
							currency-for-contracts
							invoice-currency-class
							ajax-get-contracts-for-supplier  ajax-get-purchases-orders-for-contract
							current-invoice-currency
							 ajax-get-invoice-numbers
							 
							 ">

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

                <div class="col-md-4">

                    <label>{{__('Supplier Name')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select data-current-selected="{{ isset($model) ? $model->getPartnerId() : '' }}" data-live-search="true" data-actions-box="true" id="supplier_name" name="supplier_id" class="form-control select2-select  
								ajax-get-invoice-numbers
					
									 ajax-get-contracts-for-supplier ajax-get-purchases-orders-for-contract">
                                    <option value="" selected>{{__('Select')}}</option>
                                    {{-- {{  }} --}}
                                    @foreach(Partner::getSuppliersForCompany($company->id) as $supplierId => $supplierName)
                                    <option @if($singleModel) selected @endif @if(isset($model) && $model->getSupplierName() == $supplierName ) selected @endif value="{{ $supplierId }}">{{$supplierName}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div>


                <div class="col-md-2 ">
                    <label>{{__('Payment Currency')}} @include('star')</label>
                    <div class="kt-input-icon">
                        <div class="input-group date">
                            <select id="receiving-currency-id" when-change-trigger-account-type-change name="payment_currency" class="form-control 
							current-currency
							currency-class
							receiving-currency-class
							
							ajax-get-contracts-for-supplier  ajax-get-purchases-orders-for-contract ajax-get-invoice-numbers
					
							">

                                @foreach(isset($currencies) ? $currencies : getBanksCurrencies () as $currencyId=>$currentName)
                                @php
                                $selected = isset($model) ? $model->getPaymentCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency() ;
                                $selected = $selected ? 'selected':'';
                                @endphp
                                <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>


			
                <div class="col-md-4 contract-id-div mt-4">
                    <label>{{__('Contract Name')}}
                        @include('star')
                    </label>
                    <div class="kt-input-icon">
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select data-current-selected="{{ isset($model) ? $model->getContractId() : 0 }}" id="contract-id" name="contract_id" class="form-control down-payment-contract-class ajax-get-invoice-numbers ajax-get-purchases-orders-for-contract">
                                    <option value="" selected>{{__('Select')}}</option>
                                    @foreach($contracts as $index => $contract)
                                    <option @if(isset($model) && $model->getContractId() == $contract->id ) selected @endif value="{{ $contract->id }}">{{$contract->getName()}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

				<div class="col-md-3 mt-4">
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




                    {{-- <div class="modal fade" id="js-choose-delivery-branch-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Add Branch') }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <input type="text" id="js-delivery-branch-names" class="form-control">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                    <button id="js-append-delivery-branch-name-if-not-exist" type="button" class="btn btn-primary">{{ __('Save') }}</button>
                                </div>
                            </div>
                        </div>
                    </div> --}}




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
                    <div class="col-md-5 width-45 ">
                        <label>{{__('Delivery Branch')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select id="branch-id" name="delivery_branch_id" class="form-control">
                                    <option value="-1">{{__('Select Branch')}}</option>
                                    @foreach($selectedBranches as $branchId=>$branchName)
                                    <option value="{{ $branchId }}" {{ isset($model) && $model->getCashPaymentBranchId() == $branchId ? 'selected' : '' }}>{{ $branchName }}</option>
                                    @endforeach
                                </select>
                                {{-- <button id="js-delivery-branch" class="btn btn-sm btn-primary">{{ __('Add New Branch') }}</button> --}}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 closest-parent">
                        <label>{{__('Received Amount')}} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount() :0 }}" name="paid_amount[{{ MoneyPayment::CASH_PAYMENT}}]" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. MoneyPayment::CASH_PAYMENT.'-received-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::CASH_PAYMENT }}" placeholder="{{__('Received Amount')}}">
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
                    <div class="col-md-3 width-12 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency">
                        <label>{{__('Exchange Rate')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::CASH_PAYMENT }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::CASH_PAYMENT }}">
                        </div>
                    </div>

                    <div class="col-md-3 mt-4 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                        <label>{{__('Amount In Contract Currency')}} <span class="currency-span"></span> @include('star')</label>
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
                    <div class="col-md-5 width-45">
                        <label>{{__('Select Payment Bank')}} @include('star')</label>
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

                    <div class="col-md-2 width-12">
                        <label>{{__('Account Type')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select name="account_type[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control js-update-account-number-based-on-account-type">
                                    <option value="" selected>{{__('Select')}}</option>
                                    @foreach($accountTypes as $index => $accountType)
                                    <option value="{{ $accountType->id }}" @if(isset($model) && $model->getOutgoingTransferAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 width-12">
                        <label>{{__('Account Number')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select data-current-selected="{{ isset($model) ? $model->getOutgoingTransferAccountNumber() : 0 }}" name="account_number[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control js-account-number">
                                    <option value="" selected>{{__('Select')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2 width-12 closest-parent">
                        <label>{{__('Cheque Amount')}} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-max-cheque-value="0" value="{{ isset($model) ? $model->getPaidAmount() : 0 }}" placeholder="{{ __('Please insert the cheque amount') }}" type="text" name="paid_amount[{{ MoneyPayment::PAYABLE_CHEQUE }}]  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. MoneyPayment::PAYABLE_CHEQUE .'-paid-amount' }}">
                        </div>
                    </div>



                    <div class="col-md-2 width-12">
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


                    <div class="col-md-2 width-12 mt-4">
                        <label>{{__('Cheque Number')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input type="text" name="cheque_number" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->getChequeNumber() : 0 }}" class="form-control" placeholder="{{__('Cheque Number')}}">
                        </div>
                    </div>

                    <div class="col-md-2 width-12 mt-4 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency">
                        <label>{{__('Exchange Rate')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                        </div>
                    </div>

                    <div class="col-md-3 mt-4 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                        <label>{{__('Amount In Contract Currency')}} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyPayment::PAYABLE_CHEQUE }}]" class="form-control  amount-after-exchange-rate-class" data-type="{{ MoneyPayment::PAYABLE_CHEQUE }}">
                        </div>
                    </div>



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
                    <div class="col-md-5 width-45">
                        <label>{{__('Select Payment Bank')}} @include('star')</label>
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
                    <div class="col-md-2 closest-parent">
                        <label>{{__('Outgoing Transfer Amount')}} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount():0 }}" name="paid_amount[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control greater-than-or-equal-zero-allowed {{ 'js-'. MoneyPayment::OUTGOING_TRANSFER .'-received-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}" placeholder="{{__('Insert Amount')}}">
                        </div>
                    </div>



                    <div class="col-md-2 width-12">
                        <label>{{__('Account Type')}} @include('star')</label>
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
                        <label>{{__('Account Number')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select data-current-selected="{{ isset($model) ? $model->getOutgoingTransferAccountNumber() : 0 }}" name="account_number[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control js-account-number">
                                    <option value="" selected>{{__('Select')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-1 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency">
                        <label>{{__('Exchange Rate')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}">
                        </div>
                    </div>

                    <div class="col-md-3 mt-4 exchange-rate-div show-only-when-invoice-currency-not-equal-receiving-currency hidden closest-parent">
                        <label>{{__('Amount In Contract Currency')}} <span class="currency-span"></span> @include('star')</label>
                        <div class="kt-input-icon">
                            <input readonly value="{{ 0 }}" type="text" name="amount_in_invoice_currency[{{ MoneyPayment::OUTGOING_TRANSFER }}]" class="form-control  amount-after-exchange-rate-class" data-type="{{ MoneyPayment::OUTGOING_TRANSFER }}">
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </div>






    {{-- Settlement Information "Commen Card" --}}
    <div class="kt-portlet down-payment-id">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{__('Settlement Information')}}
                </h3>
            </div>
        </div>
        <div class="kt-portlet__body">

            <div class="js-append-down-payment-to">
                <div class="col-md-12 js-duplicate-node">

                </div>
            </div>




            <div class="js-down-payment-template hidden">
                <div class="col-md-12 js-duplicate-node">
                    <div class=" kt-margin-b-10 border-class">
                        @include('reports.moneyPayments._down-payments-purchase-orders')
                    </div>
                </div>
            </div>


        </div>
    </div>






 @include('user_comment',['model'=>$model??null])

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

    $(document).on('change', 'select.currency-class,#down_payment_type,select#type', function() {
        const invoiceCurrency = $('select#invoice-currency-id').val();
        const receivingCurrency = $('select#receiving-currency-id').val();
        const moneyType = $('select#type').val();
		const isGeneralDownPayment = $('#down_payment_type').val() == "{{ MoneyPayment::DOWN_PAYMENT_GENERAL }}";
        $('.main-amount-class').closest('.closest-parent').find('.currency-span').html(" [ " + receivingCurrency + " ]")
        $('.amount-after-exchange-rate-class').closest('.closest-parent').find('.currency-span').html(" [ " + invoiceCurrency + " ]")


        if (invoiceCurrency != receivingCurrency && invoiceCurrency && receivingCurrency) {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')
			if(isGeneralDownPayment){
				$('.exchange-rate-div').addClass('hidden')
			}

        } else {
            // hide 
            $('.show-only-when-invoice-currency-not-equal-receiving-currency,.exchange-rate-div').addClass('hidden')
        }
		
			if(receivingCurrency != invoiceCurrency){
		$('#remaining-settlement-taking-js').closest('.closest-parent').removeClass('visibility-hidden');	
		$('#remaining-settlement-taking-js').closest('.closest-parent').find('.taking-currency-span').html('[ ' +  receivingCurrency +' ]')
		}else{
		$('#remaining-settlement-taking-js').closest('.closest-parent').addClass('visibility-hidden');	
		}
		

    })
    $(document).on('change', '.recalculate-amount-class,select#type', function() {
        const moneyType = $('select#type').val();
        const amount = number_unformat($('.main-amount-class[data-type="' + moneyType + '"]').val());
        const exchangeRate = number_unformat($('.exchange-rate-class[data-type="' + moneyType + '"]').val());
        const amountAfterExchangeRate = roundToTwo(amount / exchangeRate,2);
        $('.amount-after-exchange-rate-class[data-type="' + moneyType + '"]').val(amountAfterExchangeRate).trigger('change')
		console.log('recalculate amount class',amountAfterExchangeRate);
        $('.js-settlement-amount:eq(0)').trigger('change')
    })
    $(document).on('change', 'select[when-change-trigger-account-type-change]', function(e) {
        $('select.js-update-account-number-based-on-account-type').trigger('change')
    });

</script>
<script src="/custom/money-payment.js">

</script>

<script>
   
		$(document).on('change','.balance-date',function(){
				$('select.js-account-number').trigger('change');
				$('select#receiving-currency-id').trigger('change');
			})
			
			 $(document).on('change', 'select#branch-id', function() {
        const branchId = $('select#branch-id').val();
        const currencyName = $('select#receiving-currency-id').val();
		const modelId = $('#js-money-payment-id').val();
		const modelType = 'MoneyPayment';
		const balanceDate = $('.balance-date').val();
		// const editType = $('#type').val();
		//let additionalBalanceInEditMode = $('#additional-balance-amount-'+editType).val();
		//additionalBalanceInEditMode = additionalBalanceInEditMode == undefined ? 0 : additionalBalanceInEditMode;
        if (branchId != '-1') {
            $.ajax({
                url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                , data: {
                    branchId
                    , currencyName,
					modelId,
					modelType,
					balanceDate
					//,additionalBalanceInEditMode
                }
                , success: function(res) {
                    const endBalance = res.end_balance;
                    $('.cash-balance-js').val(number_format(endBalance))
                }
            })
        }
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
                , financialInstitutionId,
				modelType,
				modelId,
				balanceDate
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
        $('#type').trigger('change');
    })

</script>


<script>
    $(document).on('change', '.ajax-get-contracts-for-supplier', function(e) {
        e.preventDefault()
        const supplierId = $('#supplier_name').val()
        const currency = $('select.currency-for-contracts').val()
        const contractId = $('select#contract-id').attr('data-current-selected');
        if (supplierId && currency) {
            $.ajax({
                url: "{{ route('get.contracts.for.supplier',['company'=>$company->id]) }}"
                , data: {
                    supplierId
                    , currency
                }
                , success: function(res) {
                    let options = '';
                    for (id in res.contracts) {
                        options += `<option value="${id}" ${contractId == id ? 'selected' : ''} >${res.contracts[id]}</option>`
                    }
                    $('select#contract-id').empty().append(options)
                    $('select#contract-id').trigger('change')
                }
            })
        }else{
			 $('select#contract-id').empty().append("")
                    $('select#contract-id').trigger('change')
		}
    })

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
	
	</script>
	
	
<script>
    $(function() {
        $('select#supplier_name').trigger('change')
        //$('select.currency-class').trigger('change')
        //$('.recalculate-amount-class').trigger('change')
    })
    
	
$(document).on('change','#down_payment_type',function(){
	const val = $(this).val();
	var currentSelected = $('select#supplier_name').attr('data-current-selected');
	if(val == 'settlement-of-opening-balance'){
			$.ajax({
				data:{
					type:val
				},
			url:"{{ route('get.suppliers.of.opening-balance',['company'=>$company->id]) }}",
			type:'get'
		}).then(function(res){
			let suppliersOptions = '';
			for (var supplierName in res.invoices){
				var customerId = res.invoices[supplierName];
					var selected = currentSelected ==customerId  ?'selected':'' ;
				suppliersOptions += ` <option value="${customerId}" ${selected}>${supplierName}</option> `
			}
			$('select#supplier_name').selectpicker('destroy');
			$('select#supplier_name').empty().append(suppliersOptions)
			$('select#supplier_name').selectpicker("refresh")
		})
		}else{
			$.ajax({
				data:{
					type:val
				},
			url:"{{ route('get.suppliers.of.opening-balance',['company'=>$company->id]) }}",
			type:'get'
		}).then(function(res){
			let suppliersOptions = '';
			for (var supplierName in res.invoices){
				var customerId = res.invoices[supplierName];
					var selected = currentSelected ==customerId  ?'selected':'' ;
				suppliersOptions += ` <option value="${customerId}" ${selected}>${supplierName}</option> `
			}
			$('select#supplier_name').selectpicker('destroy');
			$('select#supplier_name').empty().append(suppliersOptions)
			$('select#supplier_name').selectpicker("refresh")
		})
		}
		
	if(val != 'over_contract'){
		$('.contract-id-div').hide();
		$('.down-payment-id').hide();
		$('#settlement-card-id').hide();
		       $('#invoice-currency-div').hide();
	}else{
		$('.contract-id-div').show();
			$('.down-payment-id').show();
		$('#settlement-card-id').show();
		       $('#invoice-currency-div').show();
	}
})
$('#down_payment_type').trigger('change')


</script>
@endsection
