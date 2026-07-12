@extends('layouts.dashboard')
@php
use App\Models\LeasingContract;
@endphp
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark input[type="checkbox"] {
        cursor: pointer;
    }
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Leasing Contracts') }} — {{ $leasingCompany->getName() }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <x-back-to-leasing-header-btn
        :create-permission-name="'create medium term loan'"
        :create-route="route('leasing.contracts.create', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id])"
        :create-label="__('New Contract')"
    />

    <div class="kt-portlet__body">
        <div class="tab-content kt-margin-t-20">
            @php $currentType = LeasingContract::RUNNING; @endphp
            <div class="tab-pane active" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-end-date :type="$currentType" :title="__('Leasing Contracts')" :endDate="$filterDates[$currentType]['endDate'] ?? ''">
                        <x-export-leasing-contracts :leasing-company="$leasingCompany" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" />
                    </x-table-title.with-end-date>
                    <div class="kt-portlet__body">
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Limit') }}</th>
                                    <th>{{ __('Borrowing Rate') }}</th>
                                    <th>{{ __('Margin Rate') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Installment Interval') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index => $model)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="text-nowrap">{{ $model->getName() }}</td>
                                    <td>{{ $model->getStartDateFormatted() }}</td>
                                    <td>{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getLimitFormatted() }}</td>
                                    <td>{{ $model->getBorrowingRateFormatted() }}</td>
                                    <td>{{ $model->getMarginRateFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getDurationFormatted() }}</td>
                                    <td class="text-transform">{{ $model->getPaymentInstallmentIntervalFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @if(hasAuthFor('create medium term loan'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Upload Contract Schedule') }}" href="{{ route('view.uploading', ['company' => $company->id, 'loanId' => $model->id, 'model' => 'ContractLoanSchedule']) }}"><i class="fa fa-upload pl-2"></i> <i class="fa fa-dollar-sign ml-1 pr-2"></i></a>
                                            @endif
                                            @if(hasAuthFor('update medium term loan'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit') }}" href="{{ route('leasing.contracts.edit', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(hasAuthFor('delete medium term loan'))
                                            <a data-toggle="modal" data-target="#delete-leasing-contract-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-leasing-contract-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('leasing.contracts.destroy', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $model->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
@endsection
