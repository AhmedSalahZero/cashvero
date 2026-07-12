@php
use App\Models\MoneyPayment ;
use App\Models\MoneyReceived ;
@endphp
@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
.js-parent-to-table{
	min-height:500px !important; 
}
    .bootstrap-select .dropdown-menu {
        max-height: 500px !important;
        /* Adjust height as needed */
        overflow-y: auto !important;
        /* Enable vertical scrollbar */
        overflow-x: hidden !important;
        /* Prevent horizontal scrollbar */
    }

    .css-fix-plus-direction {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-direction: row-reverse;
    }

    .kt-portlet .kt-portlet__head {
        border-bottom-color: #CCE2FD !important;
    }

    .customer-name-width {
        max-width: 500px !important;
        width: 500px !important;
        min-width: 500px !important;
    }

    .account-number-width {
        max-width: 250px !important;
        width: 250px !important;
        min-width: 250px !important;
    }

    .account-type-width {
        max-width: 400px !important;
        width: 400px !important;
        min-width: 400px !important;
    }

    .drawee-bank-width {
        max-width: 665px !important;
        width: 665px !important;
        min-width: 665px !important;
    }

    .width-8 {
        max-width: 100px !important;
        width: 100px !important;
        min-width: 100px !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: 150px !important;
        width: 150px !important;
        min-width: 150px !important;
        flex: initial !important;
    }



    .width-15 {
        max-width: 170px !important;
        width: 170px !important;
        min-width: 170px !important;
        flex: initial !important;
    }

    thead tr {
        background-color: #CCE2FD !important;

    }

    thead tr th {
        border: 1px solid white !important;
    }

    label {
        white-space: nowrap !important
    }

    [class*="col"] {
        margin-bottom: 1.5rem !important;
    }

    label {
        text-align: left !important;
    }





    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style),
    input.form-control:not(.is-date-css)[readonly] {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

    .drawee-bank-add-icon-row {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 0.25rem;
        min-height: 1rem;
    }

    .drawee-bank-add-icon-row .fa-plus {
        color: #5867dd;
        font-size: 0.95rem;
    }

</style>

<style>
    .show-class-js.js-parent-to-table {
        overflow: scroll !important;
    }

</style>
<style>

</style>
@endsection
@section('sub-header')
{{ __('Opening Balance') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ? route('opening-balance.update',['company'=>$company->id,'opening_balance'=>$model->id]) : route('opening-balance.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
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
                                    <x-sectionTitle :title="__('Opening Balance')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->

                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Opening Balance Date')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">
                                <div class="col-md-4 ">
                                    <x-form.date :type="'text'" :classes="'datepicker-input'" :default-value="formatDateForDatePicker(isset($model)  ? $model->getDate() : null)" :model="$model??null" :label="__('Opening Balance Date')" :type="'text'" :placeholder="__('')" :name="'date'" :required="true"></x-form.date>
                                </div>


                            </div>
                        </div>
                    </div>


                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Cash In Safe Opening Balance')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp


                                {{-- start of fixed monthly repeating amount --}}
                                @php
                                $tableId = MoneyReceived::CASH_IN_SAFE;
                                $repeaterId = 'm_repeater_6';
                                @endphp
                                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                                <x-tables.repeater-table :initEmpty="!isset($model) || !$model->cashInSafeStatements->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Safe')=>'col-md-1',
                                        __('Amount')=>'col-md-1',
                                        __('Currency')=>'col-md-1',
                                        __('Exchange <br> Rate')=>'col-md-1'
                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->cashInSafeStatements :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $cashInSafeStatement)
                                        @php
                                        if( !($cashInSafeStatement instanceof \App\Models\CashInSafeStatement) ){
                                        unset($cashInSafeStatement);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>
                                            <td class="text-center">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <select name="received_branch_id" class="form-control cash-in-safe-branch-select">
                                                        @foreach($selectedBranches as $branchId => $branchName )
                                                        <option value="{{ $branchId }}" data-currency="{{ $branchCurrencies[$branchId] ?? '' }}" @if(isset($cashInSafeStatement) && $cashInSafeStatement->getBranchId() == $branchId ) selected @endif > {{ $branchName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        {{-- <input type="hidden" name="" value="{{ $company->getHeadOfficeId() }}"> --}}

                                                        <input name="received_amount" type="text" class="form-control " value="{{ number_format(isset($cashInSafeStatement) ? $cashInSafeStatement->getDebitAmount() : old('amount',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="hidden" name="id" value="{{ isset($cashInSafeStatement) ? $cashInSafeStatement->id : 0 }}">





                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency cash-in-safe-currency-select readonly-style-select" js-when-change-trigger-change-account-type>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($cashInSafeStatement) && $cashInSafeStatement->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" step="4" type="text" class="form-control " value="{{ isset($cashInSafeStatement) ? $cashInSafeStatement->getExchangeRate() : old('exchange_rate',1) }}">
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
                    </div>










                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Cheque In Safe Opening Balance')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp



                                {{-- start of fixed monthly repeating amount --}}
                                @php
                                $tableId = MoneyReceived::CHEQUE;
                                $repeaterId = 'm_repeater_7';

                                @endphp
                                <div class="modal fade " data-type="" id="js-choose-bank-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
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
                                                <button type="button" class="btn btn-primary js-append-bank-name-if-not-exist-in-repeater">{{ __('Save') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <x-tables.repeater-table :initEmpty="!isset($model->chequeInSafe) || !$model->chequeInSafe->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js modal-parent--js is-customer-class'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Customer <br> Name')=>'customer-name-width',
                                        __('Currency')=>'width-8',
                                        __('Due <br> Date')=>'width-12',
                                        __('Drawee <br> Bank')=>'drawee-bank-width',
                                        __('Amount')=>'col-md-1',
                                        __('Cheque <br> Number')=>'col-md-1',
                                        __('Exchange <br> Rate')=>'col-md-1'

                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->chequeInSafe :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $chequeInSafe)
                                        @php
                                        if( !($chequeInSafe instanceof \App\Models\MoneyReceived) ){
                                        unset($chequeInSafe);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>
                                            <td class="text-center">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>


                                            <input type="hidden" name="id" value="{{ isset($chequeInSafe) ? $chequeInSafe->id : 0 }}">
                                            <td>
                                                <div class="input-group css-fix-plus-direction name-class">
                                                    <x-form.select :add-new-modal="true" :add-new-modal-modal-type="'Customer'" :add-new-modal-modal-name="'Partner'" :add-new-modal-modal-title="__('Customer Name')" :options="$customersFormatted" :add-new="false" :label="' '" class="customer_name_class repeater-select" data-filter-type="{{ 'create' }}" :all="false" name="customer_id" :selected-value="isset($chequeInSafe) ? $chequeInSafe->getPartnerId() : 0"></x-form.select>
                                                </div>

                                            </td>

                                            <td>

                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency width-8" js-when-change-trigger-change-account-type>
                                                        {{-- <option selected>{{__('Select')}}</option> --}}
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($chequeInSafe) && $chequeInSafe->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>

                                            <td>
                                                <x-calendar :onlyMonth="false" :showLabel="false" :value="isset($chequeInSafe) ?  formatDateForDatePicker($chequeInSafe->getChequeDueDate()) : formatDateForDatePicker(now())" :label="__('Due Date')" :id="'due_date'" :class="'width-12'" name="due_date" :classes="'width-12'"></x-calendar>

                                            </td>

                                            <td>
                                                <div class="kt-input-icon drawee-bank-width">
                                                    <div class="drawee-bank-add-icon-row">
                                                        <i title="{{ __('Add New Bank') }}" class="fa fa-plus cursor-pointer block ml-auto js-drawee-bank-class"></i>
                                                    </div>
                                                    <div class="input-group date">

                                                        <select data-live-search="true" data-actions-box="true" name="drawee_bank_id" class="form-control repeater-select select2-select	drawee-bank-class">
                                                            @foreach($selectedBanks as $bankId=>$bankName)
                                                            <option value="{{ $bankId }}" {{ isset($chequeInSafe) && $chequeInSafe->cheque && $chequeInSafe->cheque->getDraweeBankId() == $bankId ? 'selected':'' }}>{{ $bankName }}</option>
                                                            @endforeach
                                                        </select>



                                                    </div>
                                                </div>
                                            </td>


                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="received_amount" type="text" class="form-control " value="{{ number_format(isset($chequeInSafe) ? $chequeInSafe->getReceivedAmount() : old('amount',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="cheque_number" type="text" class="form-control " value="{{ isset($chequeInSafe) ? $chequeInSafe->getChequeNumber() : old('cheque_number',0) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>

                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" type="text" class="form-control " value="{{ isset($chequeInSafe) ? $chequeInSafe->getExchangeRate() : old('exchange_rate',1) }}">
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
                    </div>












                    <div class="kt-portlet" >

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Cheque Under Collection Opening Balance')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp



                                {{-- start of Cheques Under Collection --}}
                                @php
                                $tableId = MoneyReceived::CHEQUE_UNDER_COLLECTION;
                                $repeaterId = 'm_repeater_8';

                                @endphp
                                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                                <x-tables.repeater-table :initEmpty="!isset($model) || !$model->chequeUnderCollections->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js modal-parent--js is-customer-class'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Customer <br> Name')=>'customer-name-width',
                                        __('Currency')=>'width-8',
                                        __('Due <br> Date')=>'width-12',
                                        __('Drawee <br> Bank')=>'drawee-bank-width ',
                                        __('Amount')=>'width-15',
                                        __('Cheque <br> Number')=>'width-15',
                                        __('Exchange <br> Rate')=>'width-8',
                                        __('Deposit <br> Date') => 'width-12',
                                        __('Drawal <br> Bank')=>'drawee-bank-width',
                                        __('Account <br> Type')=>'account-type-width',
                                        __('Account <br> Number')=>'account-number-width',
                                        __('Clearance <br> Days')=>'width-8'

                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->chequeUnderCollections :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $chequeUnderCollection)
                                        @php
                                        if( !($chequeUnderCollection instanceof \App\Models\MoneyReceived) ){
                                        unset($chequeUnderCollection);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>
                                            <td class="text-center">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>


                                            <input type="hidden" name="id" value="{{ isset($chequeUnderCollection) ? $chequeUnderCollection->id : 0 }}">



                                            <td>

                                                <div class="input-group css-fix-plus-direction name-class">
                                                    <x-form.select :add-new-modal="true" :add-new-modal-modal-type="'Customer'" :add-new-modal-modal-name="'Partner'" :add-new-modal-modal-title="__('Customer Name')" :options="$customersFormatted" :add-new="false" :label="' '" class="customer_name_class repeater-select" data-filter-type="{{ 'create' }}" :all="false" name="customer_id" :selected-value="isset($chequeUnderCollection) ? $chequeUnderCollection->getCustomerId() : 0"></x-form.select>
                                                </div>

                                            </td>

                                            <td>

                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency" js-when-change-trigger-change-account-type>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($chequeUnderCollection) && $chequeUnderCollection->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>
                                                <x-calendar :onlyMonth="false" :showLabel="false" :value="isset($chequeUnderCollection) ?  formatDateForDatePicker($chequeUnderCollection->getChequeDueDate()) : formatDateForDatePicker(now())" :label="__('Due Date')" :id="'due_date'" name="due_date"></x-calendar>

                                            </td>
                                            <td>
                                                <div class="kt-input-icon drawee-bank-width">
                                                    <div class="drawee-bank-add-icon-row">
                                                        <i title="{{ __('Add New Bank') }}" class="fa fa-plus cursor-pointer block ml-auto js-drawee-bank-class"></i>
                                                    </div>
                                                    <div class="input-group date">

                                                        <select data-live-search="true" data-actions-box="true" name="drawee_bank_id" class="form-control repeater-select select2-select	drawee-bank-class">
                                                            @foreach($selectedBanks as $bankId=>$bankName)

                                                            <option data-current-id="{{ isset($chequeUnderCollection) ? $chequeUnderCollection->cheque->getDraweeBankId() : 0 }}" value="{{ $bankId }}" {{ isset($chequeUnderCollection) && $chequeUnderCollection->cheque && $chequeUnderCollection->cheque->getDraweeBankId() == $bankId ? 'selected':'' }}>{{ $bankName }}</option>
                                                            @endforeach
                                                        </select>


                                                    </div>
                                                </div>
                                            </td>


                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="received_amount" type="text" class="form-control " value="{{ number_format(isset($chequeUnderCollection) ? $chequeUnderCollection->getReceivedAmount() : old('amount',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="cheque_number" type="text" class="form-control " value="{{ (isset($chequeUnderCollection) ? $chequeUnderCollection->getChequeNumber() : old('cheque_number',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>

                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" type="text" class="form-control " value="{{ isset($chequeUnderCollection) ? $chequeUnderCollection->getExchangeRate() : old('exchange_rate',1) }}">
                                                    </div>
                                                </div>

                                            </td>

                                            <td>
                                                <div class="date-max-width">
                                                    <x-calendar :onlyMonth="false" :showLabel="false" :value="isset($chequeUnderCollection) ?  formatDateForDatePicker($chequeUnderCollection->getChequeDepositDate()) :  formatDateForDatePicker(now())" :label="__('Deposit Date')" :id="'deposit_date'" name="deposit_date"></x-calendar>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date ">
                                                        <select js-when-change-trigger-change-account-type data-financial-institution-id required name="drawl_bank_id" class="form-control js-drawl-bank">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($chequeUnderCollection) && $chequeUnderCollection && $chequeUnderCollection->getChequeDrawlBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select name="account_type" class="form-control js-update-account-number-based-on-account-type">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($chequeUnderCollection) && $chequeUnderCollection->getChequeAccountType() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select data-current-selected="{{ isset($chequeUnderCollection) ? $chequeUnderCollection->getChequeAccountNumber(): 0 }}" name="account_number" class="form-control js-account-number">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>


                                            <td>
                                                <div class="kt-input-icon">
                                                    <input value="{{ isset($chequeUnderCollection) ? $chequeUnderCollection->getChequeClearanceDays() : 0 }}" required name="clearance_days" step="any" min="0" class="form-control only-greater-than-zero-or-equal-allowed" placeholder="{{__('Clearance Days')}}">
                                                </div>
                                            </td>


                                        </tr>
                                        @endforeach

                                    </x-slot>




                                </x-tables.repeater-table>
                                {{-- end of fixed monthly repeating amount --}}















































































                            </div>


                        </div>
                    </div>







                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Payable Cheques Opening Balance')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp



                                {{-- start of Cheques Under Collection --}}
                                @php
                                $tableId = MoneyPayment::PAYABLE_CHEQUE;
                                $repeaterId = 'm_repeater_9';

                                @endphp
                                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                                <x-tables.repeater-table :initEmpty="!isset($model) || !$model->payableCheques->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js modal-parent--js is-supplier-class'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Supplier <br> Name')=>'customer-name-width',
                                        __('Currency')=>'width-8',
                                        __('Due <br> Date')=>'width-12',
                                        __('Amount')=>'width-15',
                                        __('Cheque <br> Number')=>'width-15',
                                        __('Exchange <br> Rate')=>'width-8',
                                        __('Payment <br> Bank')=>'drawee-bank-width',
                                        __('Account <br> Type')=>'account-type-width',
                                        __('Account <br> Number')=>'account-number-width',
                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>

                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->payableCheques :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $payableCheques)
                                        @php
                                        if( !($payableCheques instanceof \App\Models\MoneyPayment) ){
                                  	 	     unset($payableCheques);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>
                                            <td class="text-center">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>


                                            <input type="hidden" name="id" value="{{ isset($payableCheques) ? $payableCheques->id : 0 }}">



                                            <td>
                                                <div class="input-group css-fix-plus-direction">
                                                    <x-form.select  :options="$suppliersFormatted" :add-new="false" :label="' '" class="customer_name_class repeater-select" data-filter-type="{{ 'create' }}" :all="false" name="supplier_id" :selected-value="isset($payableCheques) ? $payableCheques->getSupplierId() : 0"></x-form.select>
                                                </div>

                                            </td>

                                            <td>

                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency" js-when-change-trigger-change-account-type>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($payableCheques) && $payableCheques->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>

                                            <td>
                                                <x-calendar :onlyMonth="false" :showLabel="false" :value="isset($payableCheques) ?  formatDateForDatePicker($payableCheques->getPayableChequeDueDate()) : formatDateForDatePicker(now())" :label="__('Due Date')" :id="'due_date'" name="due_date"></x-calendar>

                                            </td>


                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="paid_amount" type="text" class="form-control " value="{{ number_format(isset($payableCheques) ? $payableCheques->getPaidAmount() : old('paid_amount',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="cheque_number" type="text" class="form-control " value="{{ isset($payableCheques) ? $payableCheques->getPayableChequeNumber() : old('cheque_number',0)}}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>

                                                <div class="kt-input-icon width-15">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" type="numeric" class="form-control " value="{{ isset($payableCheques) ? $payableCheques->getExchangeRate() : old('exchange_rate',1) }}">
                                                    </div>
                                                </div>

                                            </td>



                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date ">
                                                        <select js-when-change-trigger-change-account-type data-financial-institution-id required name="delivery_bank_id" class="form-control">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($payableCheques) && $payableCheques && $payableCheques->getPayableChequePaymentBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select name="account_type" class="form-control js-update-account-number-based-on-account-type">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($payableCheques) && $payableCheques->getPayableChequeAccountType() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select data-current-selected="{{ isset($payableCheques) ? $payableCheques->getPayableChequeAccountNumber(): 0 }}" name="account_number" class="form-control js-account-number">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                        </select>
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
                    </div>



                </div>
            </div>
            <x-submitting-by-ajax :backTo="route('opening-balance.index',['company'=>$company->id])" />

            </div>
            @endsection
            @section('js')
            <script>
                function reinitalizeMonthYearInput(dateInput) {
                    var currentDate = $(dateInput).val();
                    var startDate = "{{ isset($studyStartDate) && $studyStartDate ? $studyStartDate : -1 }}";
                    startDate = startDate == '-1' ? '' : startDate;
                    var endDate = "{{ isset($studyEndDate) && $studyEndDate? $studyEndDate : -1 }}";
                    endDate = endDate == '-1' ? '' : endDate;

                    $(dateInput).datepicker({
                            viewMode: "year"
                            , minViewMode: "year"
                            , todayHighlight: false
                            , clearBtn: true
                            , autoclose: true
                            , format: "mm/01/yyyy"
                        , })
                        .datepicker('setDate', new Date(currentDate))
                        .datepicker('setStartDate', new Date(startDate))
                        .datepicker('setEndDate', new Date(endDate))
                }

            </script>
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
            {{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script> --}}
            {{-- <script src="{{asset('assets/form-repeater.js')}}" type="text/javascript"></script> --}}
            <script>

            </script>

            <script>
                $(document).find('.datepicker-input').datepicker({
                    dateFormat: 'mm-dd-yy'
                    , autoclose: true
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

            <style>
                .readonly-style-select {
                    pointer-events: none;
                    background-color: #e9ecef;
                }
            </style>
            <script>
                const cashInSafeBranchCurrencies = @json($branchCurrencies);

                function setCashInSafeCurrencyFromBranch(row) {
                    const $row = $(row);
                    const $branchSelect = $row.find('.cash-in-safe-branch-select');
                    const $currencySelect = $row.find('.cash-in-safe-currency-select');
                    const selectedOption = $branchSelect.find('option:selected');
                    const currency = selectedOption.data('currency') || cashInSafeBranchCurrencies[$branchSelect.val()];

                    if (currency) {
                        $currencySelect.val(currency);
                    }
                }

                function initCashInSafeOpeningBalanceCurrencies() {
                    $('#m_repeater_6 [data-repeater-item]').each(function () {
                        setCashInSafeCurrencyFromBranch(this);
                    });
                }

                $(document).on('change', '#m_repeater_6 .cash-in-safe-branch-select', function () {
                    setCashInSafeCurrencyFromBranch($(this).closest('[data-repeater-item]'));
                });

                $(document).on('click', '#m_repeater_6 [data-repeater-create]', function () {
                    setTimeout(initCashInSafeOpeningBalanceCurrencies, 100);
                });

                $(function () {
                    initCashInSafeOpeningBalanceCurrencies();
                });
            </script>

            <script src="/custom/money-receive.js"></script>


            <script>
                var openedSelect = null;
                var modalId = null



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

                    const additionalColumn = $(this).attr('data-additional-column')
                    const additionalColumnValue = $(this).attr('data-additional-column-value')
                    let route = "{{ route('add.new.partner.type',['company'=>$company->id , 'type'=>'replace_with_actual_type']) }}"
                    let isSupplier = $(this).closest('.modal-parent--js.is-supplier-class').length;
                    let isCustomer = $(this).closest('.modal-parent--js.is-customer-class').length;
                    let type = isSupplier > 0 ? 'Supplier' : 'Customer';
                    route = route.replace('replace_with_actual_type', modalName);

                    $.ajax({
                        url: route
                        , data: {
                            value
                            , type
                        }
                        , type: "POST"
                        , success: function(response) {
                            $(that).attr('disabled', false);
                            modal.find('input').val('');
                            $('.modal').modal('hide')
                            if (response.status) {

                                const allSelect = $(that).closest('.kt-portlet').find('select[data-modal-name="' + modalName + '"][data-modal-type="' + modalType + '"]');
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

            </script>

            @endsection
