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
{{ __('Overdraft Against Commercial Paper '. $financialInstitution->getName()) }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
  <x-back-to-bank-header-btn :create-permission-name="'view overdraft against commercial paper'" :create-route="route('create.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])"></x-back-to-bank-header-btn>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == 'overdraft-against-commercial-paper' ?'active':'' }}" id="bank" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__head kt-portlet__head--lg p-0">
                        <div class="kt-portlet__head-label">
                            <span class="kt-portlet__head-icon">
                                <i class="kt-font-secondary btn-outline-hover-danger fa fa-layer-group"></i>
                            </span>
                            <h3 class="kt-portlet__head-title">
                                {{ __('Overdraft Against Commercial Paper Table') }}
                            </h3>
                        </div>
                        {{-- Export --}}
                        <x-export-overdraft-against-commercial-paper :financialInstitution="$financialInstitution" :search-fields="$searchFields" :money-received-type="'overdraft-against-commercial-paper'" :has-search="1" :has-batch-collection="0"   href="{{route('create.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])}}" />
                    </div>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th >{{ __('Start Date') }}</th>
                                    <th >{{ __('End Date') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Limit') }}</th>
                                    <th>{{ __('Borrowing Rate') }}</th>
                                    <th>{{ __('Margin Rate') }}</th>
                                    <th>{{ __('Intreset Rate') }}</th>
                                    {{-- <th>{{ __('Max Lending Limit Per Customer') }}</th> --}}
                                    {{-- <th>{{ __('Max Settlement Days') }}</th> --}}
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overdraftAgainstCommercialPapers as $index=>$overdraftAgainstCommercialPaper)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="text-nowrap">{{ $overdraftAgainstCommercialPaper->getContractStartDateFormatted() }}</td>
                                    <td class="text-nowrap">{{ $overdraftAgainstCommercialPaper->getContractEndDateFormatted() }}</td>
                                    <td>{{ $overdraftAgainstCommercialPaper->getAccountNumber() }}</td>
                                    <td class="text-uppercase">{{ $overdraftAgainstCommercialPaper->getCurrency() }}</td>
                                    <td class="text-transform">{{ $overdraftAgainstCommercialPaper->getLimitFormatted()  }}</td>
                                    <td class="bank-max-width">{{ $overdraftAgainstCommercialPaper->getBorrowingRateFormatted() . ' %'  }}</td>
                                    <td class="text-nowrap">{{ $overdraftAgainstCommercialPaper->getMarginRateFormatted() . ' %'  }}</td>
                                    <td>{{ $overdraftAgainstCommercialPaper->getInterestRateFormatted() . ' %'  }}</td>
                                    {{-- <td>{{ $overdraftAgainstCommercialPaper->getMaxLendingLimitPerCustomer() }}</td> --}}
                                    {{-- <td>{{ $overdraftAgainstCommercialPaper->getMaxSettlementDays() }}</td> --}}
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
									
										@include('reports.overdraft-against-commercial-paper.apply-rate')
                                        <span style="overflow: visible; position: relative; width: 110px;">
										@if(hasAuthFor('update overdraft against commercial paper'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'overdraftAgainstCommercialPaper'=>$overdraftAgainstCommercialPaper->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete overdraft against commercial paper'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $overdraftAgainstCommercialPaper->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $overdraftAgainstCommercialPaper->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'overdraftAgainstCommercialPaper'=>$overdraftAgainstCommercialPaper]) }}" method="post">
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
										
										 @foreach($overdraftAgainstCommercialPaper->rates as $index=>$rate)
											@include('reports.overdraft-against-commercial-paper.rate-modal')
										@endforeach
										
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
        if (searchFieldName === 'contract_start_date') {
            modal.find('.data-type-span').html('[ {{ __("Contract Start Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } 
		else if(searchFieldName === 'contract_end_date') {
            modal.find('.data-type-span').html('[ {{ __("Contract End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        }
		else if(searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        }
		else {
            modal.find('.data-type-span').html('[ {{ __("Contract Start Date") }} ]')
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
