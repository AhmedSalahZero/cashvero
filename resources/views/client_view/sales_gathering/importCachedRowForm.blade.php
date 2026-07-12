@extends('layouts.dashboard')
@section('css')
    <style>
        .money-flow-dark input,
        .money-flow-dark select,
        .money-flow-dark .dropdown-toggle.bs-placeholder {
            border: 1px solid #CCE2FD !important;
        }

        .money-flow-dark label {
            text-align: left !important;
        }

        .money-flow-dark .kt-portlet {
            overflow: visible !important;
        }

        .money-flow-dark .kt-portlet .kt-portlet__head {
            border-bottom-color: #1490a833 !important;
        }
    </style>
@endsection
@section('sub-header')
    @php $modelDisplayName = $modelName === 'ContractLoanSchedule' ? __('Contract Leasing Schedule') : camelToTitle($modelName); @endphp
    {{ $modelDisplayName }} — {{ __('Edit Row') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{ $modelDisplayName }} — {{ __('Edit Row') }}
                    </h3>
                </div>
            </div>
        </div>

        <form class="kt-form kt-form--label-right" method="POST"
            action="{{ route('salesGatheringTest.updateCachedRow', array_merge(['company' => $company->id, 'model' => $modelName, 'rowId' => $rowId], $modelName == 'ContractLoanSchedule' && $loanId ? ['leasing_contract_id' => $loanId] : ($loanId ? ['medium_term_loan_id' => $loanId] : []))) }}">
            @csrf
            @method('PUT')
            <div class="kt-portlet">
                <div class="kt-portlet__body">
                    <div class="row">
                        @foreach ($exportableFields as $fieldName => $label)
                            @php
                                $fieldMeta = \App\Helpers\HGlobal::getFieldTypeAndClassFromTitle($modelName, $label);
                                $inputType = $fieldMeta['type'] ?? 'text';
                                $inputClass = trim(($fieldMeta['class'] ?? '') . ' form-control');
                                $value = $row[$fieldName] ?? '';
                                if ($inputType === 'date' && $value) {
                                    try {
                                        $value = \Carbon\Carbon::parse($value)->format('Y-m-d');
                                    } catch (\Exception $e) {
                                        $value = $row[$fieldName] ?? '';
                                    }
                                }
                            @endphp
                            <div class="form-group col-md-6">
                                <label>{{ __($label) }}</label>
                                @if ($inputType === 'select')
                                    <select name="{{ $fieldName }}" class="{{ trim($inputClass . ' select2-select') }}" data-live-search="true" data-actions-box="true">
                                        @foreach (($fieldMeta['options'] ?? []) as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @if((string) $optionValue === (string) $value) selected @endif>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="{{ $inputType }}"
                                        name="{{ $fieldName }}"
                                        value="{{ $value }}"
                                        class="{{ $inputClass }}"
                                        placeholder="{{ __($label) }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <x-submitting />
        </form>
    </div>
</div>
</div>
@endsection
