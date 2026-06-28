@extends('layouts.dashboard')
@section('css')
@php
use App\Models\CashExpense ;
use App\Models\SupplierInvoice;
$banks =[];
$selectedBanks = [];
@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .custom-contract-amount-css,
    .money-flow-dark .max-w-12 {
        max-width: initial !important;
        width: 12% !important;
        flex: initial !important;

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
{{ __('Cash Expense Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ?  route('update.cash.expense',['company'=>$company->id,'cashExpense'=>$model->id]) :route('store.cash.expense',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input id="js-money-payment-id" type="hidden" name="cash_expense_id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="cash_id" value="{{ isset($model) && $model->cashPayment ? $model->cashPayment->id : 0 }}">
            <input type="hidden" name="current_cheque_id" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->id : 0 }}">

            @if(isset($model))
            <input type="hidden" name="modelId" value="{{ $model->id }}">
            <input type="hidden" name="modelType" value="CashExpense">
            @endif

            {{-- <input type="hidden" id="ajax-invoice-item" data-single-model="{{ $singleModel ? 1 : 0 }}" value="{{ $singleModel ? $singleModel : 0 }}"> --}}
            @csrf
            @if(isset($model))
            @method('put')
            @endif
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Cash Expense')}}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2">
                            <label>{{__('Payment Date')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input type="text" name="payment_date" value="{{ isset($model) ? formatDateForDatePicker($model->getPaymentDate()) : '' }}" class="form-control balance-date is-date-css exchange-rate-date update-exchange-rate" readonly placeholder="Select date" id="kt_datepicker_max_date_is_today" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-2 mb-4">
                            <x-form.select :add-new-modal="true" :add-new-modal-modal-type="''" :add-new-modal-modal-name="'CashExpenseCategory'" :add-new-modal-modal-title="__('Expense Category')" :options="$cashExpenseCategories" :add-new="false" :label="__('Expense Category')" class="select2-select expense_category  " data-update-category-name-based-on-category data-filter-type="{{ 'create' }}" :all="false" name="expense_category_id" id="expense_category_id" :selected-value="isset($model) ? $model->getExpenseCategoryId() : 0"></x-form.select>
                        </div>


                        <div class="col-md-2 mb-4">
                            <x-form.select :add-new-modal="true" :add-new-modal-modal-type="''" :add-new-modal-modal-name="'CashExpenseCategoryName'" :add-new-modal-modal-title="__('Expense Name')" :previous-select-name-in-dB="'cash_expense_category_id'" :previous-select-must-be-selected="true" :previous-select-selector="'select.expense_category'" :previous-select-title="__('Expense Name')" :options="[]" :add-new="false" :label="__('Expense Name')" class="select2-select category_name  " data-filter-type="{{ 'create' }}" :all="false" name="cash_expense_category_name_id" id="{{'cash_expense_category_name_id' }}" :selected-value="isset($model) ? $model->getCashExpenseCategoryNameId() : 0" data-current-selected="{{ isset($model) ? $model->getCashExpenseCategoryNameId() : 0 }}"></x-form.select>
                        </div>

                        <div class="col-md-1">
                            <input type="hidden" class="to-currency" value="{{ $company->getMainFunctionalCurrency() }}">
                            <label>{{__('Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="receiving-currency-id" when-change-trigger-account-type-change name="currency" class="form-control
							
							currency-class
							receiving-currency-class
							current-invoice-currency update-exchange-rate
							 current-currency">
                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                        @foreach(getCurrencies() as $currencyId=>$currentName)
                                        @php
                                        $selected = isset($model) ? $model->getPaymentCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency() ;
                                        $selected = $selected ? 'selected':'';
                                        if($selected){
                                        }
                                        @endphp
                                        <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-3">
                            <label>{{__('Payment Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required name="type" id="type" class="form-control">
                                        <option value="" selected>{{__('Select')}}</option>
                                        <option @if(isset($model) && $model->isCashPayment() ) selected @endif value="{{ CashExpense::CASH_PAYMENT }}">{{__('Cash Payment')}}</option>
                                        <option @if(isset($model) && $model->isPayableCheque() ) selected @endif value="{{ CashExpense::PAYABLE_CHEQUE }}">{{__('Payable Cheques')}}</option>
                                        <option @if(isset($model) && $model->isOutgoingTransfer()) selected @endif value="{{ CashExpense::OUTGOING_TRANSFER }}">{{__('Outgoing Transfer / Bank Charges')}}</option>
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

                        <div class="col-md-2" id="bank-charges-id">
                            <label class="mb-2 d-inline-block">{{__('Is Bank Charges ?')}}

                                <div class="kt-input-icon mt-3">
                                    <div class="input-group date">
                                        <input style="max-height:25px;" type="checkbox" name="is_bank_charges" value="1" class="form-control checkbox " @if(isset($model) && $model->isOutgoingTransferBankCharges())
                                        checked
                                        @endif
                                        >
                                    </div>
                                </div>

                            </label>



                        </div>


                    </div>
                </div>
            </div>

            {{-- Cash In Safe Information--}}
            <div class="kt-portlet js-section-parent hidden" id="{{ CashExpense::CASH_PAYMENT}}">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label flex-1">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Cash Payment Information')}}
                        </h3>

                        <div class=" flex-1 d-flex justify-content-end pt-3">
                            <div class="col-md-3 mb-3">
                                <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                <div class="kt-input-icon">
                                    <input value="0" type="text" disabled class="form-control cash-balance-js" data-type="{{  CashExpense::PAYABLE_CHEQUE }}" placeholder="{{__('Account Balance')}}">
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
                                            <option value="-1">{{__('Select Branch')}}</option>
                                            @foreach($selectedBranches as $branchId=>$branchName)
                                            <option value="{{ $branchId }}" {{ isset($model) && $model->getCashPaymentBranchId() == $branchId ? 'selected' : '' }}>{{ $branchName }}</option>
                                            @endforeach
                                        </select>
                                        {{-- <button id="js-delivery-branch" class="btn btn-sm btn-primary">{{ __('Add New Branch') }}</button> --}}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label>{{__('Paid Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount() :0 }}" name="paid_amount[{{ CashExpense::CASH_PAYMENT}}]" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. CashExpense::CASH_PAYMENT.'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ CashExpense::CASH_PAYMENT }}" placeholder="{{__('Paid Amount')}}">
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
                            <div class="col-md-3 width-12 show-only-when-invoice-currency-not-equal-receiving-currency">
                                <label>{{__('Exchange Rate')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ CashExpense::CASH_PAYMENT }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ CashExpense::CASH_PAYMENT }}">
                                </div>
                            </div>

                            <div class="col-md-2 max-w-12 show-only-when-invoice-currency-not-equal-receiving-currency hidden">
                                <label>{{__('Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input readonly value="0" type="text" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ CashExpense::CASH_PAYMENT }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>





















            {{-- Cheques Information--}}
            <div class="kt-portlet js-section-parent hidden" id="{{ CashExpense::PAYABLE_CHEQUE }}">
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

                                        <select js-when-change-trigger-change-account-type data-financial-institution-id name="delivery_bank_id[{{ CashExpense::PAYABLE_CHEQUE  }}]" class="form-control financial-institution-id">
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
                                        <select name="account_type[{{ CashExpense::PAYABLE_CHEQUE }}]" class="form-control js-update-account-number-based-on-account-type">
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
                                        <select data-current-selected="{{ isset($model) ? $model->getPayableChequeAccountNumber() : 0 }}" name="account_number[{{ CashExpense::PAYABLE_CHEQUE }}]" class="form-control js-account-number">
                                            <option value="" selected>{{__('Select')}}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label>{{__('Cheque Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input data-max-cheque-value="0" value="{{ isset($model) ? $model->getPaidAmount() : 0 }}" placeholder="{{ __('Please insert the cheque amount') }}" type="text" name="paid_amount[{{ CashExpense::PAYABLE_CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed {{ 'js-'. CashExpense::PAYABLE_CHEQUE .'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ CashExpense::PAYABLE_CHEQUE }}">
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
                                    <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ CashExpense::PAYABLE_CHEQUE }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ CashExpense::PAYABLE_CHEQUE }}">
                                </div>
                            </div>

                            <div class="col-md-1  show-only-when-invoice-currency-not-equal-receiving-currency hidden">
                                <label>{{__('Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input readonly value="{{ 0 }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ CashExpense::PAYABLE_CHEQUE }}">
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
    <div class="kt-portlet js-section-parent hidden" id="{{ CashExpense::OUTGOING_TRANSFER }}">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label flex-1">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{__('Outgoing Transfer Information')}}
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
                    <div class="col-md-5 width-40">
                        <label> {!! __('Payment <br> Bank') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">

                                <select js-when-change-trigger-change-account-type data-financial-institution-id name="delivery_bank_id[{{ CashExpense::OUTGOING_TRANSFER }}]" class="form-control financial-institution-id">
                                    @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                    <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getOutgoingTransferDeliveryBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                    @endforeach
                                </select>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 max-w-15">
                        <label> {!! __('Outgoing <br> Transfer Amount') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getPaidAmount():0 }}" name="paid_amount[{{ CashExpense::OUTGOING_TRANSFER }}]" class="form-control greater-than-or-equal-zero-allowed {{ 'js-'. CashExpense::OUTGOING_TRANSFER .'-paid-amount' }}  main-amount-class recalculate-amount-class" data-type="{{ CashExpense::OUTGOING_TRANSFER }}" placeholder="{{__('Insert Amount')}}">
                        </div>
                    </div>



                    <div class="col-md-3">
                        <label> {!! __('Account <br> Type') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <div class="input-group date">
                                <select name="account_type[{{ CashExpense::OUTGOING_TRANSFER }}]" class="form-control js-update-account-number-based-on-account-type">
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
                                <select data-current-selected="{{ isset($model) ? $model->getOutgoingTransferAccountNumber() : 0 }}" name="account_number[{{ CashExpense::OUTGOING_TRANSFER }}]" class="form-control js-account-number">
                                    <option value="" selected>{{__('Select')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-1 max-w-6 show-only-when-invoice-currency-not-equal-receiving-currency">
                        <label>{!! __('Exchange <br> Rate') !!} @include('star')</label>
                        <div class="kt-input-icon">
                            <input value="{{ isset($model) ? $model->getExchangeRate() : 1}}" placeholder="{{ __('Exchange Rate') }}" type="text" name="exchange_rate[{{ CashExpense::OUTGOING_TRANSFER }}]" class="form-control only-greater-than-or-equal-zero-allowed exchange-rate-class recalculate-amount-class" data-type="{{ CashExpense::OUTGOING_TRANSFER }}">
                        </div>
                    </div>

                    <div class="col-md-1 mt-4 show-only-when-invoice-currency-not-equal-receiving-currency hidden">
                        <label>{{__('Amount')}} @include('star')</label>
                        <div class="kt-input-icon">
                            <input readonly value="{{ 0 }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed amount-after-exchange-rate-class" data-type="{{ CashExpense::OUTGOING_TRANSFER }}">
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </div>






    {{-- Allocation Information "Commen Card" --}}
    @include('reports.cashExpenses._allocate')
    @include('user_comment',['model'=>$model??null])
    <x-submitting-by-ajax :backTo="route('view.cash.expense',['company'=>$company->id])" />

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
        const modelType = 'CashExpense';
        const balanceDate = $('.balance-date').val();
        if (branchId != '-1') {
            $.ajax({
                url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                , data: {
                    branchId
                    , currencyName
                    , modelType
                    , modelId
                    , balanceDate
                }
                , success: function(res) {
                    const endBalance = res.end_balance;
                    $('.cash-balance-js').val(number_format(endBalance))
                }
            })
        }
    })

    $('#type').change(function() {
        selected = $(this).val();
        if (selected == 'outgoing-transfer') {
            $('#bank-charges-id').show()
        } else {
            $('#bank-charges-id').hide()

        }
        $('.js-section-parent').addClass('hidden');
        if (selected) {
            $('#' + selected).removeClass('hidden');
        }


    });
    $('#type').trigger('change')

</script>
<script src="/custom/money-payment.js">

</script>

<script>
    $(document).on('change', '.settlement-amount-class', function() {

    })
    $(function() {
        $('#type').trigger('change');
    })


    $(document).on('change', 'select.currency-class', function() {
        const invoiceCurrency = $('select#invoice-currency-id').val();
        const receivingCurrency = $('select#receiving-currency-id').val();
        const moneyType = $('select#type').val();
        if (invoiceCurrency != receivingCurrency && receivingCurrency && invoiceCurrency) {
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
        const amountAfterExchangeRate = amount * exchangeRate;
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
        const modelType = 'CashExpense';
        const balanceDate = $('.balance-date').val();
        $.ajax({
            url: "{{ route('update.balance.and.net.balance.based.on.account.number',['company'=>$company->id]) }}"
            , data: {
                accountNumber
                , accountType
                , financialInstitutionId
                , modelId
                , modelType
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

    $(document).on('click', '.trigger-add-new-modal', function() {
        var additionalName = '';
        if ($(this).attr('data-previous-must-be-opened')) {
            const previosSelectorQuery = $(this).attr('data-previous-select-selector');
            const previousSelectorValue = $(previosSelectorQuery).val()
            const previousSelectorTitle = $(this).attr('data-previous-select-title');
            if (!previousSelectorValue) {
                Swal.fire({
                    text: "{{ __('Please Select') }}" + ' ' + previousSelectorTitle
                    , icon: 'warning'
                })
                return;
            }
            const previousSelectorVal = $(previosSelectorQuery).val();
            const previousSelectorHtml = $(previosSelectorQuery).find('option[value="' + previousSelectorVal + '"]').html();
            additionalName = "{{' '. __('For')  }}  [" + previousSelectorHtml + ' ]'
        }
        const parent = $(this).closest('label').parent();
        parent.find('select');
        const type = $(this).attr('data-modal-title')
        const name = $(this).attr('data-modal-name')
        $('.modal-title-add-new-modal-' + name).html("{{ __('Add New ') }}" + type + additionalName);
        parent.find('.modal').modal('show')
    })
    $(document).on('click', '.store-new-add-modal', function() {
        const that = $(this);
        $(this).attr('disabled', true);
        const modalName = $(this).attr('data-modal-name');
        const modalType = $(this).attr('data-modal-type');
        const modal = $(this).closest('.modal');
        const value = modal.find('input.name-class-js').val();
        const previousSelectorSelector = $(this).attr('data-previous-select-selector');
        const previousSelectorValue = previousSelectorSelector ? $(previousSelectorSelector).val() : null;
        const previousSelectorNameInDb = $(this).attr('data-previous-select-name-in-db');

        $.ajax({
            url: "{{ route('admin.store.new.modal',['company'=>$company->id ?? 0  ]) }}"
            , data: {
                "_token": "{{ csrf_token() }}"
                , "modalName": modalName
                , "modalType": modalType
                , "value": value
                , "previousSelectorNameInDb": previousSelectorNameInDb
                , "previousSelectorValue": previousSelectorValue
            }
            , type: "POST"
            , success: function(response) {
                $(that).attr('disabled', false);
                modal.find('input').val('');
                $('.modal').modal('hide')
                if (response.status) {
                    const allSelect = $('select[data-modal-name="' + modalName + '"][data-modal-type="' + modalType + '"]');
                    const allSelectLength = allSelect.length;
                    allSelect.each(function(index, select) {
                        var isSelected = '';
                        if (index == (allSelectLength - 1)) {
                            isSelected = 'selected';
                        }
                        $(select).append(`<option ` + isSelected + ` value="` + response.id + `">` + response.value + `</option>`).selectpicker('refresh').trigger('change')
                    })

                }
            }
            , error: function(response) {}
        });
    })



    $(function() {
        $('select.currency-class').trigger('change')
        $('.recalculate-amount-class').trigger('change')
    })

</script>

<script>
    $(document).on('change', '[data-update-category-name-based-on-category]', function(e) {
        const expenseCategoryId = $('select.expense_category').val()
        if (!expenseCategoryId) {
            return;
        }
        $.ajax({
            url: "{{route('update.expense.category.name.based.on.category',['company'=>$company->id])}}"
            , data: {
                expenseCategoryId
            , }
            , type: "GET"
            , success: function(res) {
                var options = '';
                var currentSelectedId = $('select.category_name').attr('data-current-selected')

                for (var categoryName in res.categoryNames) {
                    var categoryNameId = res.categoryNames[categoryName];
                    options += `<option ${currentSelectedId == categoryNameId ? 'selected' : '' } value="${categoryNameId}"> ${categoryName}  </option> `;
                }
                $('select.category_name').empty().append(options).selectpicker("refresh");
                $('select.category_name').trigger('change')
            }
        })
    })
    $('[data-update-category-name-based-on-category]').trigger('change')

</script>

<script>
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency') ? $(this).find('option:selected').data('currency').toUpperCase() : null;
        if (currency) {
            $(parent).find('.contract-code').val(code)
            $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)
        }
    })
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
            }
        })
    })
    $(function() {
        $('select.suppliers-or-customers-js').trigger('change')
    })

</script>
@endsection
