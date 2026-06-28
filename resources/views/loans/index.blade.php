@extends('layouts.dashboard')
@php
use App\Models\MediumTermLoan ;
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
{{ __('Medium Term Loan') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <x-back-to-bank-header-btn :create-permission-name="'create medium term loan'" :create-route="route('loans.create',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,MediumTermLoan::RUNNING])"></x-back-to-bank-header-btn>
	
	
	<div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            @php
            $currentType = MediumTermLoan::RUNNING ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-end-date :type="$currentType" :title="__('Medium Term Loan')" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-loans  :financialInstitution="$financialInstitution" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('loans.create',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])}}" />
                    </x-table-title.with-end-date>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Start Date') }}</th>
                                    <th>{{ __('End Date') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Limit') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Borrowing Rate') }}</th>
                                    <th>{{ __('Margin Rate') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Installment Interval') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getName() }}</td>
                                    <td>{{ $model->getStartDateFormatted() }}</td>
                                    <td>{{ $model->getEndDateFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getLimitFormatted() }}</td>
                                    <td>{{ $model->getAccountNumber() }}</td>
                                    <td>{{ $model->getBorrowingRateFormatted() }}</td>
                                    <td>{{ $model->getMarginRateFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getDurationFormatted() }}</td>
                                    <td class="text-transform">{{ $model->getPaymentInstallmentIntervalFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										@if(hasAuthFor('create medium term loan'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Upload Loan Schedule & Apply Payments') }}" href="{{ route('view.uploading',['company'=>$company->id,'loanId'=>$model->id,'model'=>'LoanSchedule']) }}"><i class="fa fa-upload pl-2"></i> <i class="fa fa-dollar-sign ml-1 pr-2"></i> </a>
											@endif 
											@if(hasAuthFor('update medium term loan'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('loans.edit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'mediumTermLoan'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete medium term loan'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('loans.destroy',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'mediumTermLoan'=>$model->id ]) }}" method="post">
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
                                            </div>
											@endif 
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
