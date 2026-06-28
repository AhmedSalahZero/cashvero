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
{{ __('LG & LC Bank Statement') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">


        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('result.lg.lc.bank.statement',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2 mb-4">
                            <label>{{ __('Start Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="start_date" value="{{ now() }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{ __('End Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="end_date" value="{{ now()->addYear() }}">
                                </div>
                            </div>
                        </div>





                        <div class="col-md-2 mb-4">
                            <label>{{ __('Select Currency') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select js-when-change-trigger-change-account-type data-live-search="true" data-actions-box="true" name="currency" required class="form-control current-currency  kt-bootstrap-select select2-select kt_bootstrap_select ajax-currency-name">
                                        @foreach(getCurrency() as $currency=>$currencyName)
                                        <option @if($currency == $selectedCurrency)  selected @endif value="{{ $currency }}">{{ touppercase($currencyName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 width-45">
                            <label>{{__('Select Bank')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">

                                    <select required js-when-change-trigger-change-account-type data-financial-institution-id name="financial_institution_id" js-get-lc-facility-based-on-financial-institution id="financial-instutition-id" class="form-control ">
                                        @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                        <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getCashInBankReceivingBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                        @endforeach
                                    </select>

                                </div>
                            </div>
                        </div>


                        <div class="col-md-3">
                            <label>{{__('Report Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required name="report_type" class="form-control update-lc-or-lg-type">
                                        <option value="" selected>{{__('Select')}}</option>
                                        @foreach([
											'LetterOfCreditIssuance'=>__('Letter Of Credit Bank Statement')
											 , 'LetterOfGuaranteeIssuance'=>__('Letter Of Guarantee Bank Statement'),
											 'LCOverdraft'=>__('Letter Of Credit Overdraft Bank Statement')
											 
											 ] as $tableName => $title)
											<option value="{{ $tableName }}" > {{ $title }} </option>
										@endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 width-12" id="source-div-js">
                            <label>{{__('Source')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required  name="source" class="form-control sources-js">
                                        <option value="" selected>{{__('Select')}}</option>

                                    </select>
                                </div>
                            </div>
                        </div>




                        <div class="col-md-3 width-12" id="type-div-js">
                            <label>{{__('Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required  name="type" class="form-control types-js">
                                       
                                    </select>
                                </div>
                            </div>
                        </div>
						
						
						 <div class="col-md-3" id="lc-facility-div-id" style="display:none">
                                        <label>{{__('LC Facility')}}
                                            @include('star')
                                        </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select  js-update-outstanding-balance-and-limits data-current-selected="{{ isset($model) ? $model->getLcFacilityId() : 0 }}" id="lc-facility-id" name="lc_facility_id" class="form-control">
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
<script src="/custom/money-receive.js">

</script>
<script>
	$(document).on('change','select.update-lc-or-lg-type',function(){
		const lcOrLg = $(this).val();
		if(lcOrLg == 'LCOverdraft'){
			$('#lc-facility-div-id').show()
		}else{
			$('#lc-facility-div-id').hide()
			
		}
		if(lcOrLg){
			$.ajax({
				url:"{{ route('get.lc.or.lg.types',['company'=>$company->id]) }}",
				data:{
					lcOrLg
				},
				success:function(res){
					
					var options = ''
					
					for(var id in res.types){
						options += `<option value="${id}">${res.types[id]}</option> `
					}
					$('select.types-js').empty().append(options).trigger('change')
					
					
							var options = ''
					for(var id in res.sources){
						options += `<option value="${id}">${res.sources[id]}</option> `
					}
					$('select.sources-js').empty().append(options).trigger('change')
					
					
					if(!Object.keys(res.sources).length){
						$('#source-div-js').hide();
						$('#source-div-js select').prop('required',false);
					}else{
						$('#source-div-js').show()
						$('#source-div-js select').prop('required',true);
					
					}
					if(!Object.keys(res.types).length){
						$('#type-div-js').hide();
						$('#type-div-js select').prop('required',false);
					}else{
			
						$('#type-div-js').show()
						$('#type-div-js select').prop('required',true);
					}
					
				}
			})
		}
	})
	
$(document).on('change','select[js-get-lc-facility-based-on-financial-institution]',function(){
	const financialInstitutionId = $('#financial-instutition-id').val();
	const currentSelected = $('select#lc-facility-id').attr('data-current-selected');

	$.ajax({
		url:"{{ route('get.lc.facility.based.on.financial.institution',['company'=>$company->id]) }}",
		data:{
			financialInstitutionId
		},
		success:function(res){
			const lcFacilities = res.letterOfCreditFacilities ;
			let options='<option value="">{{ __("Select") }}</option>';
		
			for(id in lcFacilities){
				var name =lcFacilities[id]; 
				options+=`<option ${currentSelected == id ? 'selected' : '' } value="${id}"  >${name}</option>`
			}
			$('select#lc-facility-id').empty().append(options).trigger('change')
		}
	})
})

$('select[js-get-lc-facility-based-on-financial-institution]').trigger('change')

</script>
@endsection
