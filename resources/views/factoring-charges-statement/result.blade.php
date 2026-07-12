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
{{ __('Factoring Charges Statement') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <x-factoring-statement-tabs :company="$company" active="charges" />
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title text-primary">
                        {{ __('Factoring Charges Statement') }} — {{ $factoringCompany->getName() }}
                        | {{ strtoupper($currency) }}
                        @if($contract)
                            | {{ $contract->getContractStartDateFormatted() }} — {{ $contract->getContractEndDateFormatted() }}
                        @else
                            | {{ __('All Contracts') }}
                        @endif
                        | {{ $startDate }} — {{ $endDate }}
                    </h3>
                </div>
            </div>
            <div class="kt-portlet__body">
                <table class="table table-bordered table-hover table-checkable text-center">
                    <thead>
                        <tr class="table-standard-color">
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Charge Type') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Running Total') }}</th>
                            <th>{{ __('Comment') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['charge_type'] }}</td>
                            <td>{{ number_format($row['amount'], 2) }}</td>
                            <td>{{ number_format($row['running_total'], 2) }}</td>
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
