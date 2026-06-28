@extends('layouts.dashboard')
@php
use App\Models\Partner ;
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
{{ __('Partners') }}
@endsection
@section('content')
<div class="money-flow-dark">

<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == Partner::PARTNERS ?'active':'' }}" data-toggle="tab" href="#{{Partner::CUSTOMERS  }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Partners Table') }}
                    </a>
                </li>




            </ul>
			@if(!$company->hasOdooIntegrationCredentials())
            <div class="flex-tabs">
                <a href="{{ route('partners.create',['company'=>$company->id,Partner::PARTNERS]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Partner') }}
                </a>
            </div>
		@endif 
            
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">






            @php
            $currentType = Partner::PARTNERS ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Partners')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-customers :indexRouteName="$indexRouteName" :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('partners.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Supplier') }}</th>
                                    <th>{{ __('Subsidiary') }}</th>
                                    <th class="text-nowrap">{{ __('Other Partner') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Shareholder') }}</th>
                                    {{-- <th>{{ __('Created At') }}</th> --}}
									@if(hasAuthFor('update customers') 
									 // || hasAuthFor('delete customers')
									 )
                                    <th>{{ __('Control') }}</th>
									@endif 
                                </tr>
                            </thead>
                            <tbody>
							@php
								$order=1;
							@endphp
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $order++ }}
                                    </td>

                                    <td class="text-nowrap text-left">{{ $model->getName() }}</td>
									
                                    <td>
										<div>
											@if($model->isCustomer())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
									</td>
                                    <td>
										<div>
											@if($model->isSupplier())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
									</td>
                                     <td>
										<div>
											@if($model->isSubsidiaryCompany())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
									</td>
                                     <td>
										<div>
											@if($model->isOtherPartner())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
								</td>
                                    <td>
										<div>
											@if($model->isEmployee())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
								</td>
                                       <td>
										<div>
											@if($model->isShareholder())
											<i class="fas fa-check text-green"></i>
											@else 
											<i class="fas fa-times text-red"></i>
											@endif 
										</div>
								</td>
                                    {{-- <td>{{ $model->getCreatedAtFormatted() }}</td> --}}
									@if(hasAuthFor('update customers') 
									
									// || hasAuthFor('delete customers')
									 )
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
											@if(hasAuthFor('update customers'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('partners.edit',['company'=>$company->id,'partner'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
										
                                        </span>
                                    </td>
									@endif
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
            modal.find('.data-type-span').html('[ {{ __("Contract End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
       //     modal.find('.data-type-span').html('[ {{ __("Contract Start Date") }} ]')
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
