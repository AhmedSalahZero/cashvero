@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet { overflow: visible !important; }
    .money-flow-dark .kt-portlet__body { padding-top: 0 !important; }
    #factoring-without-recourse-modals .modal-dialog {
        max-width: min(560px, 95vw);
        width: 100%;
        margin-left: auto;
        margin-right: auto;
    }
    #factoring-without-recourse-modals .modal-lg {
        max-width: min(720px, 95vw);
    }
    #factoring-without-recourse-modals .modal-content {
        overflow: hidden;
    }
    #factoring-without-recourse-modals .modal-title,
    #factoring-without-recourse-modals .modal-body,
    #factoring-without-recourse-modals .modal-body p {
        overflow-wrap: anywhere;
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
    }
</style>
@endsection
@section('sub-header')
{{ __('Factoring Without Recourse') }}
@endsection
@section('content')
<div class="money-flow-dark" id="factoring-without-recourse-index"
    data-account-numbers-url="{{ url(app()->getLocale() . '/' . $company->id . '/money-received/get-account-numbers-based-on-account-type') }}">
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
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Bank') }}</th>
                            <th>{{ __('Account Number') }}</th>
                            <th>{{ __('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $index => $transaction)
                        @php
                            $differenceAmount = $transaction->getDifferenceAmount();
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="text-nowrap">{{ $transaction->getFactoringDateFormatted() }}</td>
                            <td>{{ $transaction->factoringCompany?->getName() }}</td>
                            <td>{{ $transaction->customer?->getName() }}</td>
                            <td>{{ $transaction->customerInvoice?->invoice_number }}</td>
                            <td class="text-uppercase">{{ $transaction->invoice_currency }}</td>
                            <td>{{ number_format((float) $transaction->factoring_amount, 2) }}</td>
                            <td>{{ number_format((float) $transaction->received_amount, 2) }}</td>
                            <td>
                                @if($transaction->is_settled)
                                    {{ __('Settled') }}
                                    @if($transaction->settled_at)
                                        <div class="text-muted small">{{ \Carbon\Carbon::make($transaction->settled_at)->format('Y-m-d') }}</div>
                                    @endif
                                @else
                                    {{ __('Pending') }}
                                @endif
                            </td>
                            <td>{{ $transaction->financialInstitution?->getName() }}</td>
                            <td>{{ $transaction->account_number }}</td>
                            <td>
                                @if(hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment'))
                                    @if(!$transaction->is_settled)
                                    <a href="{{ route('factoring.without-recourse.edit', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit') }}"><i class="fa fa-edit"></i></a>
                                    @endif
                                @endif
                                @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && !$transaction->is_settled)
                                <a data-toggle="modal" data-target="#settle-factoring-transaction-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-success btn-icon" title="{{ __('Mark As Settled') }}" href="#"><i class="fa fa-check"></i></a>
                                @endif
                                @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $transaction->is_settled)
                                <a data-toggle="modal" data-target="#revert-factoring-transaction-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="{{ __('Reset') }}" href="#"><i class="fa fa-undo"></i></a>
                                @endif
                                @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $differenceAmount > 0 && !$transaction->is_difference_received)
                                <a data-toggle="modal" data-target="#difference-received-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-info btn-icon" title="{{ __('Record Difference Received') }}" href="#"><i class="fa fa-hand-holding-usd"></i></a>
                                @endif
                                @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $transaction->is_difference_received)
                                <a data-toggle="modal" data-target="#revert-difference-received-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="{{ __('Revert Difference Received') }}" href="#"><i class="fa fa-undo"></i></a>
                                @endif
                                @if(hasAuthFor('delete supplier payment'))
                                <a data-toggle="modal" data-target="#delete-factoring-transaction-{{ $transaction->id }}" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
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

<div id="factoring-without-recourse-modals">
    @foreach($transactions as $transaction)
        @php
            $differenceAmount = $transaction->getDifferenceAmount();
        @endphp
        @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && !$transaction->is_settled)
        <div class="modal fade" id="settle-factoring-transaction-{{ $transaction->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form action="{{ route('factoring.without-recourse.mark-as-settled', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" method="post">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Mark As Settled') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body text-left">
                            <div class="form-group mb-0">
                                <label>{{ __('Date') }} @include('star')</label>
                                <input type="date" name="settlement_date" required class="form-control"
                                    max="{{ now()->format('Y-m-d') }}"
                                    value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $transaction->is_settled)
        <div class="modal fade" id="revert-factoring-transaction-{{ $transaction->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form action="{{ route('factoring.without-recourse.revert-settlement', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" method="post">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Reset Settlement') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">{{ __('Do you want to revert the settlement and remove the related factoring statement entry?') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-warning">{{ __('Confirm Reset') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $differenceAmount > 0 && !$transaction->is_difference_received)
        <div class="modal fade difference-received-modal" id="difference-received-{{ $transaction->id }}" tabindex="-1" role="dialog" aria-hidden="true"
            data-invoice-currency="{{ $transaction->invoice_currency }}"
            data-default-bank-id="{{ $transaction->financial_institution_id }}"
            data-default-account-type-id="{{ $transaction->account_type_id }}"
            data-default-account-number="{{ $transaction->account_number }}">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <form action="{{ route('factoring.without-recourse.mark-difference-received', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" method="post">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Record Difference Received') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body text-left">
                            <div class="alert alert-light border mb-4">
                                <strong>{{ __('Difference Amount') }}:</strong>
                                {{ number_format($differenceAmount, 2) }}
                                <span class="text-uppercase">{{ $transaction->invoice_currency }}</span>
                                <div class="text-muted small mt-1">{{ __('Confirm that you have received this amount.') }}</div>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Date') }} @include('star')</label>
                                <input type="date" name="difference_received_date" required class="form-control"
                                    max="{{ now()->format('Y-m-d') }}"
                                    value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group">
                                <label>{{ __('Bank') }} @include('star')</label>
                                <select required name="financial_institution_id" class="form-control difference-bank" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($financialInstitutionBanks as $bank)
                                        <option value="{{ $bank->id }}" @selected((int) $transaction->financial_institution_id === (int) $bank->id)>{{ $bank->getName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Account Type') }} @include('star')</label>
                                <select required name="account_type_id" class="form-control difference-account-type" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($accountTypes as $accountType)
                                        <option value="{{ $accountType->id }}" @selected((int) $transaction->account_type_id === (int) $accountType->id)>{{ $accountType->getName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label>{{ __('Account Number') }} @include('star')</label>
                                <select required name="account_number" class="form-control difference-account-number" data-live-search="true">
                                    <option value="">{{ __('Select') }}</option>
                                    @if($transaction->account_number)
                                        <option value="{{ $transaction->account_number }}" selected>{{ $transaction->account_number }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-info">{{ __('Confirm') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        @if((hasAuthFor('create supplier payment') || hasAuthFor('update supplier payment')) && $transaction->is_difference_received)
        <div class="modal fade" id="revert-difference-received-{{ $transaction->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form action="{{ route('factoring.without-recourse.revert-difference-received', ['company' => $company->id, 'factoringTransaction' => $transaction->id]) }}" method="post">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Revert Difference Received') }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">{{ __('Do you want to revert the difference received and remove the related bank movement?') }}</p>
                            @if($transaction->difference_received_amount)
                                <p class="mb-0"><strong>{{ __('Difference Amount') }}:</strong> {{ number_format((float) $transaction->difference_received_amount, 2) }} <span class="text-uppercase">{{ $transaction->invoice_currency }}</span></p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-warning">{{ __('Confirm Reset') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        @if(hasAuthFor('delete supplier payment'))
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
    @endforeach
</div>

</div>
@endsection
@section('js')
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}"></script>
<script src="{{ url('custom/factoring-without-recourse-index.js') }}?v={{ time() }}"></script>
@endsection
