@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark label { text-align: left !important; white-space: nowrap !important; }
    .money-flow-dark [class*="col"] { margin-bottom: 1.5rem !important; }
    .money-flow-dark .kt-portlet { overflow: visible !important; }
</style>
@endsection
@php
    $isEdit = isset($factoringTransaction);
    $invoice = $isEdit ? $factoringTransaction->customerInvoice : null;
@endphp
@section('sub-header')
{{ $isEdit ? __('Edit Factoring With Recourse') : __('Create Factoring With Recourse') }}
@endsection
@section('content')
<div class="money-flow-dark" id="factoring-with-recourse-form"
    data-company-id="{{ $company->id }}"
    data-locale="{{ app()->getLocale() }}"
    data-mode="{{ $isEdit ? 'edit' : 'create' }}"
    @if($isEdit)
    data-except-factoring-transaction-id="{{ $factoringTransaction->id }}"
    data-initial-values="{{ json_encode([
        'factoring_company_id' => $factoringTransaction->factoring_company_id,
        'factoring_contract_id' => $factoringTransaction->factoring_contract_id,
        'customer_id' => $factoringTransaction->customer_id,
        'invoice_currency' => $factoringTransaction->invoice_currency,
        'customer_invoice_id' => $factoringTransaction->customer_invoice_id,
        'financial_institution_id' => $factoringTransaction->financial_institution_id,
        'account_type_id' => $factoringTransaction->account_type_id,
        'account_number' => $factoringTransaction->account_number,
        'factoring_amount' => (float) $factoringTransaction->factoring_amount,
    ]) }}"
    @endif
    data-contracts-url="{{ url(app()->getLocale() . '/' . $company->id . '/factoring/with-recourse/contracts') }}"
    data-currencies-url="{{ url(app()->getLocale() . '/' . $company->id . '/factoring/with-recourse/currencies') }}"
    data-invoices-url="{{ url(app()->getLocale() . '/' . $company->id . '/factoring/with-recourse/invoices') }}"
    data-calculate-url="{{ route('factoring.with-recourse.calculate', ['company' => $company->id]) }}"
    data-account-numbers-url="{{ url(app()->getLocale() . '/' . $company->id . '/money-received/get-account-numbers-based-on-account-type') }}">
<div class="row">
    <div class="col-md-12">
        <form method="post"
            action="{{ $isEdit ? route('factoring.with-recourse.update', ['company' => $company->id, 'factoringTransaction' => $factoringTransaction->id]) : route('factoring.with-recourse.store', ['company' => $company->id]) }}"
            class="kt-form kt-form--label-right" id="factoring-form">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            <x-sectionTitle :title="$isEdit ? __('Edit Factoring With Recourse') : __('Create Factoring With Recourse')"></x-sectionTitle>
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2">
                            <label>{{ __('Factoring Date') }} @include('star')</label>
                            <div class="input-group date">
                                <input type="text" name="factoring_date" id="factoring-date" required readonly
                                    class="form-control is-date-css" placeholder="{{ __('Select date') }}"
                                    value="{{ $isEdit ? formatDateForDatePicker($factoringTransaction->factoring_date) : formatDateForDatePicker(now()) }}" />
                                <div class="input-group-append"><span class="input-group-text"><i class="la la-calendar-check-o"></i></span></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label>{{ __('Factoring Company') }} @include('star')</label>
                            <select required name="factoring_company_id" id="factoring-company-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($factoringCompanies as $id => $name)
                                    <option value="{{ $id }}" @selected($isEdit && (int) $factoringTransaction->factoring_company_id === (int) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>{{ __('Factoring Contract') }} @include('star')</label>
                            <select required name="factoring_contract_id" id="factoring-contract-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @if($isEdit)
                                    @foreach(($contracts ?? collect()) as $contract)
                                        <option value="{{ $contract->id }}" @selected((int) $factoringTransaction->factoring_contract_id === (int) $contract->id)>
                                            {{ $contract->getContractStartDateFormatted() }} — {{ $contract->getContractEndDateFormatted() }}
                                            | {{ strtoupper($contract->getCurrency() ?? '') }}
                                            | {{ $contract->getLimitFormatted() }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Customer') }} @include('star')</label>
                            @if($isEdit)
                                <input type="hidden" name="customer_id" value="{{ $factoringTransaction->customer_id }}">
                                <input type="text" class="form-control exclude-text" readonly value="{{ $factoringTransaction->customer?->getName() }}">
                            @else
                                <select required name="customer_id" id="customer-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($customers as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Invoice Currency') }} @include('star')</label>
                            @if($isEdit)
                                <input type="hidden" name="invoice_currency" value="{{ $factoringTransaction->invoice_currency }}">
                                <input type="text" class="form-control exclude-text text-uppercase" readonly value="{{ strtoupper($factoringTransaction->invoice_currency) }}">
                            @else
                                <select required name="invoice_currency" id="invoice-currency-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                </select>
                            @endif
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Invoice Number') }} @include('star')</label>
                            @if($isEdit)
                                <input type="hidden" name="customer_invoice_id" value="{{ $factoringTransaction->customer_invoice_id }}">
                                <input type="text" class="form-control exclude-text" readonly value="{{ $invoice?->invoice_number }}">
                            @else
                                <select required name="customer_invoice_id" id="customer-invoice-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                </select>
                            @endif
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Invoice Due Date') }}</label>
                            <input type="text" id="invoice-due-date-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit && $invoice?->getInvoiceDueDate() ? \Carbon\Carbon::make($invoice->getInvoiceDueDate())->format('Y-m-d') : '' }}">
                            <input type="hidden" id="invoice-due-date" value="{{ $isEdit ? $invoice?->getInvoiceDueDate() : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Invoice Amount') }}</label>
                            <input type="text" id="invoice-amount-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->invoice_amount, 2) : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Factoring Percentage') }} @include('star')</label>
                            <input type="text" name="factoring_percentage" id="factoring-percentage" required class="form-control only-percentage-allowed" placeholder="0"
                                value="{{ $isEdit ? $factoringTransaction->factoring_percentage : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Factoring Amount') }}</label>
                            <input type="text" id="factoring-amount-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->factoring_amount, 2) : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Remaining Limit') }}</label>
                            <input type="text" id="remaining-limit-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit && isset($factoringTransaction) ? number_format($factoringTransaction->factoringContract?->getRemainingLimit($factoringTransaction->id) ?? 0, 2) : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Contract Interest Rate (%)') }}</label>
                            <input type="text" id="contract-interest-rate-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->contract_interest_rate, 2) : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Diff In Days') }}</label>
                            <input type="text" id="diff-in-days-display" class="form-control exclude-text" readonly
                                value="{{ $isEdit ? $factoringTransaction->diff_in_days : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Factoring Interest Amount') }}</label>
                            <input type="text" name="factoring_interest_amount" id="factoring-interest-amount-display" required class="form-control only-greater-than-or-equal-zero-allowed"
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->factoring_interest_amount, 2) : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Other Charges') }} @include('star')</label>
                            <input type="text" name="other_charges" id="other-charges" required class="form-control only-greater-than-or-equal-zero-allowed"
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->other_charges, 2) : '0' }}">
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Received Amount') }}</label>
                            <input type="text" name="received_amount" id="received-amount-display" required class="form-control only-greater-than-or-equal-zero-allowed"
                                value="{{ $isEdit ? number_format((float) $factoringTransaction->received_amount, 2) : '' }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">{{ __('Bank Details') }}</h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-4">
                            <label>{{ __('Bank') }} @include('star')</label>
                            <select required name="financial_institution_id" id="financial-institution-id" class="form-control kt_bootstrap_select financial-institution-id" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($financialInstitutionBanks as $bank)
                                    <option value="{{ $bank->id }}" @selected($isEdit && (int) $factoringTransaction->financial_institution_id === (int) $bank->id)>{{ $bank->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('Account Type') }} @include('star')</label>
                            <select required name="account_type_id" id="account-type-id" class="form-control kt_bootstrap_select js-update-account-number-based-on-account-type" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($accountTypes as $accountType)
                                    <option value="{{ $accountType->id }}" @selected($isEdit && (int) $factoringTransaction->account_type_id === (int) $accountType->id)>{{ $accountType->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('Account Number') }} @include('star')</label>
                            <select required name="account_number" id="account-number-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @if($isEdit)
                                    <option value="{{ $factoringTransaction->account_number }}" selected>{{ $factoringTransaction->account_number }}</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <x-submitting-by-ajax :backTo="route('factoring.with-recourse.index', ['company' => $company->id])" />
        </form>
    </div>
</div>
</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}"></script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}"></script>
<script src="{{ url('custom/factoring-with-recourse.js') }}?v={{ time() }}"></script>
@endsection
