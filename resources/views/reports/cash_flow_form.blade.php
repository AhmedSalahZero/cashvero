@extends('layouts.dashboard')
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

  

    input.form-control[disabled]:not(.ignore-global-style) {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }
	
    .kt-portlet {
        overflow: visible !important;
    }
 .max-w-checkbox {
        min-width: 25px !important;
        max-width: 25px !important;
        width: 25px !important;
		margin-left:30px;
    }
</style>
@endsection
@section('sub-header')
{{ __('Cash Flow Report') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">



        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="get" action="{{ route('result.cashflow.report',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            {{-- @csrf --}}
            <div class="kt-portlet" style="overflow-x:hidden">

                <div class="kt-portlet__body">



                    <div class="form-group row">


                        <div class="col-md-3">
                            <label>{{__('Report Interval')}} @include('star')</label>

                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="report_interval" class="form-control " required>
                                         <option value="">{{ __('Select') }}</option>
                                         <option value="daily">{{__('Daily')}}</option>
                                        <option value="weekly" >{{__('Weekly')}}</option>
                                        <option value="monthly">{{__('Monthly')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
						
				<div class="col-md-3 ">
                                    <x-form.date :type="'text'" :classes="'datepicker-input '" :default-value="formatDateForDatePicker(old('start_date') ?: (now()) )" :model="$model??null" :label="__('Start Date')" :type="'text'" :id="'id'" :placeholder="__('')" :name="'start_date'" :required="true"></x-form.date>
                                </div>

                        {{-- <div class="col-md-3">
                            <label>{{ __('Start Date') }} @include('star') </label>
                            <div class="kt-input-icon">
                                <div class="input-group date" id="start_date">
                                    <input required type="date" class="form-control" name="start_date" value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}">
                                </div>
                            </div>
                        </div> --}}


                       <div class="col-md-3 ">
                                    <x-form.date :type="'text'" :classes="'datepicker-input '" :default-value="formatDateForDatePicker(old('end_date') ?: (now()->addMonths(6)) )" :model="$model??null" :label="__('End Date')" :type="'text'" :id="'id'" :placeholder="__('')" :name="'end_date'" :required="true"></x-form.date>
                                </div>

                        <div class="col-md-1">
                            <label>{{__('Currency')}} @include('star')</label>

                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="currency" class="form-control current-currency ajax-get-invoice-numbers">
                                        <option value="" selected>{{__('Select')}}</option>
                                        @foreach(isset($currencies) ? $currencies : getBanksCurrencies () as $currencyId=>$currentName)
                                        @php
                                        $selected = isset($model) ? $model->getCurrency() == $currencyId : $currentName == $company->getMainFunctionalCurrency() ;
                                        $selected = $selected ? 'selected':'';
                                        @endphp
                                        <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
						
						<div class="col-md-2 mt-4">
							<p class="text-left text-red">
								{{ __('Note: Kindly the date of Today must be included within the report duration') }}
							</p>
						</div>
						<div class="col-md-3 mt-4">
						 <label>{{__('Reset [Past Dues & Other Projected Cash In & Out]')}} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date justify-content-center">
                                            <input name="reset_report"  class="form-control max-w-checkbox  text-center" value="1"   type="checkbox">
								</div>
								</div>
						</div>
						
						<div class="col-md-3 mt-4">
						 <label>{{__('Do You Want To Save Report')}} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date ">
                                            <input name="save_report"  class="form-control max-w-checkbox want-to-save-report  text-center" value="1"   type="checkbox">
								</div>
								</div>
						</div>
						
						
						  <div class="col-md-4 mt-4 " id="report-name-div" style="display:none">
                            <label>{{ __('Report Name') }} </label>
                        <div class="kt-input-icon">
                            <div class="input-group date" id="report_name">
                                <input type="text" class="form-control" name="report_name" value="">
                            </div>
                        </div>
                    </div>
				
				
				
				
						

					


                      














                </div>
				
					

		
            </div>
    </div>
                <x-submitting />
    </form>





			@include('cashflow-report-index')
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
{{-- <script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script> --}}
{{-- <script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js') }}" type="text/javascript">
</script> --}}

{{-- <script>
    $(function() {
        $('#firstColumnId').trigger('change');
    })

</script> --}}
<script>
 $(document).find('.datepicker-input').datepicker({
                dateFormat: 'yy-mm-dd'
                , autoclose: true
            })
			
</script>
<script>
$(document).on('change','.want-to-save-report',function(){
	const isChecked = $(this).is(':checked');
	if(isChecked){
		$('#report-name-div').show();
	}else{
		$('#report-name-div').hide();
	}
})
</script>
@endsection
