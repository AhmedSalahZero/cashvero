@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
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
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }
</style>
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <form method="post"
            action="{{ isset($model)
                ? route('leasing.contracts.update', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $model->id])
                : route('leasing.contracts.store', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]) }}"
            class="kt-form kt-form--label-right">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <input type="hidden" name="leasing_company_id" value="{{ $leasingCompany->id }}">
            @if(isset($model))
                <input type="hidden" name="updated_by" value="{{ auth()->user()->id }}">
            @else
                <input type="hidden" name="created_by" value="{{ auth()->user()->id }}">
            @endif
            @csrf
            @if(isset($model))
                @method('put')
            @endif

            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            <x-sectionTitle :title="__(isset($model) ? 'Edit Contract' : 'Add Contract')"></x-sectionTitle>
                        </h3>
                    </div>
                </div>
            </div>

            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">{{ __('Contract Information') }}</h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4">
                                <label>{{ __('Name') }} @include('star')</label>
                                <input type="text" value="{{ isset($model) ? $model->getName() : '' }}" name="name" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <x-form.date :label="__('Start Date')" :required="true" :model="$model ?? null" :name="'start_date'"></x-form.date>
                            </div>
                            <div class="col-md-2">
                                <x-form.date :label="__('End Date')" :required="true" :model="$model ?? null" :name="'end_date'"></x-form.date>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Currency') }} @include('star')</label>
                                <select name="currency" class="form-control">
                                    <option selected>{{ __('Select') }}</option>
                                    @foreach(getCurrencies() as $currencyName => $currencyValue)
                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName) selected @elseif($currencyName == 'EGP') selected @endif>{{ $currencyValue }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Limit') }} @include('star')</label>
                                <input type="text" value="{{ isset($model) ? $model->getLimit() : 0 }}" name="limit" class="form-control only-greater-than-zero-allowed">
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Borrowing Rate %') }} @include('star')</label>
                                <input type="text" value="{{ isset($model) ? $model->getBorrowingRate() : 0 }}" name="borrowing_rate" class="form-control recalculate-interest-rate">
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Margin Rate %') }} @include('star')</label>
                                <input type="text" value="{{ isset($model) ? $model->getMarginRate() : 0 }}" name="margin_rate" class="form-control recalculate-interest-rate">
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Interest Rate %') }} @include('star')</label>
                                <input readonly name="interest_rate" type="text" value="{{ isset($model) ? $model->getInterestRate() : 0 }}" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Duration (In Months)') }} @include('star')</label>
                                <input type="text" value="{{ isset($model) ? $model->getDuration() : 0 }}" name="duration" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Installment Payment Interval') }} @include('star')</label>
                                <select required name="installment_payment_interval" class="form-control">
                                    <option value="" selected>{{ __('Select') }}</option>
                                    @foreach(\App\Helpers\HVero::getDurationIntervalTypesForSelect() as $intervalArr)
                                        <option value="{{ $intervalArr['value'] }}" @if(isset($model) && $intervalArr['value'] == $model->getPaymentInstallmentInterval()) selected @endif>{{ $intervalArr['title'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <x-submitting :backTo="route('leasing.contracts.index', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id])" />
        </form>
    </div>
</div>
</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript"></script>
<script>
    $('input[name="borrowing_rate"],input[name="margin_rate"]').on('change', function() {
        let borrowingRate = parseFloat($('input[name="borrowing_rate"]').val() || 0);
        let marginRate = parseFloat($('input[name="margin_rate"]').val() || 0);
        $('input[name="interest_rate"]').val(borrowingRate + marginRate);
    });
    $('input[name="borrowing_rate"]').trigger('change');
</script>
@endsection
