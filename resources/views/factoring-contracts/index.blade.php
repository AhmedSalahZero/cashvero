@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark input[type="checkbox"] {
        cursor: pointer;
    }
    .money-flow-dark .bank-max-width {
        max-width: 200px !important;
    }
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Factoring Contracts') }} — {{ $factoringCompany->getName() }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <x-back-to-factoring-header-btn
        :create-permission-name="'create clean overdraft'"
        :create-route="route('factoring.contracts.create', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id])"
        :create-label="__('New Contract')"
    />

    <div class="kt-portlet__body">
        <div class="tab-content kt-margin-t-20">
            <div class="kt-portlet kt-portlet--mobile">
                <x-table-title.title :title="__('Factoring Contracts')" :icon="'fa-file-contract'">
                    <x-export-factoring-contracts
                        :factoring-company="$factoringCompany"
                        :search-fields="$searchFields"
                        :money-received-type="'factoring-contracts'"
                        :has-search="1"
                        :has-batch-collection="0"
                    />
                </x-table-title.title>
                <div class="kt-portlet__body">
                    <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                        <thead>
                            <tr class="table-standard-color">
                                <th>{{ __('#') }}</th>
                                <th>{{ __('Start Date') }}</th>
                                <th>{{ __('End Date') }}</th>
                                <th>{{ __('Recourse Type') }}</th>
                                <th>{{ __('Currency') }}</th>
                                <th>{{ __('Limit') }}</th>
                                <th>{{ __('Borrowing Rate %') }}</th>
                                <th>{{ __('Margin Rate %') }}</th>
                                <th>{{ __('Interest Rate %') }}</th>
                                <th>{{ __('Control') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contracts as $index => $contract)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="text-nowrap">{{ $contract->getContractStartDateFormatted() }}</td>
                                <td class="text-nowrap">{{ $contract->getContractEndDateFormatted() }}</td>
                                <td>{{ $contract->getRecourseTypeLabel() }}</td>
                                <td class="text-uppercase">{{ $contract->getCurrency() }}</td>
                                <td class="text-transform">{{ $contract->getLimitFormatted() }}</td>
                                <td class="bank-max-width">{{ $contract->getBorrowingRateFormatted() . ' %' }}</td>
                                <td class="text-nowrap">{{ $contract->getMarginRateFormatted() . ' %' }}</td>
                                <td>{{ $contract->getInterestRateFormatted() . ' %' }}</td>
                                <td class="kt-datatable__cell--left kt-datatable__cell">
                                    <span style="overflow: visible; position: relative; width: 110px;">
                                        @if(auth()->user()->can('update clean overdraft'))
                                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit') }}" href="{{ route('factoring.contracts.edit', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id, 'factoringContract' => $contract->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                        @endif
                                        @if(auth()->user()->can('delete clean overdraft'))
                                        <a data-toggle="modal" data-target="#delete-factoring-contract-id-{{ $contract->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
                                        <div class="modal fade" id="delete-factoring-contract-id-{{ $contract->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content">
                                                    <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('factoring.contracts.destroy', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id, 'factoringContract' => $contract->id]) }}" method="post">
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
@endsection
