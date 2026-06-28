@extends('layouts.dashboard')
@php
use App\Models\OdooExpense ;
@endphp
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
    input[type="checkbox"] {
        cursor: pointer;
    }

    th {
        background-color: #0742A6;
        color: white;
    }

    .bank-max-width {
        max-width: 200px !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style) {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Approved Odoo Expense') }}
@endsection
@section('content')

<div class="kt-portlet kt-portlet--tabs">
    {{-- <x-back-to-bank-header-btn :create-permission-name="'create medium term loan'" :create-route="route('loans.create',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,OdooExpense::APPROVED])"></x-back-to-bank-header-btn> --}}


    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            @php
            $currentType = OdooExpense::APPROVED ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Approved Odoo Expense')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-odoo-expenses :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="#" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Bank/Cash Name') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Total') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Payment') }}</th>
                                    {{-- <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Limit') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Borrowing Rate') }}</th>
                                    <th>{{ __('Margin Rate') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Installment Interval') }}</th> --}}
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="text-nowrap">{{ $model->employee()->getName() }}</td>
                                    <td class="text-nowrap">{{ $model->getName() }}</td>
                                    <td class="text-nowrap">{{ $model->getBankName() }}</td>
                                    <td class="text-nowrap">{{ $model->getAccountNumber() }}</td>
                                    <td class="text-nowrap">{{ $model->getTotal() }}</td>
                                    <td class="text-nowrap">{{ $model->getState() }}</td>
                                    <td class="text-nowrap">{{ $model->getPaymentStatus() }}</td>
                                    {{-- <td>{{ $model->getStartDateFormatted() }}</td>
                                    <td>{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getLimitFormatted() }}</td>
                                    <td>{{ $model->getAccountNumber() }}</td>
                                    <td>{{ $model->getBorrowingRateFormatted() }}</td>
                                    <td>{{ $model->getMarginRateFormatted() }}</td> --}}
                                    {{-- <td class="text-uppercase">{{ $model->getDurationFormatted() }}</td>
                                    <td class="text-transform">{{ $model->getPaymentInstallmentIntervalFormatted() }}</td> --}}
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            {{-- @if(hasAuthFor('create medium term loan')) --}}
                                            @if($model->getPaymentStatus() == 'not_paid')
                                            <a data-toggle="modal" data-target="#pay-modal{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Paid') }}" href="#"><i class="fa fa-dollar pl-2"></i> <i class="fa fa-dollar-sign ml-1 pr-2"></i> </a>
                                            <div class="modal fade text-left" id="pay-modal{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog  modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('odoo-expenses.mark.as.paid',['company'=>$company->id]) }}" method="post">
                                                            @csrf
                                                            <input type="hidden" name="id" value="{{ $model->id }}">
                                                            {{-- <input type="hidden" name="odoo_id" value="{{ $model->getOdooId() }}"> --}}
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Mark As Paid') }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-12">

                                                                        <label>{{__('Payment Date')}}</label>
                                                                        <div class="kt-input-icon">
                                                                            <div class="input-group date">
                                                                                <input required type="text" name="payment_date" value="{{ formatDateForDatePicker( now()->format('Y-m-d') ) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                                                                <div class="input-group-append">
                                                                                    <span class="input-group-text">
                                                                                        <i class="la la-calendar-check-o"></i>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>


                                                                </div>

                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											@elseif($model->cashExpense) 
											<a type="button" class="btn btn-sm btn-brand btn-elevate btn-pill text-white"" title="{{ __('Allocate') }}" href="{{ route('cash.expense.allocate',['company'=>$company->id,'cashExpense'=>$model->cashExpense->id]) }}">
												{{ __('Allocate') }}
											</a> 
											@endif

                                            {{-- @endif  --}}
                                            {{-- @if(hasAuthFor('update medium term loan'))
                                             
                                            @endif --}}
                                            {{-- @if(hasAuthFor('delete medium term loan')) --}}
                                            {{-- <a data-toggle="modal" data-target="#delete-expense-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-expense-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('odoo-expenses.destroy',['company'=>$company->id,'odooExpense'=>$model->id ]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
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
                                            </div> --}}
                                            {{-- @endif  --}}
                                        </span>
                                    </td>
                                </tr>
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
