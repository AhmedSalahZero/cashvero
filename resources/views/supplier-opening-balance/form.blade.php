@php
use App\Models\MoneyPayment ;
@endphp
@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .bootstrap-select .dropdown-menu {
        max-height: 100px !important;
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

    .supplier-name-width {
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
{{ __('Suppliers Opening Balance') }}
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ? route('suppliers-opening-balance.update',['company'=>$company->id,'suppliers_opening_balance'=>$model->id]) : route('suppliers-opening-balance.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
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
                                    <x-sectionTitle :title="__('Supplier Opening Balance')"></x-sectionTitle>
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
                                    <x-form.date :type="'text'" :classes="'datepicker-input'" :default-value="formatDateForDatePicker(isset($model)  ? $model->getDate() :null)" :model="$model??null" :label="__('Opening Balance Date')" :type="'text'" :placeholder="__('')" :name="'date'" :required="true"></x-form.date>
                                </div>


                            </div>
                        </div>
                    </div>


                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Suppliers Opening Balance')}}
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
                                $tableId = 'opening-balances';
                                $repeaterId = 'm_repeater_6';
                                @endphp
                                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                                <x-tables.repeater-table :initEmpty="!isset($model) || !$model->supplierInvoices->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Supplier')=>'col-md-2',
										   __('Invoice No')=>'col-md-1',
                                        __('Project Name')=>'col-md-1',
										  __('Contract Code')=>'col-md-1',
                                        __('Contract Date')=>'col-md-1',
										 __('Purchase Order Number')=>'col-md-1',
                                        __('Amount')=>'col-md-1',
                                        __('Currency')=>'col-md-1',
                                        __('Exchange <br> Rate')=>'col-md-1',
                                        __('Due Date')=>'col-md-1',
                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->supplierInvoices :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $supplierInvoice)
                                        @php
                                        if( !($supplierInvoice instanceof \App\Models\SupplierInvoice) ){
                                        unset($supplierInvoice);
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
                                                    <select name="partner_id" class="form-control partner_id_class">
                                                        @foreach($suppliersFormatted as  $supplierArr )
														@php
															$supplierName = $supplierArr['title'];
															$supplierId = $supplierArr['value'];
														@endphp
                                                        <option value="{{ $supplierId }}" @if(isset($supplierInvoice) && $supplierInvoice->getPartnerId() == $supplierId ) selected @endif > {{ $supplierName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
											
											
											 <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="invoice_number" step="4" type="text" class="form-control " value="{{ isset($supplierInvoice) ? $supplierInvoice->getInvoiceNumber() : old('invoice_number',1) }}">
                                                    </div>
                                                </div>

                                            </td>


                                            <td>
                                                <div class="input-group">
                                                    <select data-current-contract-id="{{ isset($supplierInvoice) ? $supplierInvoice->getContractName() : 0 }}" name="contract_name" class="form-control contract_name">

                                                    </select>
                                                </div>

                                            </td>


                                            <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="contract_code" step="4" type="text" class="form-control " value="{{ isset($supplierInvoice) ? $supplierInvoice->getContractCode() : '' }}">
                                                    </div>
                                                </div>

                                            </td>

                                            <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="contract_date" type="date" class="form-control " value="{{ isset($supplierInvoice) ? $supplierInvoice->getContractDate() : '' }}">
                                                    </div>
                                                </div>

                                            </td>

                                            <td>
                                                <div class="input-group">
                                                    <select data-current-sales-order-number="{{ isset($supplierInvoice) ? $supplierInvoice->getPurchasesOrderNumber() : 0 }}" name="purchases_order_number" class="form-control sales_order_number">

                                                    </select>
                                                </div>

                                            </td>
											
                                             <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                     
                                                        <input name="paid_amount" type="text" class="form-control " value="{{ number_format(isset($supplierInvoice) ? $supplierInvoice->getInvoiceAmount() : old('amount',0)) }}">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="hidden" name="id" value="{{ isset($supplierInvoice) ? $supplierInvoice->id : 0 }}">





                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency ajax-get-invoice-numbers" js-when-change-trigger-change-account-type>
                                                        {{-- <option selected>{{__('Select')}}</option> --}}
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($supplierInvoice) && $supplierInvoice->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" step="4" type="text" class="form-control " value="{{ isset($supplierInvoice) ? $supplierInvoice->getExchangeRate() : old('exchange_rate',1) }}">
                                                    </div>
                                                </div>

                                            </td>
											<td>
                                    				<x-form.date :type="'text'" :classes="'datepicker-input'" :default-value="formatDateForDatePicker(isset($supplierInvoice)  ? $supplierInvoice->getInvoiceDueDate() : now()->format('Y-m-d'))" :model="$model??null" :label="''" :type="'text'" :placeholder="__('')" :name="'invoice_due_date'" :required="true"></x-form.date>
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
                                    {{__('Suppliers Advanced Opening Balance')}}
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
                                $tableId = 'advanced-opening-balances';
                                $repeaterId = 'm_repeater_7';
                                @endphp
                                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                                <x-tables.repeater-table :initEmpty="!isset($model) || !$model->moneyModel->count()" :firstElementDeletable="true" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        __('Supplier')=>'col-md-1',
                                        __('Amount')=>'col-md-1',
                                        __('Currency')=>'col-md-1',
                                        __('Exchange <br> Rate')=>'col-md-1',
                                        __('Type')=>'col-md-1',
                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->moneyModel :[-1] ;
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $moneyModel)
                                        @php
                                        if( !($moneyModel instanceof \App\Models\MoneyPayment) ){
                                        unset($moneyModel);
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
                                                    <select name="partner_id" class="form-control partner_id ajax-get-contracts-for-supplier">
                                                        @foreach($suppliersFormatted as $supplierArr )
                                                        @php
                                                        $supplierName = $supplierArr['title'];
                                                        $supplierId = $supplierArr['value'];
                                                        @endphp
                                                        <option value="{{ $supplierId }}" @if(isset($moneyModel) && $moneyModel->getPartnerId() == $supplierId ) selected @endif > {{ $supplierName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">

                                                        <input name="paid_amount" type="text" class="form-control " value="{{ number_format(isset($moneyModel) ? $moneyModel->getPaidAmount() : old('amount',0)) }}">
                                                    </div>
                                                </div>
												
                                            </td>
                                            <td>
                                                <input type="hidden" name="id" value="{{ isset($moneyModel) ? $moneyModel->id : 0 }}">





                                                <div class="input-group">
                                                    <select name="currency" class="form-control select-for-currency currency-for-contracts ajax-get-contracts-for-supplier" js-when-change-trigger-change-account-type>
                                                        {{-- <option  selected>{{__('Select')}}</option> --}}
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($moneyModel) && $moneyModel->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            </td>
                                            <td>

                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="exchange_rate" step="4" type="text" class="form-control  " value="{{ isset($moneyModel) ? $moneyModel->getExchangeRate() : old('exchange_rate',1) }}">
                                                    </div>
                                                </div>

                                            </td>
                                            <td>
                                                <select name="down_payment_type" class="form-control down-payment-type">
                                                    <option value="{{ MoneyPayment::DOWN_PAYMENT_GENERAL }}" @if(isset($moneyModel) && $moneyModel->isGeneralDownPayment() ) selected @endif > {{ __('General') }}</option>
                                                    <option value="{{ MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT }}" @if(isset($moneyModel) && $moneyModel->isOverContractDownPayment()) selected @endif > {{ __('Over Contract') }}</option>
                                                </select>

                                                <div class="contract-container">
                                                    <select data-current-selected="{{ isset($moneyModel) && $moneyModel->getContractId() ?  $moneyModel->getContractId() : 0 }}" name="contract_id" class="form-control contract-class">
                                                        {{-- @foreach($contracts as $contract) --}}
                                                        {{-- <option value="{{ $contract->id }}" @if(isset($moneyModel) && $moneyModel->getContractId() ) selected @endif > {{$contract->getName() }}</option> --}}
                                                        {{-- @endforeach --}}
                                                    </select>

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
            <x-submitting-by-ajax :backTo="route('suppliers-opening-balance.index',['company'=>$company->id])" />

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
                    let type = isSupplier > 0 ? 'Supplier' : 'Supplier';
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
















$(document).on('change', '.ajax-get-contracts-for-supplier', function(e) {
                    e.preventDefault()
                    const parent = $(this).closest('tr');
                    const supplierId = parent.find('select.partner_id').val()
                    const currency = parent.find('select.currency-for-contracts').val()
                    const contractId = parent.find('select.contract-class').attr('data-current-selected');
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
                                parent.find('select.contract-class').empty().append(options)
                                parent.find('select.contract-class').trigger('change')
                            }
                        })
                    } else {
                        parent.find('select.contract-class').empty().append("")
                        parent.find('select.contract-class').trigger('change')
                    }
                })
                $('select.ajax-get-contracts-for-supplier').trigger('change')
                $(document).on('change', 'select.down-payment-type', function() {
                    const val = $(this).val();
                    if (val == 'over_contract') {
                        $(this).closest('td').find('.contract-container').show();
                    } else {
                        $(this).closest('td').find('.contract-container').hide();
                    }
                });
                $('select.down-payment-type').trigger('change');

            </script>
 <script>
                $(document).on('change', 'select.partner_id_class', function(e) {
                    let parent = $(this).closest('tr')
                    const customerOrSupplierId = $(this).val();
                    const currentContractName = parent.find('[data-current-contract-id]').attr('data-current-contract-id');
                    $.ajax({
                        url: "{{ route('get.projects.for.customer.or.supplier',['company'=>$company->id]) }}"
                        , data: {
                            customerOrSupplierId
                        }
                        , success: function(res) {
                            var options = '<option value="0" selected>Select</option>';
                            for (var contract of res.projects) {
                                var selected = contract.name == currentContractName ? 'selected' : '';
                                options += `<option ${selected} data-contract-code="${contract.code}" data-contract-date="${contract.start_date}" data-contract-id="${contract.id}"  value="${contract.name}">${contract.name}</option>`
                            }
                            parent.find('select.contract_name').empty().append(options).trigger('change');
                        }
                    })
                })
                $(document).on('change', 'select.contract_name', function() {
                    let parent = $(this).closest('tr')
                    const contractId = $(this).find('option:selected').attr('data-contract-id');
                    const contractCode = $(this).find('option:selected').attr('data-contract-code');
                    const contractDate = $(this).find('option:selected').attr('data-contract-date');
                    var currentSalesOrderNumber = parent.find('[data-current-sales-order-number]').attr('data-current-sales-order-number');
                    parent.find('[name*="contract_code"]').val(contractCode);
                    parent.find('[name*="contract_date"]').val(contractDate);
                    $.ajax({
                        url: "{{ route('get.po.or.so.from.contract',['company'=>$company->id]) }}"
                        , data: {
                            contractId
                        }
                        , success: function(res) {
                            var purchaseOrders = res.purchase_orders;
                            var salesOrders = res.sales_orders;
                            var purchaseOrdersOptions = '';
                            var salesOrdersOptions = '';
                            var isCustomer = +$('input#is-customer').val();
                            var items = isCustomer ? salesOrders : purchaseOrders;
                            for (var purchaseOrder of items) {
                                var poOrSoNumber = isCustomer ? 'so_number' : 'po_number';
                                var purchaseOrderSelected = purchaseOrder[poOrSoNumber] == currentSalesOrderNumber ? 'selected' : '';
                                purchaseOrdersOptions += `<option ${purchaseOrderSelected} data-date="${purchaseOrder.start_date_1}" value="${purchaseOrder[poOrSoNumber]}"> ${purchaseOrder[poOrSoNumber]}</option>`
                            }
                            parent.find('select[data-current-sales-order-number]').empty().append(purchaseOrdersOptions).trigger('change');
                        }
                    })

                })
                $(document).on('change', 'select[data-current-sales-order-number]', function() {
                    let parent = $(this).closest('tr')
                    const date = $(this).find('option:selected').attr('data-date');
                    parent.find('input[name*="sales_order_date"]').val(date).trigger('change');
                    parent.find('input[name*="purchases_order_date"]').val(date).trigger('change');
                })
                $(function() {
                    $('select.partner_id_class').trigger('change')
                })

            </script>
			
			
			
            @endsection
