@extends('layouts.dashboard')
@push('css')
<x-styles.commons></x-styles.commons>
@endpush
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

    /* Datepicker must be dropdown-only (never inline inside input-group) */
    .money-flow-dark .input-group.date > .datepicker:not(.datepicker-dropdown) {
        display: none !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Consolidated Cash Flow') }}
@endsection
@section('content')
<div class="money-flow-dark">
<p style="opacity:.85;margin-bottom:1rem;">{{ __('Note: the report period must include today (same rule as the main cash flow report).') }}</p>
<p style="opacity:.85;margin-bottom:1rem;">{{ __('Tip: select only the contracts you need (up to 50 per run). Monthly interval is faster than daily for long periods.') }}</p>
<form class="kt-form kt-form--label-right" method="get" action="{{ route('reports.consolidated-cash-flow.result', ['company' => $company->id]) }}">
    <div class="kt-portlet">
        <div class="kt-portlet__body">
            <div class="form-group row">
                <div class="col-md-3">
                    <label>{{ __('Report Interval') }} @include('star')</label>
                    <select name="report_interval" class="form-control" required>
                        <option value="daily">{{ __('Daily') }}</option>
                        <option value="weekly" selected>{{ __('Weekly') }}</option>
                        <option value="monthly">{{ __('Monthly') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <x-calendar :onlyMonth="false" :value="formatDateForDatePicker(old('start_date', now()->format('Y-m-d')))" :label="__('Start Date')" :id="'ccf_start'" :name="'start_date'"></x-calendar>
                </div>
                <div class="col-md-3">
                    <x-calendar :onlyMonth="false" :value="formatDateForDatePicker(old('end_date', now()->addMonths(6)->format('Y-m-d')))" :label="__('End Date')" :id="'ccf_end'" :name="'end_date'"></x-calendar>
                </div>
                <div class="col-md-3">
                    <label>{{ __('Currency') }}</label>
                    <select name="currency" class="form-control">
                        <option value="{{ $company->getMainFunctionalCurrency() }}" selected>{{ $company->getMainFunctionalCurrency() }}</option>
                        @foreach (getBanksCurrencies() as $currencyId => $currentName)
                            <option value="{{ $currentName }}" @if (old('currency') === $currentName) selected @endif>{{ $currentName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <div class="col-md-12">
                    <label>{{ __('Contracts') }} ({{ __('leave empty for all active contracts') }})</label>
                    <select name="contract_ids[]" class="form-control select2-select" multiple data-live-search="true" data-actions-box="true">
                        @foreach ($activeContracts as $c)
                            <option value="{{ $c->id }}" @if (collect(old('contract_ids', []))->contains($c->id)) selected @endif>
                                {{ $c->getName() }} @if ($c->getCode()) [{{ $c->getCode() }}] @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
    <x-custom-button-name-to-submit :displayName="__('Run Report')" />
</form>
</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script>
    $(function () {
        const $dateInputs = $('.money-flow-dark input.datepicker-input:not(.only-month-year-picker)');

        $dateInputs.each(function () {
            const $input = $(this);
            if ($input.data('datepicker')) {
                $input.datepicker('destroy');
            }
        });

        $dateInputs.datepicker({
            format: 'mm/dd/yyyy',
            autoclose: true,
            todayHighlight: true,
            orientation: 'bottom auto',
            container: 'body',
            zIndexOffset: 1100,
            disableTouchKeyboard: true,
        });
    });
</script>
@endsection
