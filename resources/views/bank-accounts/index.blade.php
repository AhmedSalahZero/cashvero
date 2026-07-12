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

    .money-flow-dark .kt-portlet__body {
        padding-top: 0 !important;
    }

    .money-flow-dark input.form-control[disabled]:not(.ignore-global-style) {
        background-color: var(--readonly-bg) !important;
        color: var(--text-primary) !important;
        font-weight: bold !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Bank Accounts' ) }} [{{ $financialInstitution->getName() }}]
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <x-back-to-bank-header-btn
	:create-permission-name="''" :create-route="''"
	></x-back-to-bank-header-btn>
    <div style="padding-top:0 !important " class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == 'bank-accounts' ?'active':'' }}" id="bank" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-one-date :filterDate="$filterDate"></x-table-title.with-one-date>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Balance') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allBankAccounts as $index=>$bankAccounts)
                                @foreach($bankAccounts as $bankAccount)
                                @php
                                    $accountModelName = class_basename($bankAccount);
                                    $lockModalId = $accountModelName . '-' . $bankAccount->id;
                                    $accountTypeId = $accountTypesByModel[$accountModelName] ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
								
                                    <td class="text-left">{{ $bankAccount->getType() }}</td>
                                    <td class="text-nowrap">{{ $bankAccount->getAccountNumber() }}</td>
                                    <td>{{ $bankAccount->getCurrencyFormatted() }}</td>
								
                                    <td>{{ $bankAccount->getLastAmountFormatted($company->id , $bankAccount->getCurrency(),$bankAccount->getFinancialInstitutionId(),$bankAccount->getAccountNumber()) }}</td>
							
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @if($bankAccount instanceof \App\Models\FinancialInstitutionAccount)
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.financial.institutions.account',['company'=>$company->id , 'financialInstitutionAccount'=>$bankAccount->id ]) }}"><i class="fa fa-pen-alt"></i></a>

                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $bankAccount->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            @endif

                                            @if(method_exists($bankAccount, 'isActive') && $accountTypeId)
                                            @include('components.bank-account-lock-button', [
                                                'bankAccount' => $bankAccount,
                                                'company' => $company,
                                                'accountTypeId' => $accountTypeId,
                                            ])
                                            @endif

                                            @if($bankAccount instanceof \App\Models\FinancialInstitutionAccount)
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $bankAccount->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('delete.financial.institutions.account',['company'=>$company->id,'financialInstitutionAccount'=>$bankAccount->id]) }}" method="post">
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

</script>
<script>


</script>



{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js') }}" type="text/javascript">
</script> --}}

{{-- <script>
    $(function() {
        $('#firstColumnId').trigger('change');
    })

</script> --}}

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
        if (searchFieldName === 'start_date') {
            modal.find('.data-type-span').html('[ {{ __("Start Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'end_date') {
            modal.find('.data-type-span').html('[ {{ __("End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        }
        //else if(searchFieldName === 'balance_date') {
        //     modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
        //     $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        // }
        else {
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
