@extends('layouts.dashboard')
@php
use App\Models\MoneyReceived ;
@endphp
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
{{ $title }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == MoneyReceived::CONTRACTS_WITH_DOWN_PAYMENTS ?'active':'' }}" data-toggle="tab" href="#{{MoneyReceived::CONTRACTS_WITH_DOWN_PAYMENTS  }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ $tableTitle }}
                    </a>
                </li>




            </ul>

            {{-- <div class="flex-tabs">
                <a href="{{ route('loans.create',['company'=>$company->id,MoneyReceived::CONTRACTS_WITH_DOWN_PAYMENTS]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Create') }}
                </a>
            </div> --}}
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            @php
            $currentType = MoneyReceived::CONTRACTS_WITH_DOWN_PAYMENTS ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="$title" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        {{-- <x-export-contracts-with-down-payment   :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('view.down.payment.settlement',['company'=>$company->id,'moneyReceived'=>$model->moneyReceived->id])}}" /> --}}
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Down Payment Amount') }}</th>
                                    <th>{{ __('Settlement Amount') }}</th>
                                    <th>{{ __('Net Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Contract Name') }}</th>
                                    <th>{{ __('Contract Amount') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
							@php
								$index = 0 ;
							@endphp
                                @foreach($models[$currentType] as $moneyModel)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
										 {{-- -- {{ $moneyModel->id }} --}}
                                    </td>

                                    <td class="text-nowrap">{{ $moneyModel->getReceivingOrPaymentMoneyDateFormatted() }}</td>
                                    <td>{{ $moneyModel->getDownPaymentAmountFormatted() }}</td>
                                    <td>{{ $moneyModel->getTotalSettlementAmountForDownPaymentFormatted() }}</td>
                                    <td>{{ number_format($moneyModel->getTotalSettlementsNetBalanceForDownPayment()) }}</td>
                                    <td>{{  $moneyModel->currency }}</td>
                                    {{-- <td>{{ $moneyModel->getReceivingOrPaymentCurrency() }}</td> --}}
                                    <td>{{ $moneyModel->getContractName() }}</td>
                                    <td>{{ $moneyModel->getContractAmountFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Start Settlement') }}" href="{{ route('view.down.payment.settlement',['company'=>$company->id,'downPaymentId'=>$moneyModel->id,'modelType'=>$modelType]) }}"><i class="fa fa-dollar-sign"></i></a>
                                        
                                        </span>
                                    </td>
                                </tr>
								@php
									$index++;
								@endphp
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>




            <!--End:: Tab Content-->



            <!--End:: Tab Content-->
        </div>
    </div>
</div>
</div>

@endsection
@section('js')
<!--begin::Page Scripts(used by this page) -->
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>

<script>
    $(document).on('click', '.js-close-modal', function() {
        $(this).closest('.modal').modal('hide');
    })

</script>
<script>
    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'transfer_date') {
            modal.find('.data-type-span').html('[ {{ __("Transfer Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'contract_end_date') {
            modal.find('.data-type-span').html('[ {{ __("End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
            modal.find('.data-type-span').html('[ {{ __("Start Date") }} ]')
            $(modal).find('.search-field').prop('disabled', false);
        }
    })
    $(function() {

        $('.js-search-modal').trigger('change')

    })

</script>
@endsection
@push('js')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script> --}}
@endpush
