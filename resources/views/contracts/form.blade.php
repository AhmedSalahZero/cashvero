@php
use App\Models\MoneyReceived ;
@endphp
@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-portlet .kt-portlet__head {
        border-bottom-color: #CCE2FD !important;
    }

    label {
        white-space: nowrap !important
    }

    [class*="col"] {
        margin-bottom: 1.5rem !important;
    }

    label {
        text-align: left !important;
    }

    .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
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
{{ $formTitle }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">

        <form method="post" action="{{ isset($model) ? route('contracts.update',['company'=>$company->id,'contract'=>$model->id,'type'=>$type]) : route('contracts.store',['company'=>$company->id,'type'=>$type]) }}" class="kt-form kt-form--label-right">
            @csrf
            @if(isset($model))
            @method('put')
            @endif
            <div class="row">
                <div class="col-md-12">
                    <!--begin::Portlet-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    <x-sectionTitle :title="$formTitle"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->

                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{__('Contract Information')}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <input type="hidden" name="company_id" value="{{ $company->id }}">
                            <input id="model_type" type="hidden" name="model_type" value="{{ $type }}">
                            <div class="form-group row">

                                <div class="col-md-4 ">
                                    <label> {{ __('Name') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input required name="name" type="text" class="form-control" value="{{   old('name',isset($model) ? $model->getName() : null ) }}">
                                        </div>
                                    </div>
                                </div>

								
                                <div class="col-md-2 ">
                                    <label> {{ __('Code') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input
											@if(isset($model))
											readonly
											@endif 
											 required name="code" id="contract-code" type="text" class="form-control " value="{{ old('code',isset($model) ? $model->getCode() : null)   }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-5">

                                    <label>{{__('Partner Name')}}
                                        @include('star')
                                    </label>
									
                                    <div class="kt-input-icon">
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select  data-live-search="true" data-actions-box="true" id="customer_name" name="partner_id" class="form-control select2-select regenerate-code-ajax">
                                                    @foreach($clients as $index => $customer )
                                                    <option @if( old('partner_id',isset($model) && $model->getClientId() == $customer->id  ) ) selected @endif value="{{ $customer->id }}">{{$customer->getName()}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>




                                </div>
                                <div class="col-md-1">
                                    <label style="visibility:hidden !important;"> *</label>
                                    <button type="button" class="add-new btn btn-primary d-block" data-toggle="modal" data-target="#add-new-customer-modal">
                                        {{ __('Add New') }}
                                    </button>
                                </div>
                                <div class="modal fade" id="add-new-customer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">{{ __('Add New' . ' ' . $type) }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form>
                                                    <input value="" class="form-control" name="new_customer_name" id="new_customer_name" placeholder="{{ __('Enter New Customer Name') }}">
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                <button type="button" class="btn btn-primary js-add-new-customer-if-not-exist">{{ __('Save') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-2 ">
                                    <x-form.date :type="'text'" :classes="'datepicker-input start-date regenerate-code-ajax '" :default-value="formatDateForDatePicker(old('start_date') ?: (isset($model)  ? $model->getStartDate() : now()) )" :model="$model??null" :label="__('Start Date')" :type="'text'" :id="'start-date-id'" :placeholder="__('')" :name="'start_date'" :required="true"></x-form.date>
                                </div>
                                {{-- <div class="col-md-2 ">
                                    <label> {{ __('Duration (Months)') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input required name="duration" type="numeric" class="form-control duration recalc-end-date duration " value="{{ ceil(old('duration',isset($model) ? $model->getDuration() * (12/365) : null))  }}">
                                        </div>
                                    </div>
                                </div> --}}
								
								 <div class="col-md-2 ">
                                    <x-form.date :type="'text'" :classes="'datepicker-input '" :default-value="formatDateForDatePicker(old('end_date') ?: (isset($model)  ? $model->getEndDate() : now()->addYear()) )" :model="$model??null" :label="__('End Date')" :type="'text'" :id="'end-date-id'" :placeholder="__('')" :name="'end_date'" :required="true"></x-form.date>
                                </div>
								
                                {{-- <div class="col-md-2 ">
                                    <label> {{ __('End Date') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input  name="end_date" type="text" class="form-control datepicker-input end-date" value="{{ old('end_date',isset($model) ? $model->getEndDate() : null )   }}">
                                        </div>
                                    </div>
                                </div> --}}



                                <div class="col-md-3 ">
                                    <label> {{ __('Amount') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input required name="amount" type="text" class="form-control only-greater-than-or-equal-zero-allowed" value="{{ old('amount',isset($model) ? $model->getAmount() : 0 )   }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-1 ">
                                    <label> {{ __('Currency') }}
                                        @include('star')
                                    </label>
                                    <div class="input-group">
                                        <select required name="currency" class="form-control current-currency ajax-get-invoice-numbers" js-when-change-trigger-change-account-type>
                                            <option selected>{{__('Select')}}</option>
                                            @foreach(getCurrencies() as $currencyName => $currencyValue )
                                            <option value="{{ $currencyName }}" @if( old('currency',isset($model) ? $model->getCurrency():null) == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>


                                <div class="col-md-1 ">
                                    <label> {{ __('Exhange Rate') }}
                                        @include('star')
                                    </label>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input required name="exchange_rate" type="text" class="form-control only-greater-than-or-equal-zero-allowed" value="{{ generateModelData('exchange_rate',isset($model) ? $model : null ,'getExchangeRate', 1 ) }}">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>


                    <div class="kt-portlet">

                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    {{$salesOrderOrPurchaseOrderInformationText}}
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">


                            <div class="form-group row justify-content-center">
                                @php
                                $index = 0 ;
                                @endphp



                                {{-- start of fixed monthly repeating amount --}}
                                @php
                                $tableId = $salesOrderOrPurchaseOrderRelationName;
                                $repeaterId = 'm_repeater_outer';

                                @endphp
                                {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                                <x-tables.repeater-table :initialJs="false" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                    <x-slot name="ths">
                                        @foreach([
                                        $salesOrderOrPurchaseNumberText =>'col-md-1',
                                        __('Amount')=>'col-md-1',
                                        __('Insert Execution Details')=>'col-md-1',
									//	__('Allocate')=>'col-md-1'
                                        ] as $title=>$classes)
                                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
										
                                        @endforeach
                                    </x-slot>
                                    <x-slot name="trs">
                                        @php
                                        $rows = old($salesOrderOrPurchaseOrderRelationName) ?  fillObjectFromArray(old(($salesOrderOrPurchaseOrderRelationName)),$salesOrderOrPurchaseOrderObject) : (isset($model) ? $model->{$salesOrderOrPurchaseOrderRelationName} : [-1]) ;

                                        @endphp
                                        @foreach( count($rows) ? $rows : [-1] as $salesOrder)
                                        @php
                                        if( !($salesOrder instanceof $salesOrderOrPurchaseOrderObject) ){
                                        unset($salesOrder);
                                        }
                                        @endphp
                                        <tr @if($isRepeater) data-repeater-item @endif>
                                            <td class="text-center">
                                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                                <input type="hidden" name="id" value="{{ isset($salesOrder) ? $salesOrder->id :0 }}">
                                                <div class="">
                                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                    </i>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="{{ $salesOrderOrPurchaseNoText }}" type="text" class="form-control " value="{{ isset($salesOrder) ? $salesOrder->getNumber() : old('salesOrders.'.$salesOrderOrPurchaseNoText,0) }}">
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="amount" type="text" class="form-control js-recalculate-amounts-in-popup js-recalculate-allocation-amount-js" value="{{ isset($salesOrder) ? $salesOrder->getAmount() : old('salesOrders.amount',0) }}">
                                                    </div>
                                                </div>
                                            </td>
											
											
											


                                         

                                            <td class="text-center">
                                                <button class="btn btn-primary btn-active js-show-execution-percentage-modal">{{ __('Insert Execution Details') }}</button>
                                                <x-modal.execution-percentage :popup-title="__('Execution Details')" :subModel="isset($salesOrder) ? $salesOrder : null " :subModel="isset($salesOrder) ? $salesOrder : null " :tableId="$tableId" :isRepeater="$isRepeater" :id="$repeaterId.'test-modal-id'"></x-modal.execution-percentage>
                                            </td>
											
											
											
											
											
											


                                            {{-- @for($i = 1 ; $i <= 5 ; $i++) 
											<td>
                                                <div class="kt-input-icon">
                                                    <div class="input-group">
                                                        <input name="execution_percentage_{{ $i }}" type="numeric" step="0.1" class="form-control " value="{{ isset($salesOrder) ? $salesOrder->getExecutionPercentage($i) : old('salesOrders.execution_percentage_'.$i,0) }}">
                            </div>
                        </div>
                        </td>

                        <td>
                            <div class="kt-input-icon">
                                <div class="input-group">
                                    <input name="execution_days_{{ $i }}" type="numeric" step="1" class="form-control " value="{{ isset($salesOrder) ? $salesOrder->getExecutionDays($i) : old('salesOrders.execution_days_'.$i,0) }}">
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="kt-input-icon">
                                <div class="input-group">
                                    <input name="collection_days_{{ $i }}" type="numeric" step="1" class="form-control " value="{{ isset($salesOrder) ? $salesOrder->getCollectionDays($i) : old('salesOrders.collection_days_'.$i,0) }}">
                                </div>
                            </div>
                        </td>



                        @endfor --}}







                        </tr>
                        @endforeach

                        </x-slot>




                        </x-tables.repeater-table>
                        {{-- end of fixed monthly repeating amount --}}

                    </div>

                </div>
            </div>
			




















            <x-submitting />

    </div>
</div>

</div>
@endsection
@section('js')
<script>
    function reinitalizeMonthYearInput(dateInput) {
        var currentDate = $(dateInput).val();
        var startDate = "{{ isset($studyStartDate) && $studyStartDate ? $studyStartDate : -1 }}";
        startDate = startDate == '-1' ? '' : startDate;
        var endDate = "{{ isset($studyEndDate) && $studyEndDate? $studyEndDate : -1 }}";
        endDate = endDate == '-1' ? '' : endDate;

        $(dateInput).datepicker({
                viewMode: "year"
                , minViewMode: "year"
                , todayHighlight: false
                , clearBtn: true,
                autoclose: true
                , format: "mm/01/yyyy"
            , })
            .datepicker('setDate', new Date(currentDate))
            .datepicker('setStartDate', new Date(startDate))
            .datepicker('setEndDate', new Date(endDate))


    }

</script>
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
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })
    $('.repeater-js').repeater({
        initEmpty: false
        , isFirstItemUndeletable: true
        , defaultValues: {
            'text-input': 'foo'
        },

        show: function() {
            $(this).slideDown();

            $('input.trigger-change-repeater').trigger('change')
            $(document).find('.datepicker-input:not(.only-month-year-picker)').datepicker({
                dateFormat: 'mm-dd-yy'
                , autoclose: true
            })

            $('input:not([type="hidden"])').trigger('change');
            $(this).find('.dropdown-toggle').remove();
            $(this).find('select.repeater-select').selectpicker("refresh");

        },

        hide: function(deleteElement) {
            if ($('#first-loading').length) {
                $(this).slideUp(deleteElement, function() {

                    deleteElement();
                    //   $('select.main-service-item').trigger('change');
                });
            } else {
                if (confirm('Are you sure you want to delete this element?')) {
                    $(this).slideUp(deleteElement, function() {

                        deleteElement();
                        $('input.trigger-change-repeater').trigger('change')

                    });
                }
            }
        }
    });

</script>

<script>
    let oldValForInputNumber = 0;
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
        oldValForInputNumber = $(this).val();
		if(isNumeric(oldValForInputNumber)){
        	$(this).val('')
		}
    })
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

        if ($(this).val() == '') {
            $(this).val(oldValForInputNumber)
        }
    })

    $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
        if (!$(this).hasClass('exclude-text')) {
            let val = $(this).val()
            val = number_unformat(val)
            $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

        }
    })

</script>

<script>
    $(document).on('change', '.js-recalculate-amounts-in-popup', function() {
        let amount = number_unformat($(this).val())
        amount = amount ? amount : 0;
        const parent = $(this).closest('[data-repeater-item]');

        $(parent).find('.execution-percentage-js').each(function(index, element) {
            var executionPercentage = $(element).val();
            executionPercentage = executionPercentage ? executionPercentage / 100 : 0;
            $(this).closest('tr').find('.amount-js').val(executionPercentage * amount)
        })
    });
	
    $(document).on('change', '.execution-percentage-js', function() {
        let executionPercentage = $(this).val()
        executionPercentage = executionPercentage ? executionPercentage : 0;
        executionPercentage = executionPercentage / 100;
        const parent = $(this).closest('[data-repeater-item]');
        let amount = number_unformat($(parent).find('.js-recalculate-amounts-in-popup').val());
        amount = amount ? amount : 0;
        $(this).closest('tr').find('.amount-js').val(executionPercentage * amount)

    });
	
	 $(document).on('change', '.js-recalculate-allocation-amount-js', function() {
        let amount = number_unformat($(this).val())
        amount = amount ? amount : 0;
        const parent = $(this).closest('[data-repeater-list="purchasesOrders"]');
        $(parent).find('.allocation-percentage-class').each(function(index, element) {
            var allocationPercentage = $(element).val();
            allocationPercentage = allocationPercentage ? allocationPercentage / 100 : 0;
            $(this).closest('tr').find('.allocation-amount-class').val(allocationPercentage * amount)
        })
    });
	$(document).on('change', '.allocation-percentage-class', function() {
        let percentage = $(this).val()
        percentage = percentage ? percentage : 0;
        percentage = percentage / 100;
        const parent = $(this).closest('[data-repeater-list="purchasesOrders"]');
        let amount = number_unformat($(parent).find('.js-recalculate-allocation-amount-js').val());
        amount = amount ? amount : 0;
        $(this).closest('tr').find('.allocation-amount-class').val(percentage * amount)

    });


    $('.must-not-exceed-100').trigger('change')

</script>

<script src="/custom/money-receive.js"></script>

<script>
    $(document).on('change', '.recalc-end-date', function(e) {
        e.preventDefault()
        const startDate = new Date($('.start-date').val());
        const duration = parseFloat($('.duration').val());
        if (duration || duration == '0') {
            const numberOfDays = duration * 365/12
			
		
            let endDate = startDate.addDays(numberOfDays)
            endDate = formatDate(endDate)
            $('#end-date').val(endDate).trigger('change')

        }

    });
    $('.recalc-end-date').trigger('change');


    $(document).on('change', '.recalc-end-date-2', function(e) {
        e.preventDefault()
        const parent = $(this).closest('tr')
        const startDate = new Date(parent.find('.start-date-2').val());
        const duration = parseFloat(parent.find('.duration-2').val());
        if (duration || duration == '0') {
            const numberOfDays = duration * 365/12
            let endDate = startDate.addDays(numberOfDays)
            endDate = formatDate(endDate)
            parent.find('.end-date-2').val(endDate).trigger('change')
        }

    });
    $('.recalc-end-date').trigger('change');

</script>
<script>
    $(document).on('click', '.js-show-execution-percentage-modal', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.modal-item-js').modal('show')
    })
    $(document).on('click', '.js-add-new-customer-if-not-exist', function(e) {
        const customerName = $('#new_customer_name').val()
        const url = "{{ route('add.new.partner',['company'=>$company->id,'type'=>$type]) }}"
        if (customerName) {
            $.ajax({
                url
                , data: {
                    customerName
                }
                , type: "post"
                , success: function(response) {
                    if (response.status) {
                        $('select#customer_name').append('<option selected value="' + response.customer.id + '"> ' + customerName + ' </option>  ')
                        $('#add-new-customer-modal').modal('hide')
                    } else {
                        Swal.fire({
                            icon: "error"
                            , title: response.message
                        })
                    }
                }
            })
        }
    })

</script>
<script>
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency').toUpperCase()
        $(parent).find('.contract-code').val(code)
        $(parent).find('.contract-amount').val(number_format(amount) + ' '  + currency )

    })
    $(document).on('change', 'select.suppliers-or-customers-js', function() {
        const parent = $(this).closest('tr')
        const partnerId = parseInt($(this).val())
        const model = $('#model_type').val()
        let inEditMode = "{{ $inEditMode ?? 0 }}";

        $.ajax({
            url: "{{ route('get.contracts.for.customer.or.supplier',['company'=>$company->id]) }}"
            , data: {
                partnerId
                , model
                , inEditMode
            }
            , type: "get"
            , success: function(res) {
                let contracts = '';
                const currentSelected = $(parent).find('select.contracts-js').data('current-selected')
                for (var contract of res.contracts) {
                    contracts += `<option ${currentSelected ==contract.id ? 'selected' :'' } value="${contract.id}" data-code="${contract.code}" data-amount="${contract.amount}" data-currency="${contract.currency}" >${contract.name}</option>`;
                }
                parent.find('select.contracts-js').empty().append(contracts).trigger('change')
            }
        })
    })
    $(function() {
        $('select.suppliers-or-customers-js').trigger('change')
    })

</script>

<script>
	$(document).on('change','.regenerate-code-ajax',function(e){
		e.preventDefault();
		const partnerId = $('select#customer_name').val();
		const startDate = $('#start-date-id').val()
		const modelType = $('#model_type').val()
		if(partnerId && startDate ){
			$.ajax({
				url:"{{ route('generate.unique.rondom.contract.code',['company'=>$company->id,'type'=>$type]) }}",
				data:{
					partnerId,
					startDate
				},
				success:function(res){
					$('#contract-code').val(res.code)					
				}
			})
		} 
	})
	
</script>

<script>
$(document).on('change','.recheck-start-date-rule-js',function(){
	
	let originContractStartDate = $('.start-date').val() ;
	let contractStartDate = new Date(originContractStartDate)
	let originContractEndDate = $('.end-date').val() ;
	let contractEndDate = new Date(originContractEndDate)
	let value = new Date($(this).val())
	if(value < contractStartDate ){
		let lang = $('body').data('lang');
	title = "Oops..." ;
	message = "Execution Start Date Can Not Be Less Than Contract Start Date" ;
	if(lang === 'ar'){
		title = 'خطأ'  ;
		message = "تاريخ بدايه التنفيذ لا يمكن ان يكون اصغر من تاريخ بدايه العقد"
	}
	Swal.fire({
            icon: "warning",
            title,
            text: message,
        })
		
		$(this).datepicker('update',originContractStartDate)
		
	}
	else if(value > contractEndDate ){
		let lang = $('body').data('lang');
	title = "Oops..." ;
	message = "Execution Start Date Can Not Be Greater Than Contract End Date" ;
	if(lang === 'ar'){
		title = 'خطأ'  ;
		message = "تاريخ بدايه التنفيذ لا يمكن ان يكون اكبر من تاريخ نهاية العقد"
	}
	Swal.fire({
            icon: "warning",
            title,
            text: message,
        })
		
		$(this).datepicker('update',originContractEndDate)
		
	}

	
	
	
})

$(document).on('change','.recheck-end-date-rule-js',function(){
	
	let originContractStartDate = $('.start-date').val() ;
	let contractStartDate = new Date(originContractStartDate)
	let originContractEndDate = $('.end-date').val() ;
	let contractEndDate = new Date(originContractEndDate)
	let value = new Date($(this).val())
	if(value < contractStartDate ){
		let lang = $('body').data('lang');
	title = "Oops..." ;
	message = "Execution Date Can Not Be Less Than Contract Start Date" ;
	if(lang === 'ar'){
		title = 'خطأ'  ;
		message = "تاريخ التنفيذ لا يمكن ان يكون اصغر من تاريخ بدايه العقد"
	}
	Swal.fire({
            icon: "warning",
            title,
            text: message,
        })
		
		$(this).datepicker('update',originContractStartDate)
		
	}
	else if(value > contractEndDate ){
		let lang = $('body').data('lang');
	title = "Oops..." ;
	message = "Execution Date Can Not Be Greater Than Contract End Date" ;
	if(lang === 'ar'){
		title = 'خطأ'  ;
		message = "تاريخ التنفيذ لا يمكن ان يكون اكبر من تاريخ نهاية العقد"
	}
	Swal.fire({
            icon: "warning",
            title,
            text: message,
        })
		
		$(this).datepicker('update',originContractEndDate)
		
	}

	
	
	
})

$('.recheck-start-date-rule-js').trigger('change')
</script>
<script src="{{asset('assets/form-repeater.js')}}" type="text/javascript"></script>

@if(!isset($model))
<script>
	$('.regenerate-code-ajax:eq(0)').trigger('change')
</script>
@endif 
@endsection
