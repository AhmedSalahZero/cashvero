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
{{ __('Customer Aging Form') }}
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">



        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('result.customer.aging.analysis',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">

                <div class="kt-portlet__body">
                


                    <div class="form-group row">
					
					 <div class="col-md-4">
                            <label>{{ __('Aging Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" id="sales_channels">
                                    <input type="date" class="form-control" name="again_date" value="{{ now() }}">
                                </div>
                            </div>
                        </div>
						
						
						<div class="col-md-4">
                            <label>{{ __('Select Business Unit') }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" id="sales_persons">
                                    <select data-live-search="true" data-actions-box="true" name="sales_persons[]" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select ajax-sales-person" multiple>
                                         @foreach($businessUnits as $businessUnit)
                                        <option value="{{ $businessUnit }}"> {{ __($businessUnit) }}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
						

                        <div class="col-md-4">
                            <label>{{ __('Select Customers') }} <span class="multi_selection"></span>  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" id="customers">
                                    <select data-live-search="true" data-actions-box="true" name="customers[]" required class="form-control kt-bootstrap-select select2-select kt_bootstrap_select ajax-customer-name" multiple>
									{{-- <option value=""></option> --}}
                                        {{-- @foreach($customerNames as $customerName) --}}
                                        {{-- <option value="{{ $customerName }}"> {{ __($customerName) }}</option> --}}
                                        {{-- @endforeach --}}
                                    </select>
                                </div>
                            </div>
                        </div>
						
						
						
						
                <x-submitting />
						
						
						
				
						
                        
						
                    </div>

                </div>
                {{-- @d($name_of_selector_label) --}}
            </div>





        </form>

        <!--end::Form-->

        <!--end::Portlet-->
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
	$('.ajax-sales-person').on('change',function(){
		const salesPersons = $(this).val();

		$.ajax({
			url:"{{ route('get.customers.from.sales.persons',['company'=>$company->id]) }}",
			data:{
				salesPersons
			},
			type:'get'
		}).then(function(res){
			let select = '<select data-live-search="true" data-actions-box="true" name="customers[]" required class="form-control kt-bootstrap-select select2-select kt_bootstrap_select ajax-customer-name" multiple > ';
			const salesPersons = res.data.customer_names ;
			let options = '';
			for(index in salesPersons  ){
				options += '<option value="'+ salesPersons[index].customer_name +'">'+ salesPersons[index].customer_name +'</option>'
			}
			select = select + options  +  ' </select>';
			$('#customers').empty().append(select);
			reinitializeSelect2()
			
			//$('.ajax-customer-name').empty().append(options).selectpicker("refresh");

		})
	})
</script>
@endsection
