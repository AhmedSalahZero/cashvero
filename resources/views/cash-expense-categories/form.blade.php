@php
use App\Models\MoneyReceived ;
@endphp
@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-portlet .kt-portlet__head {
        border-bottom-color: #CCE2FD !important;
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

    .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
        flex: initial !important;
    }

    .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
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
@endsection
@section('sub-header')
{{ __('Expense Categories') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">

        <form  onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ? route('cash.expense.category.update',['company'=>$company->id,'cashExpenseCategory'=>$model->id]) : route('cash.expense.category.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
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
                                    <x-sectionTitle :title="__('Expense Categories')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->

                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Expense Category Information')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <input type="hidden" name="company_id" value="{{ $company->id }}">
                            {{-- <input id="model_type" type="hidden" name="model_type" value="{{ $type }}"> --}}
                            <div class="form-group row">

                                <div class="col-md-4 ">
                                    <label> {{ __('Name') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input required name="name" type="text" class="form-control" value="{{ isset($model) ? $model->getName() : old('name',null) }}">
                                        </div>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>


                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{ __('Expense Item Names') }}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp


                                @php
                                $columns = [
                                __('Name') =>'col-md-1',
                                ] ;
                                if($company->hasOdooIntegrationCredentials()){
                                $columns[__('Odoo Chart Of Account Number')] ='col-md-1';
                                }
                                @endphp
                                {{-- start of fixed monthly repeating amount --}}
                                @php
                                $tableId = 'cashExpenseCategoryNames';
                                $repeaterId = 'm_repeater_6';

                                @endphp
                                {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                                <x-tables.repeater-table :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">

                                        @foreach( $columns as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = isset($model) ? $model->cashExpenseCategoryNames :[-1] ;
                                        if(count(old('cashExpenseCategoryNames',[]))){
                                        $rows = \App\Helpers\HHelpers::newInstanceOf( \App\Models\CashExpenseCategoryName::class , old('cashExpenseCategoryNames'));
                                        }
                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $cashExpenseCategoryName)
                                        @php
                                        if( !($cashExpenseCategoryName instanceof \App\Models\CashExpenseCategoryName) ){
                                        unset($cashExpenseCategoryName);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>

                                            <td class="text-center">
                                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                <input type="hidden" name="id" value="{{ isset($cashExpenseCategoryName) ? $cashExpenseCategoryName->id :0 }}">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>


                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input required name="name" type="text" class="form-control" value="{{ isset($cashExpenseCategoryName) ? $cashExpenseCategoryName->getName() : old('name','') }}">
                                                    </div>
                                                </div>
                                            </td>

                                            @if($company->hasOdooIntegrationCredentials())
                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input required name="odoo_chart_of_account_number" type="numeric" class="form-control only-greater-than-zero-allowed" value="{{ isset($cashExpenseCategoryName) ? $cashExpenseCategoryName->getOdooChartOfAccountNumber() : old('odoo_chart_of_account_number','') }}">
                                                    </div>
                                                </div>
                                            </td>
                                            @endif






                                        </tr>
                                        @endforeach

                                    </x-slot>




                                </x-tables.repeater-table>
                                {{-- end of fixed monthly repeating amount --}}

                            </div>

                        </div>
                    </div>






















                    <x-submitting :backTo="route('cash.expense.category.index',['company'=>$company->id])" />

                </div>
            </div>

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
                            , clearBtn: true,


                            autoclose: true
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
            <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
            <script>

            </script>

            <script>
                $(document).find('.datepicker-input').datepicker({
                    dateFormat: 'mm-dd-yy'
                    , autoclose: true
                })
                $('.repeater-js').repeater({
                    initEmpty: false
                    , isFirstItemUndeletable: true
                    , defaultValues: {
                        'text-input': 'foo'
                    },

                    show: function() {
                        $(this).slideDown();

                        $('input.trigger-change-repeater').trigger('change')
                        $(document).find('.datepicker-input:not(.only-month-year-picker)').datepicker({
                            dateFormat: 'mm-dd-yy'
                            , autoclose: true
                        })

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

            <script src="/custom/money-receive.js"></script>

            <script>
                $(document).on('change', 'select.contracts-js', function() {
                    const parent = $(this).closest('tr')
                    const code = $(this).find('option:selected').data('code')
                    const amount = $(this).find('option:selected').data('amount')
                    const currency = $(this).find('option:selected').data('currency')
                    $(parent).find('.contract-code').val(code)
                    $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)

                })

                $(function() {
                    $('select.suppliers-or-customers-js').trigger('change')
                })

            </script>
            @endsection
