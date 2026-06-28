@extends('layouts.dashboard')
@section('css')
@php
use App\Models\CustomerInvoice;
use App\Models\MoneyReceived ;

@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
.width-17{
        max-width: initial !important;
        width: 17% !important;
        flex: initial !important;
    }
	
    label {
        text-align: left !important;
    }

 
    .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }
.width-9 {
        max-width: initial !important;
        width: 9% !important;
        flex: initial !important;
    }
    .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: initial !important;
        width: 12.5% !important;
        flex: initial !important;
    }

    .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style),
    input.form-control:not(.is-date-css)[readonly] {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>
@endsection
@section('sub-header')
@if($contract)

 {{__('Settlement Using Contract Down Payment')}}
							[{{ $contract->getName() }}]
							@else
 {{__('Settlement Using Down Payment')}}
							
							@endif
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">

        <form method="post" action="{{route('store.down.payment.settlement',['company'=>$company->id,'downPaymentId'=>$downPayment->id,'partnerId'=>$partnerId,'modelType'=>$modelType]) }}" class="kt-form kt-form--label-right">
							{{-- <input type="hidden" name="invoiceId" value="{{ $invoiceId }}"> --}}
			<input type="hidden" name="model_type" value="{{ $modelType }}" > 
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($downPayment) ? 1 : 0 }}">
            <input id="js-money-received-id" type="hidden" name="down_payment_id" value="{{ isset($downPayment) ? $downPayment->id : 0 }}">
            <input id="js-money-payment-id" type="hidden" name="down_payment_id" value="{{ isset($downPayment) ? $downPayment->id : 0 }}">
			                           

            {{-- <input type="hidden" id="ajax-invoice-item" data-single-model="{{ 1 }}" value="{{ $invoiceNumber }}"> --}}
            @csrf
            
            {{-- Money Received --}}
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
						@if($contract)
                            {{__('Settlement Using Contract Down Payment')}}
							[{{ $contract->getName() }}]
							@else
                            {{__('Settlement Using Down Payment')}}
							
							@endif 
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">


                        <div class="col-md-5">
                            <label>{{$customerNameText}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select disabled id="{{ $customerNameColumnName }}" name="{{ $customerIdColumnName }}" class="form-control ajax-get-invoice-numbers">
                                            {{-- @foreach($invoices as $partnerId => $customerName) --}}
                                            <option selected value="{{ $partnerId }}">{{$partnerName}}</option>
                                            {{-- @endforeach --}}
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>
						
						 <div class="col-md-2">
                            <label>  {{ __('Down Payment Amount') }} </label>
							<div class="form-group">
							 <input data-max-cheque-value="0" readonly type="text" value="{{ $downPaymentAmount}}" name="received_amount" class="form-control only-greater-than-or-equal-zero-allowed   main-amount-class recalculate-amount-class" placeholder="{{__('Received Amount')}}">
							 
							</div>

                        </div>

                        <div class="col-md-2">
                            <label>{{__('Currency')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select disabled name="currency" class="form-control current-currency ajax-get-invoice-numbers">
                                        {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                        @foreach(isset($currencies) ? $currencies : getBanksCurrencies () as $currencyId=>$currentName)
										
										@php
								$selected = isset($downPayment) ?  $downPayment->getCurrency()  == $currencyId  :  $currentName == $company->getMainFunctionalCurrency() ;
									$selected = $selected ? 'selected':'';
							   @endphp
							   
                                        <option {{ $selected }} value="{{ $currencyId }}">{{ touppercase($currentName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label>{{__('Settlement Date')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input  type="text" name="settlement_date"  value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control is-date-css" readonly placeholder="Select date" id="kt_datepicker_2" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Settlement Information "Commen Card" --}}
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Settlement Information')}}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">

                    <div class="js-append-to">
                        <div class="col-md-12 js-duplicate-node">

                        </div>
                    </div>
                    <div class="js-template ">
					     <div class="col-md-12 js-duplicate-node">
						  @foreach($invoices as $index=>$invoice)
						  
						  <div class=" kt-margin-b-10 border-class">
			<div class="form-group row align-items-end">
				@if($hasProjectNameColumn)
				<div class="col-md-1 width-17 ">
					<label> {{ __('Project Name') }} </label>
					<div class="kt-input-icon">
						<div class="kt-input-icon">
							<div class="input-group date">
								<input readonly class="form-control js-project-name" 
							
								name="settlements[{{$index}}][project_name]"
								
								 value="{{ $invoice->getProjectName() }}">
							</div>
						</div>
					</div>
				</div>
				
				@endif
				@php
					$totalSettlementAmount  = $downPayment->sumSettlementsForInvoice($invoice->id,$partnerId,$isDownPaymentFromMoneyPayment);
					$totalWithholdAmount = $downPayment->sumWithholdAmountForInvoice($invoice->id,$partnerId,$isDownPaymentFromMoneyPayment);
				@endphp
				<div class="col-md-1 width-10 ">
					<label> {{ __('Invoice Number') }} </label>
					<div class="kt-input-icon">
						<div class="kt-input-icon">
							<div class="input-group date">
								<input type="hidden" name="settlements[{{$index}}][invoice_id]" value="{{ $invoice->id }}" class="js-invoice-id">
								<input readonly class="form-control" name="settlements[{{$index}}][invoice_number]" value="{{ $invoice->getInvoiceNumber() }}">
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-1 width-9 ">
					<label>{{ __('Invoice Date') }}</label>
					<div class="kt-input-icon">
						<div class="input-group date">
							<input name="settlements[{{$index}}][invoice_date]" type="text" class="form-control " value="{{ $invoice->getInvoiceDateFormatted() }}" disabled />
						</div>
					</div>
				</div>
				<div class="col-md-1 width-9 ">
					<label>{{ __('Due Date') }}</label>
					<div class="kt-input-icon">
						<div class="input-group date">
							<input name="settlements[{{$index}}][invoice_due_date]" type="text" class="form-control" value="{{  $invoice->getInvoiceDueDateFormatted() }}" disabled />
						</div>
					</div>
				</div>
				
				{{-- <div class="col-md-1 width-8">
					<label> {{ __('Currency') }} </label>
					<div class="kt-input-icon">
						<input name="settlements[{{$index}}][currency]" type="text" disabled class="form-control" value="{{ $invoice->getCurrency() }}">
					</div>
				</div> --}}
				
				<div class="col-md-2 width-12 ">
					<label> {{ __('Invoice Amount') . ' [ ' . $invoice->getCurrency() .' ]' }} </label>
					<div class="kt-input-icon">
						<input name="settlements[{{$index}}][net_invoice_amount]" type="text" disabled class="form-control" value="{{ $invoice->getNetInvoiceAmountFormatted() }}">
					</div>
				</div>
				
				<div class="col-md-2 width-12 ">
					<label> {{ __('Collected Amount') }} </label>
					<div class="kt-input-icon">
						<input name="settlements[{{$index}}][collected_amount]" type="text" disabled class="form-control" value="{{ number_format($invoice->getCollectedOrPaidInEditModeForDownPayment(true,$totalSettlementAmount) ,0) }}">
					</div>
				</div>
		
				<div class="col-md-2 width-12 ">
					<label> {{ __('Net Balance') }} </label>
					<div class="kt-input-icon">
						<input name="settlements[{{$index}}][net_balance]" type="text" disabled class="form-control " value="{{ number_format($invoice->calculateNetBalanceInEditMode(true,$totalSettlementAmount , $totalWithholdAmount) ,0) }}">
					</div>
				</div>
				<div class="col-md-1 width-9.5 ">
					<label> {{ __('Settlement Amount') }}  <span class="text-danger ">*</span> </label>
					<div class="kt-input-icon">
						<input value="{{ $totalSettlementAmount }}" name="settlements[{{$index}}][settlement_amount]" placeholder="{{ __("Settlement Amount") }}" type="text" class="form-control  only-greater-than-or-equal-zero-allowed settlement-amount-class">
					</div>
				</div>
				<div class="col-md-1 width-9.5 ">
					<label> {{ __('Withhold Amount') }} <span class="text-danger ">*</span> </label>
					<div class="kt-input-icon">
						<input value="{{ $totalWithholdAmount  }}" name="settlements[{{$index}}][withhold_amount]" placeholder="{{ __('Withhold Amount') }}" type="text" class="form-control  only-greater-than-or-equal-zero-allowed ">
					</div>
				</div>
		
			</div>
		
		</div>
		@endforeach 
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
						  </div>
                    </div>

                    <hr>
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-md-2"></div>
                        <div class="col-md-2"></div>
                        <div class="col-md-2"></div>
                        <div class="col-md-2"></div>
                        
                </div>
            </div>
    </div>
	 <x-submitting-by-ajax :validation="1" :backTo="route('view.contracts.down.payments',['company'=>$company->id,'partnerId'=>$partnerId,'modelType'=>$modelType,'currency'=>$downPayment->getCurrency()])" />

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

</script>
<script>
    $('#type').change(function() {
        selected = $(this).val();
        $('.js-section-parent').addClass('hidden');
        if (selected) {
            $('#' + selected).removeClass('hidden');

        }


    });
    $('#type').trigger('change')

</script>
{{-- <script src="/custom/{{ $jsFile }}">

</script> --}}

<script>
    $(document).on('change', '.settlement-amount-class', function() {

    })
    $(function() {
        $('#type').trigger('change');
    })

</script>
{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js') }}" type="text/javascript">
</script> --}}

{{-- <script>
    $(function() {
        $('#firstColumnId').trigger('change');
    })

</script> --}}
<script>
$(function(){

	$('select.ajax-get-invoice-numbers').trigger('change')
})
</script>
@endsection
