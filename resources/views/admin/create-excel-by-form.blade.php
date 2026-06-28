@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
@if(in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule']))
<style>
    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }

    .money-flow-dark label {
        text-align: left !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

    .money-flow-dark input,
    .money-flow-dark select,
    .money-flow-dark .dropdown-toggle.bs-placeholder {
        border: 1px solid #CCE2FD !important;
    }

    .money-flow-dark td input,
    .money-flow-dark td select,
    .money-flow-dark .filter-option {
        color: inherit !important;
        border: 1px solid #CCE2FD !important;
    }

    .money-flow-dark .icon-for-selector {
        background-color: transparent;
        color: #1490a8;
    }

    .money-flow-dark th {
        border-bottom-color: #1490a833 !important;
    }

    .money-flow-dark .table tbody+tbody {
        border-top-color: #1490a833;
    }
</style>
@endIf
@endsection
@section('sub-header')
@if(!in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule']))
<style>
    .table {
        margin-bottom: 0 !important;
    }

    .table thead th,
    .table thead td {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .filter-option-inner-inner,
    input {
        text-align: left;
        font-weight: normal !important;
    }

    .max-w-500 {
        max-width: 400px !important;
        width: 400px !important;
        min-width: 400px !important;

    }

    .max-w-checkbox {
        min-width: 25px !important;
        width: 25px !important;
    }

    .customize-elements .bootstrap-select {
        min-width: 100px !important;
        text-align: center !important;
    }

    .customize-elements input.only-percentage-allowed {
        min-width: 100px !important;
        max-width: 100px !important;
        text-align: center !important;
    }

    [data-repeater-create] span {
        white-space: nowrap !important;
    }

    .type-btn {
        max-width: 150px;
        height: 70px;
        margin-right: 10px;
        margin-bottom: 5px !important;
    }

    .type-btn:hover {}

    .bootstrap-select {
        min-width: 200px;
    }

    input {
        min-width: 200px;
    }

    input.only-month-year-picker {
        min-width: 100px;
    }

    input.only-greater-than-or-equal-zero-allowed {
        min-width: 120px;
    }

    input.only-percentage-allowed {
        min-width: 80px;
    }

    i {
        text-align: left
    }

    .kt-portlet .kt-portlet__body {
        overflow-x: scroll;
        height: 600px;
    }

    .repeat-to-r {
        flex-basis: 100%;
        cursor: pointer
    }

    .icon-for-selector {
        background-color: white;
        color: #0742A8;
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: 3px;
        transition: all 0.5s;
    }

    .icon-for-selector:hover {
        transform: scale(1.2);

    }

    .filter-option {
        text-align: center !important;
    }


    td input,
    td select,
    .filter-option {
        border: 1px solid #CCE2FD !important;
        margin-left: auto;
        margin-right: auto;
        color: black;
        font-weight: 400;
    }

    td {
        max-width: 25% !important;
        width: 25% !important;
        min-width: 25% !important;
    }

    th div.d-flex {
        justify-content: initial !important;
    }

    th {

        border-bottom: 1px solid #CCE2FD !important;
    }

    tr:last-of-type {}

    .table tbody+tbody {
        border-top: 1px solid #CCE2FD;
    }

</style>
<x-main-form-title :id="'main-form-title'" :class="''">{{ $pageTitle  }}</x-main-form-title>
@else
{{ camelToTitle($modelName) }}
@endIf
@endsection
@section('content')
@if(in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule']))
<div class="money-flow-dark">
@endIf

<div class="row">
    <div class="col-md-12">

        <form id="form-id" class="kt-form kt-form--label-right" method="POST" enctype="multipart/form-data" action="{{ isset($model) ? route('admin.update.analysis',['company'=>$company->id ,'model'=>$modelName,'modelId'=>$model->id]) :route('admin.store.analysis',['company'=>$company->id ,'model'=>$modelName]) }}">
            @csrf
            <input type="hidden" name="model_id" value="{{ $model->id ?? 0  }}">
            {{-- <input type="hidden" name="model_name" value="IncomeStatement"> --}}
            <input type="hidden" name="company_id" value="{{ getCurrentCompanyId()  }}">
            <input type="hidden" name="creator_id" value="{{ \Auth::id()  }}">
            @if($modelName === 'ContractLoanSchedule' && !empty($leasingContractId))
                <input type="hidden" name="leasing_contract_id" value="{{ $leasingContractId }}">
            @endif
            <input type="hidden" id="current-purchase-order-id" value="{{ isset($model) && $model->purchases_order_number ? @\App\Models\PurchaseOrder::where('company_id',$company->id)->where('po_number',$model->purchases_order_number)->first()->id : 0  }}">
            <input type="hidden" id="current-sales-order-id" value="{{ isset($model) && $model->sales_order_number ? @\App\Models\SalesOrder::where('company_id',$company->id)->where('so_number',$model->sales_order_number)->first()->id : 0  }}">
            <input type="hidden" id="current-contract-id" value="{{ isset($model) && $model->contract_name ? @\App\Models\Contract::where('company_id',$company->id)->where('name',$model->contract_name)->first()->id : 0  }}">


            <div class="kt-portlet">


                <div class="kt-portlet__body">

                    @php

                    $groupedFields = collect($exportables)->chunk(4)->mapWithKeys(function ($chunk, $index) {
                    return ["Group " . ($index + 1) => $chunk->toArray()];
                    })->toArray();
                    @endphp

                    @foreach($groupedFields as $currentExportables)


                    <div class="form-group row justify-content-center mb-0">
                        @php
                        $index = 0 ;
                        @endphp



                        {{-- start of fixed monthly repeating amount --}}
                        @php
                        $tableId = $modelName;
                        // $repeaterId = 'm_repeater_7';

                        @endphp
                        <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                        <x-tables.repeater-table :removeActionBtn="true" :showAddBtnAndPlus="false" :repeater-with-select2="true" :parentClass="'js-toggle-visibility'" :tableName="$tableId" :repeaterId="''" :relationName="'food'" :isRepeater="false" :initialJs="false">
                            <x-slot name="ths">
                                @foreach($currentExportables as $name=>$title)
                                <x-tables.repeater-table-th class="col-md-2" :title="$title"></x-tables.repeater-table-th>
                                @endforeach
                            </x-slot>
                            <x-slot name="trs">
                                @php
                                $rows = [-1] ;
                                @endphp
                                @foreach( count($rows) ? $rows : [-1] as $subModel)
                                @php
                                if( !($subModel instanceof \App\Models\Expense) ){
                                unset($subModel);
                                }
                                @endphp
                                @php

                                @endphp
                                <tr>


                                    <input type="hidden" name="id" value="{{ isset($subModel) ? $subModel->id : 0 }}">
                                    {{-- <input type="hidden"  value="{{ isset($subModel) ? $subModel->id : 0 }}"> --}}


                                    @foreach($currentExportables as $name=>$title)

                                    @php
                                    $fieldTypeAndClassDefaultValue = \App\Helpers\HGlobal::getFieldTypeAndClassFromTitle($modelName,$title);
                                    $fieldType = $fieldTypeAndClassDefaultValue['type'];
                                    $fieldClass = $fieldTypeAndClassDefaultValue['class'] ?? '';
                                    $defaultValue = $fieldTypeAndClassDefaultValue['default_value'];
                                    $options = $fieldTypeAndClassDefaultValue['options']??[];
                                    $oldColumnName = $fieldTypeAndClassDefaultValue['name']??'';

                                    @endphp

                                    <td>
                                        @if($fieldType == 'select')
                                        <select name="{{ $fieldTypeAndClassDefaultValue['name'] }}" class="form-control select2-select max-w-500" data-live-search="true" data-actions-box="true">
                                            @foreach($options as $id => $value)
                                            <option @if($id==@$model->{$oldColumnName}) selected @endif value="{{ $id }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        @else


                                        @php
                                        $currentVal = isset($model) && $model->{$name} ? $model->{$name} : $defaultValue;
                                        if(is_object($currentVal)){
                                        $currentVal = \Carbon\Carbon::make($currentVal)->format('Y-m-d');
                                        }
                                        @endphp
                                        <input type="{{ $fieldType }}" value="{{ $currentVal }}" class="form-control {{ $fieldClass }}" name="{{ $name }}">

                                        @endif
                                    </td>

                                    @endforeach


                                </tr>
                                @endforeach

                            </x-slot>




                        </x-tables.repeater-table>
                        {{-- end of fixed monthly repeating amount --}}















































































                    </div>

                    @endforeach
                </div>
            </div>

            @if($modelName == 'CustomerInvoice' || $modelName == 'SupplierInvoice')
            <x-save :hint="__('Hint: If you can not find your customer or supplier is the drop down please create a new from from the Partners Section')" />
			@else 
            <x-save  />
			
            @endif




            <!--end::Form-->

            <!--end::Portlet-->
    </div>


</div>

</div>




</div>









</div>
</div>
</form>

</div>
@if(in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule']))
</div>
@endIf
@endsection
@section('js')
<x-js.commons></x-js.commons>

<script>
    $(document).on('change', '.financial-statement-type', function() {
        validateDuration();
    })
    $(document).on('change', 'select[name="duration_type"]', function() {
        validateDuration();
    })
    $(document).on('change', '#duration', function() {
        validateDuration();
    })

    function validateDuration() {
        let type = $('input[name="type"]:checked').val();
        let durationType = $('select[name="duration_type"]').val();
        let duration = $('#duration').val();
        let isValid = true;
        let allowedDuration = 24;
        if (type == 'forecast' && durationType == 'monthly') {
            allowedDuration = 24;
            isValid = duration <= allowedDuration;
        }
        if (type == 'forecast' && durationType == 'quarterly') {
            allowedDuration = 8;
            isValid = duration <= allowedDuration
        }
        if (type == 'forecast' && durationType == 'semi-annually') {
            allowedDuration = 4
            isValid = duration <= allowedDuration
        }
        if (type == 'forecast' && durationType == 'annually') {
            allowedDuration = 2;
            isValid = duration <= allowedDuration
        }
        if (type == 'actual' && durationType == 'monthly') {
            allowedDuration = 36;
            isValid = duration <= allowedDuration;
        }
        if (type == 'actual' && durationType == 'quarterly') {
            allowedDuration = 12
            isValid = duration <= allowedDuration;
        }
        if (type == 'actual' && durationType == 'semi-annually') {
            allowedDuration = 6;
            isValid = duration <= allowedDuration
        }
        if (type == 'actual' && durationType == 'annually') {
            allowedDuration = 3
            isValid = duration <= allowedDuration
        }
        let allowedDurationText = "{{ __('Allowed Duration') }}";

        $('#allowed-duration').html(allowedDurationText + '  ' + allowedDuration)

        if (!isValid) {
            Swal.fire({
                icon: 'error'
                , title: 'Invalid Duration. Allowed [ ' + allowedDuration + ' ]'
            , })

            $('#duration').val(allowedDuration).trigger('change');

        }


    }

    $(function() {
        $('.financial-statement-type').trigger('change')

    })

</script>

<script>
    $(document).on('click', '.save-form', function(e) {
        e.preventDefault(); {

            let form = document.getElementById('form-id');
            var formData = new FormData(form);
            $('.save-form').prop('disabled', true);

            $.ajax({
                cache: false
                , contentType: false
                , processData: false
                , url: form.getAttribute('action')
                , data: formData
                , type: form.getAttribute('method')
                , success: function(res) {
                    $('.save-form').prop('disabled', false)

                    Swal.fire({
                        icon: 'success'
                        , title: res.message
                        , timer: 1500
                        , showConfirmButton: false

                    }).then(function() {
                        window.location.href = res.redirectTo;
                    });





                }
                , complete: function() {
                    $('#enter-name').modal('hide');
                    $('#name-for-calculator').val('');

                }
                , error: function(res) {
                    $('.save-form').prop('disabled', false);
                    $('.submit-form-btn-new').prop('disabled', false)
                    Swal.fire({
                        icon: 'error'
                        , title: res.responseJSON.message
                    , });
                }
            });
        }
    })

</script>
<script>
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })

</script>
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

    $(function() {

        $('.only-month-year-picker').each(function(index, dateInput) {
            reinitalizeMonthYearInput(dateInput)
        })



    });
    //  $(document).on('change', '#expense_type', function() {
    //      $('.js-parent-to-table').hide();
    //      let tableId = '.' + $(this).val();
    //      $(tableId).closest('.js-parent-to-table').show();
    //
    //  }) 
    $(document).on('click', '.js-type-btn', function(e) {
        e.preventDefault();
        $('.js-type-btn').removeClass('active');
        $(this).addClass('active');
        $('.js-parent-to-table').show();
        let tableId = '.' + $(this).attr('data-value');
        $(tableId).closest('.js-parent-to-table').show();

    })
    $('.js-parent-to-table').show();
    $(function() {
        $('#expense_type').trigger('change')
        $('.js-type-btn.active').trigger('click')
    })

    $(function() {
        $(document).on('click', '.js-show-all-categories-trigger', function() {
            const elementToAppendIn = $(this).parent().find('.js-append-into');
            const texts = [];
            let lis = '';
            text = '<u><a href="#" data-close-new class="text-decoration-none mb-2 d-inline-block text-nowrap ">' + 'Add New' + '</a></u>'
            lis += '<li >' + text + '</li>'
            $(this).closest('table').find('.js-show-all-categories-popup').each(function(index, element) {
                let text = $(element).val().trim();
                if (text && !texts.includes(text)) {
                    texts.push(text)
                    text = '<a href="#" data-add-new class="text-decoration-none mb-2 d-inline-block">' + text + '</a>'
                    lis += '<li >' + text + '</li>'
                }
            })




            elementToAppendIn.removeClass('d-none');
            elementToAppendIn.find('ul').empty().append(lis);
        })


    })
    $(document).on('click', '[data-add-new]', function(e) {
        e.preventDefault();
        let content = $(this).html();
        $(this).closest('.js-common-parent').find('input').val(content);
    })
    $(document).on('click', '[data-close-new]', function(e) {
        e.preventDefault();
        $(this).closest('.js-append-into').addClass('d-none');
        $(this).closest('.js-common-parent').find('input').val('').focus();
    })
    $(document).on('click', function(e) {
        let closestParent = $(e.target).closest('.js-append-into').length;
        if (!closestParent && !$(e.target).hasClass('js-show-all-categories-trigger')) {
            $('.js-append-into').addClass('d-none');
        }
    })
    $(function() {
        $('.repeater-with-select2').closest('.repeater-class').find('[data-repeater-delete]').trigger('click');
        $('.repeater-with-select2').closest('.repeater-class').find('[data-repeater-create]').trigger('click');
    });

</script>
@endsection



@push('js_end')

<script>
    let oldValForInputNumber = 0;
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
        oldValForInputNumber = $(this).val();
        if (isNumber(oldValForInputNumber)) {
            $(this).val('')

        }
    })
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

        if ($(this).val() == '') {
            if (isNumber(oldValForInputNumber)) {
                $(this).val(oldValForInputNumber)
            }
        }
    })

    $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
        if (!$(this).hasClass('exclude-text')) {
            let val = $(this).val()
            val = number_unformat(val)
            if (isNumber(val)) {
                $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)
            }

        }
    })
    $(document).on('click', '.repeat-to-r', function() {
        const columnIndex = $(this).data('column-index');
        const digitNumber = $(this).data('digit-number');
        const val = $(this).parent().find('input[type="hidden"]').val();
        $(this).closest('tr').find('.can-be-repeated-parent').each(function(index, parent) {
            if (index > columnIndex) {
                $(parent).find('.can-be-repeated-text').val(val);
                $(parent).find('.can-be-repeated-text').val(number_format(val, digitNumber));

            }
        })
    })


    $('select.js-condition-to-select').change(function() {
        const value = $(this).val();
        const conditionalValueTwoInput = $(this).closest('tr').find('input.conditional-b-input');
        if (value == 'between-and-equal' || value == 'between') {
            conditionalValueTwoInput.prop('disabled', false).trigger('change');
        } else {
            conditionalValueTwoInput.prop('disabled', true).trigger('change');
        }
    })

    $('select.js-condition-to-select').trigger('change');
    $(document).on('change', '.conditional-input', function() {
        if (!$(this).closest('tr').find('conditional-b-input').prop('disabled')) {
            const conditionalA = $(this).closest('tr').find('.conditional-a-input').val();
            const conditionalB = $(this).closest('tr').find('.conditional-b-input').val();
            if (conditionalA >= conditionalB) {
                if (conditionalA == 0 && conditionalB == 0) {
                    return;
                }
                Swal.fire('conditional a must be less than conditional b value');
                $(this).closest('tr').find('.conditional-a-input').val($(this).closest('tr').find('.conditional-b-input').val() - 1);
            }
        }

    })

</script>
<script>
    const handlePaymentTermModal = function() {
        const parentTermsType = $(this).closest('select').val();
        const tableId = $(this).closest('table').attr('id');
        if (parentTermsType == 'customize') {
            $(this).closest('tr').find('#' + tableId + 'test-modal-id').modal('show')
        }



    };
    $(document).on('change', 'select.payment_terms', handlePaymentTermModal)
    $('select.js-due_in_days').change(function() {
        // const selectValue = $(this).val();
        // $(this).find('option').prop('selected',false)
        // $(this).find('option[value="'+selectValue+'"]').prop('selected',true);
        // reinitializeSelect2();
    })

    //$(document).on('click','option',handlePaymentTermModal)
    $(document).on('change', '.rate-element', function() {
        let total = 0;
        const parent = $(this).closest('tbody');
        parent.find('.rate-element-hidden').each(function(index, element) {
            total += parseFloat($(element).val());
        });
        parent.find('td.td-for-total-payment-rate').html(number_format(total, 2) + ' %');

    })
    $(function() {
        $('.rate-element').trigger('change');
    })

</script>
@if(session()->has('success'))

<script>
    Swal.fire({
        text: "{{ session()->get('success') }}"
        , icon: 'success'
    });

</script>

<script>



</script>
@endif
<script>
    $('select[name="customer_id"],select[name="supplier_id"]').on('change', function(e) {
        const customerOrSupplierId = $(this).val();
        const currentContractId = $('#current-contract-id').val();
        $.ajax({
            url: "{{ route('get.projects.for.customer.or.supplier',['company'=>$company->id]) }}"
            , data: {
                customerOrSupplierId
            }
            , success: function(res) {
                var options = '<option value="0" selected>Select</option>';
                for (var contract of res.projects) {
                    var selected = contract.id == currentContractId ? 'selected' : '';
                    options += `<option ${selected} data-contract-code="${contract.code}" data-contract-date="${contract.start_date}"  value="${contract.id}">${contract.name}</option>`
                    //	options+= `<option data-contract-code="${contract.code}" data-contract-date="${contract.start_date}"  value="${contract.id}">${contract.name}</option>`
                }
                $('select[name="contract_id"]').empty().append(options).trigger('change');
            }
        })
    })
    $('select[name="contract_id"]').on('change', function() {
        const contractId = $(this).val();
        const contractCode = $(this).find('option:selected').attr('data-contract-code');
        const contractDate = $(this).find('option:selected').attr('data-contract-date');
        var currentSalesOrderId = $('#current-sales-order-id').val();
        var currentPurchaseOrderId = $('#current-purchases-order-id').val();
        $('[name*="contract_code"]').val(contractCode);
        $('[name*="contract_date"]').val(contractDate);
        $.ajax({
            url: "{{ route('get.po.or.so.from.contract',['company'=>$company->id]) }}"
            , data: {
                contractId
            }
            , success: function(res) {
                var purchaseOrders = res.purchase_orders;
                var salesOrders = res.sales_orders;
                var purchaseOrdersOptions = '';
                var salesOrdersOptions = '';

                for (var purchaseOrder of purchaseOrders) {
                    var purchaseOrderSelected = purchaseOrder.id == currentPurchaseOrderId ? 'selected' : '';
                    purchaseOrdersOptions += `<option ${purchaseOrderSelected} data-date="${purchaseOrder.start_date_1}" value="${purchaseOrder.id}"> ${purchaseOrder.po_number}</option>`
                }
                $('select[name="purchases_order_id"]').empty().append(purchaseOrdersOptions).trigger('change');

                for (var salesOrder of salesOrders) {
                    var salesOrderSelected = salesOrder.id == currentSalesOrderId ? 'selected' : '';
                    salesOrdersOptions += `<option ${salesOrderSelected} data-date="${salesOrder.start_date_1}" value="${salesOrder.id}"> ${salesOrder.so_number}</option>`
                }
                $('select[name="sales_order_id"]').empty().append(salesOrdersOptions).trigger('change');
            }
        })

    })
    $('select[name="sales_order_id"],select[name="purchases_order_id"]').on('change', function() {
        const date = $(this).find('option:selected').attr('data-date');
        $('input[name*="sales_order_date"]').val(date).trigger('change');
        $('input[name*="purchases_order_date"]').val(date).trigger('change');
    })
    $(function() {
        $('select[name="customer_id"]').trigger('change')
        $('select[name="supplier_id"]').trigger('change')
    })

</script>
@endpush
