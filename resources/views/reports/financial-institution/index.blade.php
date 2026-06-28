@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
.money-flow-dark .kt-portlet__body{
	padding-top:0 !important;
}
.money-flow-dark .hover-color-black:hover i{
	color:black !important;
}
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
{{ __('Financial Institutions') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !Request('active') || Request('active') == 'bank' ?'active':'' }}" href="{{ route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']) }}" role="tab">
                        <i class="kt-menu__link-icon fa fa-university"></i> {{ __('Banks Table') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request('active') == 'leasing_companies' ? 'active' : '' }}" href="{{ route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies']) }}" role="tab">
                        <i class="kt-menu__link-icon fa fa-building"></i> {{ __('Leasing Companies') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request('active') == 'factoring_companies' ? 'active' : '' }}" href="{{ route('view.financial.institutions', ['company' => $company->id, 'active' => 'factoring_companies']) }}" role="tab">
                        <i class="kt-menu__link-icon fa fa-handshake"></i> {{ __('Factoring Companies') }}
                    </a>
                </li>
            </ul>

           <div class="flex-tabs">
		   @if(!Request('active') || Request('active') == 'bank')
				@if(hasAuthFor('create financial institutions'))
				<a href="{{ route('create.financial.institutions', ['company' => $company->id]) }}" class="btn active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New Bank') }}
				</a>
				@endif
		   @elseif(Request('active') == 'leasing_companies')
				@if(hasAuthFor('create financial institutions'))
				<a href="{{ route('leasing.companies.create', ['company' => $company->id]) }}" class="btn active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New Leasing Company') }}
				</a>
				@endif
		   @elseif(Request('active') == 'factoring_companies')
				@if(hasAuthFor('create financial institutions'))
				<a href="{{ route('factoring.companies.create', ['company' => $company->id]) }}" class="btn active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New Factoring Company') }}
				</a>
				@endif
		   @endif
           </div>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">

            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == 'bank' ?'active':'' }}" id="bank" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
					<x-table-title.title :title="__('Banks Table')" :icon="'fa-university'">
                        <x-export-financial-institution :search-fields="$companiesSearchFields" :money-received-type="'bank'" :has-search="1" :has-batch-collection="0" :banks="$banks" :selectedBanks="$selectedBanks" href="{{route('create.financial.institutions',['company'=>$company->id])}}" />
					</x-table-title.title>
                    {{-- <div class="kt-portlet__head kt-portlet__head--lg p-0">
                        <div class="kt-portlet__head-label">
                            <span class="kt-portlet__head-icon">
                                <i class="kt-font-dark btn-outline-hover-danger kt-menu__link-icon fa fa-university "></i>
                            </span>
                            <h3 class="kt-portlet__head-title">
                            </h3>
                        </div>
                    </div> --}}
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th class="bank-max-width">{{ __('Bank') }}</th>
                                    <th>{{ __('Branch Name') }}</th>
                                    <th>{{ __('Company Account Number') }}</th>
                              
                                    <th>{{ __('Control') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialInstitutionsBanks as $index=>$financialInstitutionBank)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td class="bank-max-width">{{ $financialInstitutionBank->getBankName() }}</td>
                                    <td class="text-nowrap">{{ $financialInstitutionBank->getBranchName() }}</td>
                                    <td>{{ $financialInstitutionBank->getCompanyAccountNumber() }}</td>
    
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                     

                                        <span style="overflow: visible; position: relative; width: 110px;">
                                        @include('reports.financial-institution.dropdown-actions')
                                         
                                        </span>
                                    </td>
									
									<td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                     

                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            <a type="button" class="btn btn-success btn-outline-hover-success btn-icon hover-color-black"  title="{{ __('Show All Account') }}" href="{{ route('view.all.bank.accounts',['company'=>$company->id,'financialInstitution'=>$financialInstitutionBank->id]) }}"><i class="fa fa-eye"></i></a>
											@if(hasAuthFor('update financial institutions'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.financial.institutions',['company'=>$company->id,'financialInstitution'=>$financialInstitutionBank->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete financial institutions'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $financialInstitutionBank->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $financialInstitutionBank->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.financial.institutions',['company'=>$company->id,'financialInstitution'=>$financialInstitutionBank->id]) }}" method="post">
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

            <div class="tab-pane {{ Request('active') == 'leasing_companies' ? 'active' : '' }}" id="leasing_companies" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.title :title="__('Leasing Companies Table')" :icon="'fa-building'">
                        <x-export-financial-institution :search-fields="$leasingInstitutionCompaniesSearchField" :money-received-type="'leasing_companies'" :has-search="1" :has-batch-collection="0" :banks="$banks" :selectedBanks="$selectedBanks" href="{{ route('leasing.companies.create', ['company' => $company->id]) }}" />
                    </x-table-title.title>
                    <div class="kt-portlet__body">
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="align-middle">{{ __('#') }}</th>
                                    <th class="align-middle">{{ __('Name') }}</th>
                                    <th class="align-middle">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leasingCompanies as $index => $leasingCompany)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $leasingCompany->getName() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell">
                                        <span style="overflow: visible; position: relative; width: 140px;">
                                            @if(hasAuthFor('view medium term loan'))
                                            <a type="button" class="btn btn-success btn-outline-hover-success btn-icon hover-color-black" title="{{ __('Contracts') }}" href="{{ route('leasing.contracts.index', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]) }}"><i class="fa fa-file-contract"></i></a>
                                            @endif
                                            @if(hasAuthFor('update financial institutions'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit') }}" href="{{ route('leasing.companies.edit', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(hasAuthFor('delete financial institutions'))
                                            <a data-toggle="modal" data-target="#delete-leasing-company-id-{{ $leasingCompany->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-leasing-company-id-{{ $leasingCompany->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('leasing.companies.destroy', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">{{ __('Do You Want To Delete This Item ?') }}</h5>
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
                    </div>
                </div>
            </div>

            <div class="tab-pane {{ Request('active') == 'factoring_companies' ? 'active' : '' }}" id="factoring_companies" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.title :title="__('Factoring Companies Table')" :icon="'fa-handshake'">
                        <x-export-financial-institution :search-fields="$factoringInstitutionCompaniesSearchField" :money-received-type="'factoring_companies'" :has-search="1" :has-batch-collection="0" :banks="$banks" :selectedBanks="$selectedBanks" href="{{ route('factoring.companies.create', ['company' => $company->id]) }}" />
                    </x-table-title.title>
                    <div class="kt-portlet__body">
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="align-middle">{{ __('#') }}</th>
                                    <th class="align-middle">{{ __('Name') }}</th>
                                    <th class="align-middle">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($factoringCompanies as $index => $factoringCompany)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $factoringCompany->getName() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell">
                                        <span style="overflow: visible; position: relative; width: 140px;">
                                            @if(hasAuthFor('view clean overdraft'))
                                            <a type="button" class="btn btn-success btn-outline-hover-success btn-icon hover-color-black" title="{{ __('Contracts') }}" href="{{ route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]) }}"><i class="fa fa-file-contract"></i></a>
                                            @endif
                                            @if(hasAuthFor('update financial institutions'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit') }}" href="{{ route('factoring.companies.edit', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(hasAuthFor('delete financial institutions'))
                                            <a data-toggle="modal" data-target="#delete-factoring-company-id-{{ $factoringCompany->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="{{ __('Delete') }}" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-factoring-company-id-{{ $factoringCompany->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('factoring.companies.destroy', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">{{ __('Do You Want To Delete This Item ?') }}</h5>
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
                    </div>
                </div>
            </div>

            @foreach($financialInstitutionCompanies as $id => $companyArr)

            <div class="tab-pane {{ Request('active') == $id ? 'active':''  }}" id="{{ $id }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__head kt-portlet__head--lg p-0">
                        <div class="kt-portlet__head-label">
                            <span class="kt-portlet__head-icon">
                                <i class="kt-font-secondary btn-outline-hover-danger fa fa-layer-group"></i>
                            </span>
                            <h3 class="kt-portlet__head-title">
                                {{ $companyArr['title'] }}
                            </h3>
                        </div>
                        {{-- Export --}}
                        <x-export-financial-institution :search-fields="$companyArr['searchFieldsArr']" :money-received-type="$id" :has-search="1" :has-batch-collection="0" :banks="$banks" :selectedBanks="$selectedBanks" href="{{route('create.financial.institutions',['company'=>$company->id])}}" />

                    </div>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="align-middle">{{ __('#') }}</th>
                                    <th class="align-middle">{{ __('Name') }}</th>
                                    <th class="align-middle">{{ __('Branch Name') }}</th>
                                    <th class="align-middle">{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($companyArr['financialInstitutionCompanies'] as $index=>$financialInstitutionCompany)
                                <tr>
                                    <td>{{ $index+1 }}</td>
                                    <td>{{ $financialInstitutionCompany->getName() }}</td>
                                    <td>{{ $financialInstitutionCompany->getBranchName() }}</td>
								
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
											@include('reports.financial-institution.dropdown-actions')
											@if(hasAuthFor('update financial institutions'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.financial.institutions',['company'=>$company->id,'financialInstitution'=>$financialInstitutionCompany->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete financial institutions'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $financialInstitutionCompany->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $financialInstitutionCompany->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.financial.institutions',['company'=>$company->id,'financialInstitution'=>$financialInstitutionCompany->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            {{-- <div class="modal-body">
                                                            ...
                                                        </div> --}}
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
            @endforeach





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
	$('button[data-dismiss-modal="inner-modal"]').click(function () {
		$(this).closest('.modal').modal('hide');
	});

    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
            modal.find('.data-type-span').html('[ {{ __("Created At") }} ]')
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
