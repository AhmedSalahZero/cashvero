@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
.kt-portlet{
	overflow:visible !important ;
}
</style>
@endsection
@section('sub-header')
{{ $title }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">



        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('result.aging.analysis',['company'=>$company->id ,'modelType'=>$modelType ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">
                <div class="kt-portlet__body">
                    <div class="form-group row">
					 <div class="col-md-3 mb-4">
                            <label>{{ __('Aging Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" id="sales_channels">
                                    <input  type="date" class="form-control" name="again_date" value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                        </div>
						
						@if(count($businessUnits))
						<div class="col-md-3 mb-4">
                            <label>{{ __('Select Business Unit') }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" >
                                    <select  data-live-search="true" data-actions-box="true" name="business_units[]" class="form-control business-unit-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-refresh-customers" multiple>
                                         @foreach($businessUnits as $businessUnit)
                                        <option value="{{ $businessUnit }}"> {{ __($businessUnit) }}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
						@endif 
						@if(count($salesPersons))
						<div class="col-md-3 mb-4">
                            <label>{{ __('Select Sales Person') }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" >
                                    <select  data-live-search="true" data-actions-box="true" name="sales_persons[]" class="form-control sales-person-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-refresh-customers" multiple>
                                         @foreach($salesPersons as $salesPerson)
                                        <option value="{{ $salesPerson }}"> {{ __($salesPerson) }}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
						@endif 
						@if(count($businessSectors))
							<div class="col-md-3 mb-4">
                            <label>{{ __('Select Business Sectors') }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" >
                                    <select  data-live-search="true" data-actions-box="true" name="business_sectors[]" class="form-control business-sector-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-refresh-customers" multiple>
                                         @foreach($businessSectors as $businessSector)
                                        <option value="{{ $businessSector }}"> {{ __($businessSector) }}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
						@endif
						
					
						 <div class="col-md-3 mb-4">
                            <label>{{ __('Currency') }}   </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" >
                                    <select  data-live-search="true" data-actions-box="true" name="currency" required class="form-control currency-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-currency-name ajax-refresh-customers" >
										@foreach($currencies as $currencyName)
										<option value="{{ $currencyName }}">{{ touppercase($currencyName) }}</option>
										@endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-3 mb-4">
                            <label>{{ $customersOrSupplierText }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" >
                                    <select  data-live-search="true" data-actions-box="true" name="client_ids[]" required class="form-control customers-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-customer-name" multiple>
									{{-- @foreach($partners as $partner)
									<option value="{{ $partner->id }}">{{ $partner->getName() }}</option>
									@endforeach  --}}
                                    </select>
                                </div>
                            </div>
                        </div>
						
						     
						
						
						
						
						
                <x-submitting />
						
						
						
				
						
                        
						
                    </div>

                </div>
            </div>





        </form>

        <!--end::Form-->

        <!--end::Portlet-->
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
	
	$(document).on('change','select.ajax-refresh-customers',function(){
		const businessUnits = $('select.business-unit-js').val();
		const salesPersons = $('select.sales-person-js').val();
		const businessSectors = $('select.business-sector-js').val();
		const currencies = $('select.currency-js').val();

		$.ajax({
			url:"{{ route('get.customers.or.suppliers.from.business.units.currencies',['company'=>$company->id,'modelType'=>$modelType]) }}",
			data:{
				business_units:businessUnits,
				business_sectors:businessSectors,
				sales_persons:salesPersons,
				currencies,
				
			},
			type:'get'
		}).then(function(res){
			let currenciesOptions = '';
			for (var currencyName of res.data.currencies_names){
				currenciesOptions += `<option ${currencies == currencyName ? 'selected' : '' } value="${currencyName}">${currencyName}</option>`
			}
			let customersOptions = '';
	
			for (var customerId in res.data.customer_names){
				var customerName = res.data.customer_names[customerId];
				customersOptions += ` <option value="${customerId}">${customerName}</option> `
			}
			$('select.currency-js').selectpicker('destroy');
			$('select.currency-js').empty().append(currenciesOptions)
			$('select.currency-js').selectpicker("refresh")
			
			$('select.customers-js').selectpicker('destroy');
			$('select.customers-js').empty().append(customersOptions)
			$('select.customers-js').selectpicker("refresh")
		})
	})
	
	$('select.ajax-refresh-customers:eq(0)').trigger('change')
	
</script>
@endsection
