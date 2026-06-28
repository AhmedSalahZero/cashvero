@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('LG By Beneficiary Name Report') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">


        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="get" action="{{ route('result.lg.by.beneficiary.name.report',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2 mb-4">
                            <label>{{ __('Renewal Date (Greater Than Or Equal Date)') }}</span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="start_date" value="{{ now() }}">
                                </div>
                            </div>
                        </div>

                        {{-- <div class="col-md-2 mb-4">
                            <label>{{ __('End Date') }}  </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="end_date" value="{{ now()->addYear() }}">
                                </div>
                            </div>
                        </div> --}}





                        <div class="col-md-2 mb-4">
                            <label>{{ __('Select Currency') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select js-get-beneficiary data-live-search="true" data-actions-box="true" id="currency_name" name="currency_name" required class="form-control ajax-current-currency  kt-bootstrap-select select2-select kt_bootstrap_select ">
                                        @foreach($currencies as $currency=>$currencyName)
                                        <option @if($currency == $selectedCurrency)  selected @endif value="{{ $currency }}">{{ touppercase($currencyName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label>{{__('Beneficiary')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select multiple data-live-search="true" data-actions-box="true" id="beneficiary-id"  name="beneficiary_id[]" class="form-control select2-select">
                                    </select>
                                </div>
                            </div>
                        </div>
                        

                        <div class="col-md-2">
                            <label>{{__('Status')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required  name="status" class="form-control ">
                                        <option value="running" selected>{{__('Running')}}</option>
                                        <option value="all" selected>{{__('All')}}</option>
                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
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
$(document).on('change','[js-get-beneficiary]',function(e){
	const currencyName = $('select#currency_name').val()
	if(currencyName){
		$.ajax({
			url:"{{ route('get.beneficiary.name.by.currency',['company'=>$company->id]) }}",
			data:{
				currencyName
			},
			success:function(res){
				var benefiniciaries = res.beneficiaries
				
				var options = '';
				for(var id in benefiniciaries){
					var name = benefiniciaries[id]
					options+=`<option value="${id}">${name}</option>`
				}
				$('select#beneficiary-id').empty().append(options).trigger('change')
			}
			
		})
	}
})
$('[js-get-beneficiary]').trigger('change')
</script>

@endsection
