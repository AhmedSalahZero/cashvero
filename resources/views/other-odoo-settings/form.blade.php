@php
use App\NotificationSetting ;
@endphp
@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }

    .money-flow-dark label {
        white-space: nowrap !important;
        text-align: left !important;
    }

    .money-flow-dark [class*="col"] {
        margin-bottom: 1.5rem !important;
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

    .money-flow-dark input.form-control[disabled]:not(.ignore-global-style),
    .money-flow-dark input.form-control:not(.is-date-css)[readonly]:not(#kt_datepicker_2) {
        background-color: var(--readonly-bg) !important;
        color: var(--text-primary) !important;
        font-weight: bold !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Other Integration Settings') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ route('odoo-settings.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            @csrf

            <div class="row">
                <div class="col-md-12">
                    <!--begin::Portlet-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    <x-sectionTitle :title="__('Please Insert Odoo Chart Of Account Number')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Liquidity / Treasury Accounts')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">
                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Liquidity / Treasury Clearance Account')" :type="'text'" :placeholder="__('Liquidity / Treasury Clearance Account')" :name="'liquidity_transfer_account_code'" :required="false"></x-form.input>
                                </div>
                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Custody Account')" :type="'text'" :placeholder="__('Custody Account')" :name="'custody_account_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Advanced / Employee Loans Account')" :type="'text'" :placeholder="__('Advanced / Employee Loans Account')" :name="'employee_loans_account_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Notes/Cheques Receivables')" :type="'text'" :placeholder="__('Notes/Cheques Receivables')" :name="'cheques_receivable_code'" :required="false"></x-form.input>
                                </div>
                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Notes/Cheques Payables')" :type="'text'" :placeholder="__('Notes/Cheques Payables')" :name="'cheques_payable_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Shareholders Account')" :type="'text'" :placeholder="__('Shareholders Account')" :name="'shareholder_account_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Dividend Payable Account')" :type="'text'" :placeholder="__('Shareholders Account')" :name="'dividend_payable_account_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Insurance From Account')" :type="'text'" :placeholder="__('Insurance From Account')" :name="'insurance_from_account_code'" :required="false"></x-form.input>
                                </div>
                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Advances To Suppliers')" :type="'text'" :placeholder="__('Advances To Suppliers')" :name="'advances_to_suppliers_code'" :required="false"></x-form.input>
                                </div>
								  <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Advances From Customers')" :type="'text'" :placeholder="__('Advances From Customers')" :name="'advances_from_customers_code'" :required="false"></x-form.input>
                                </div>
								
								




                            </div>
                        </div>
                    </div>

                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('LG & LC Cash Cover Accounts')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">


                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Bid LG Cash Cover')" :type="'text'" :placeholder="__('Bid LG Cash Cover')" :name="'bid_lg_cash_cover_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Final LG Cash Cover')" :type="'text'" :placeholder="__('Final LG Cash Cover')" :name="'final_lg_cash_cover_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Advanced LG Cash Cover')" :type="'text'" :placeholder="__('Advanced LG Cash Cover')" :name="'advanced_lg_cash_cover_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Performance LG Cash Cover')" :type="'text'" :placeholder="__('Performance LG Cash Cover')" :name="'performance_lg_cash_cover_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Sight Lc Cash Cover')" :type="'text'" :placeholder="__('Sight Lc Cash Cover')" :name="'sight_lc_cash_cover_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Deferred Lc Cash Cover')" :type="'text'" :placeholder="__('Deferred Lc Cash Cover')" :name="'deferred_lc_cash_cover_code'" :required="false"></x-form.input>
                                </div>




                            </div>
                        </div>
                    </div>

 				<div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Taxes & Social Insurance')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">


                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('VAT Taxes')" :type="'text'" :placeholder="__('VAT Taxes')" :name="'vat_taxes_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Credit Withhold Taxes')" :type="'text'" :placeholder="__('Credit Withhold Taxes')" :name="'credit_withhold_taxes_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Salary Taxes')" :type="'text'" :placeholder="__('Salary Taxes')" :name="'salary_taxes_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Social Insurance')" :type="'text'" :placeholder="__('Social Insurance')" :name="'social_insurance_code'" :required="false"></x-form.input>
                                </div>
								
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Income Taxes')" :type="'text'" :placeholder="__('Income Taxes')" :name="'income_taxes_code'" :required="false"></x-form.input>
                                </div>
								
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Takaful Contribution Tax')" :type="'text'" :placeholder="__('Takaful Contribution Tax')" :name="'takaful_code'" :required="false"></x-form.input>
                                </div>
								
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Tax for the Support of Victims Fund')" :type="'text'" :placeholder="__('Tax for the Support of Victims Fund')" :name="'tax_for_victims_code'" :required="false"></x-form.input>
                                </div>
								
								
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Real Estate Taxes')" :type="'text'" :placeholder="__('Real Estate Taxes')" :name="'real_estate_taxes_code'" :required="false"></x-form.input>
                                </div>
								
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Stamp Duty Taxes')" :type="'text'" :placeholder="__('Stamp Duty Taxes')" :name="'stamp_duty_taxes_code'" :required="false"></x-form.input>
                                </div>
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Other Taxes')" :type="'text'" :placeholder="__('Other Taxes')" :name="'other_taxes_code'" :required="false"></x-form.input>
                                </div>
								
								




                            </div>
                        </div>
                    </div>
					


                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Bank Charges & Fees')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">


                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Letter Of Guarantee Commission Fees')" :type="'text'" :placeholder="__('Letter Of Guarantee Commission Fees')" :name="'letter_of_guarantee_commission_fees_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Letter Of Guarantee Issuance Fees')" :type="'text'" :placeholder="__('Letter Of Guarantee Issuance Fees')" :name="'letter_of_guarantee_issuance_fees_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Letter Of Credit Commission Fees')" :type="'text'" :placeholder="__('Letter Of Credit Commission Fees')" :name="'letter_of_credit_commission_fees_code'" :required="false"></x-form.input>
                                </div>

                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Letter Of Credit Other Fees')" :type="'text'" :placeholder="__('Letter Of Credit Other Fees')" :name="'letter_of_credit_other_fees_code'" :required="false"></x-form.input>
                                </div>





                            </div>
                        </div>
                    </div>
					
					
					<div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Bank Facilities Interest Expense')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">

                            <div class="form-group row">


                                <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Fully Secured Overdraft Interest Expense')" :type="'text'" :placeholder="__('Fully Secured Overdraft Interest Expense')" :name="'fully_secured_overdraft_interest_expense_code'" :required="false"></x-form.input>
                                </div>
								
								 <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Clean Overdraft Interest Expense')" :type="'text'" :placeholder="__('Clean Overdraft Interest Expense')" :name="'clean_overdraft_interest_expense_code'" :required="false"></x-form.input>
                                </div>
								
								 <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Overdraft Against Commercial Paper Interest Expense')" :type="'text'" :placeholder="__('Overdraft Against Commercial Paper Interest Expense')" :name="'overdraft_against_commercial_paper_interest_expense_code'" :required="false"></x-form.input>
                                </div>
								
								 <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Overdraft Against Contract Assignment Interest Expense')" :type="'text'" :placeholder="__('Overdraft Against Contract Assignment Interest Expense')" :name="'overdraft_against_contract_assignment_interest_expense_code'" :required="false"></x-form.input>
                                </div>
								
								 <div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Medium Term Loan Interest Expense')" :type="'text'" :placeholder="__('Medium Term Loan Interest Expense')" :name="'medium_term_loan_interest_expense_code'" :required="false"></x-form.input>
                                </div>
								
								
								
								





                            </div>
                        </div>
                    </div>
					



                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Interest Revenues Accounts')}}
                                </h3>
                            </div>
                        </div>
						
                        <div class="kt-portlet__body">
                            {{-- @include('other-odoo-settings.repeater-with-all',[
                            'financialInstitutionBanks'=>$financialInstitutionBanks
                            ]) --}}
							@include('other-odoo-settings.repeater-with-all',[
                            'financialInstitutionBanks'=>$financialInstitutionBanks
                            ])
							
                            {{-- <div class="form-group row"> --}}


                            {{--
								<div class="col-md-3 ">
                                    <x-form.input :default-value="null" :model="$model??null" :label="__('Interest Revenue')" :type="'text'" :placeholder="__('Interest Revenue')" :name="'interest_revenue_code'" :required="false"></x-form.input>
                                </div>
								
								<div class="col-md-6 ">
                             
                                <label>{{__('Receiving Bank')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select js-when-change-trigger-change-account-type data-financial-institution-id name="financial_institution_id" class="form-control ">
                                        @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                        <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getCashInBankReceivingBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                        </div> --}}












                        {{-- </div> --}}
                    </div>
                </div>







            </div>
    </div>
    <x-submitting />
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


    @endsection
