@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet { overflow: visible !important; }
    .money-flow-dark .kt-portlet__body { padding-top: 0 !important; }
</style>
@endsection
@section('sub-header')
{{ __('Factoring Without Recourse') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('factoring.without-recourse.index', ['company' => $company->id]) }}" role="tab">
                        <i class="fa fa-handshake"></i> {{ __('Factoring Without Recourse') }}
                    </a>
                </li>
            </ul>
            @if(hasAuthFor('create supplier payment'))
            <div class="flex-tabs">
                <a href="{{ route('factoring.without-recourse.create', ['company' => $company->id]) }}" class="btn active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i> {{ __('Create New') }}
                </a>
            </div>
            @endif
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="kt-portlet kt-portlet--mobile">
            <x-table-title.title :title="__('Factoring Transactions')" :icon="'fa-handshake'">
                <x-export-factoring-transactions :search-fields="$searchFields" :money-received-type="'factoring-without-recourse'" :has-search="1" :has-batch-collection="0" />
            </x-table-title.title>
            <div class="kt-portlet__body">
                <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                    <thead>
                        <tr class="table-standard-color">
                            <th>{{ __('#') }}</th>
                            <th>{{ __('Factoring Date') }}</th>
                            <th>{{ __('Factoring Company') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Invoice Number') }}</th>
                            <th>{{ __('Currency') }}</th>
                            <th>{{ __('Factoring Amount') }}</th>
                            <th>{{ __('Received Amount') }}</th>
                            <th>{{ __('Bank') }}</th>
                            <th>{{ __('Account Number') }}</th>
                            <th>{{ __('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $index => $transaction)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="text-nowrap">{{ $transaction->getFactoringDateFormatted() }}</td>
                            <td>{{ $transaction->factoringCompany?->getName() }}</td>
                            <td>{{ $transaction->customer?->getName() }}</td>
                            <td>{{ $transaction->customerInvoice?->invoice_number }}</td>
                            <td class="text-uppercase">{{ $transaction->invoice_currency }}</td>
                            <td>{{ number_format((float) $transaction->factoring_amount, 2) }}</td>
                            <td>{{ number_format((float) $transaction->received_amount, 2) }}</td>
                            <td>{{ $transaction->financialInstitution?->getName() }}</td>
                            <td>{{ $transaction->account_number }}</td>
                            <td>
                                @if(hasAuthFor('delete supplier payment'))
                                <a data-toggle="modal" data-target="#delete-factoring-transaction-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
                                <div class="modal fade" id="delete-factoring-transaction-{{ $transaction->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <form action="{{ route('factoring.without-recourse.destroy', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" method="post">
                                                @csrf
                                                @method('delete')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
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
@endsection
