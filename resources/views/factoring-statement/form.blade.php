@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet { overflow: visible !important; }
</style>
@endsection
@section('sub-header')
{{ __('Factoring Statement') }}
@endsection
@section('content')
<div class="money-flow-dark" id="factoring-statement-form"
    data-currencies-url="{{ url(app()->getLocale() . '/' . $company->id . '/factoring-statement/currencies') }}"
    data-contracts-url="{{ url(app()->getLocale() . '/' . $company->id . '/factoring-statement/contracts') }}">
<div class="row">
    <div class="col-md-12">
        <form class="kt-form kt-form--label-right" method="get" action="{{ route('result.factoring.statement', ['company' => $company->id]) }}">
            <div class="kt-portlet">
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-3 mb-4">
                            <label>{{ __('Start Date') }} @include('star')</label>
                            <input required type="date" class="form-control" name="start_date" id="start-date" value="{{ now()->subYear()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 mb-4">
                            <label>{{ __('End Date') }} @include('star')</label>
                            <input required type="date" class="form-control" name="end_date" id="end-date" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 mb-4">
                            <label>{{ __('Factoring Company') }} @include('star')</label>
                            <select required name="factoring_company_id" id="factoring-company-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($factoringCompanies as $factoringCompany)
                                    <option value="{{ $factoringCompany->id }}">{{ $factoringCompany->getName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label>{{ __('Currency') }} @include('star')</label>
                            <select required name="currency" id="currency-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label>{{ __('Factoring Contract') }} @include('star')</label>
                            <select required name="factoring_contract_id" id="factoring-contract-id" class="form-control kt_bootstrap_select" data-live-search="true">
                                <option value="">{{ __('Select') }}</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <x-submitting />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}"></script>
<script src="{{ url('custom/factoring-statement.js') }}?v={{ time() }}"></script>
@endsection
