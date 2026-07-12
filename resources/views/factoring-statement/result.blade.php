@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
<style>
    .money-flow-dark .table tbody tr,
    .money-flow-dark .table tbody tr td {
        background: transparent !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Factoring Statement') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <x-factoring-statement-tabs :company="$company" active="statement" />
<div class="kt-portlet">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title text-primary">
                {{ __('Factoring Statement') }} — {{ $factoringCompany->getName() }}
                | {{ strtoupper($currency) }}
                | {{ $contract->getContractStartDateFormatted() }} — {{ $contract->getContractEndDateFormatted() }}
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <table class="table table-bordered table-hover table-checkable text-center">
            <thead>
                <tr class="table-standard-color">
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Debit') }}</th>
                    <th>{{ __('Credit') }}</th>
                    <th>{{ __('End Balance') }}</th>
                    <th>{{ __('Comment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                    <td>{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                    <td>{{ number_format($row['end_balance'], 2) }}</td>
                    <td class="text-left">{{ $row['comment'] }}</td>
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
