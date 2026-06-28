@extends('layouts.dashboard')
@push('css')
<x-styles.commons></x-styles.commons>
@endpush
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('sub-header')
{{ __('Export fields') }} — {{ camelToTitle($modelName) }}
@endsection
@section('content')
<div class="money-flow-dark">
<script src="{{ asset('custom/axios.js') }}"></script>

<script>
    setInterval(() => {
        let company_id = "{{ $company->id }}"
        axios.get('/removeSessionForRedirect').then(res => {
            if (res.data.status) {
                window.location.href = res.data.url
            }

        })
    }, 2000)

</script>

<div class="row">
    <div class="col-lg-12">
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    <div class="col-lg-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{ __('Please Choose Fields That You Need To Be in Your Excel Sheet') }}
                    </h3>
                </div>
            </div>
        </div>
        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('table.fields.selection.save', [$company,$model, $modelName]) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="model_name" value="{{$model}}">
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary ">
                            {{ __('Fields Names') }}
                        </h3>
                    </div>

                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row form-group-marginless">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-lg-12 ">
                                    <label class="kt-option bg-secondary">
                                        <span class="kt-option__control">
                                            <span class="kt-checkbox kt-checkbox--bold kt-checkbox--brand kt-checkbox--check-bold" checked>
                                                <input type="checkbox" id="select_all" {{count($selected_fields) == count($columnsWithViewingNames) ? 'checked' : ''}}>
                                                <span></span>
                                            </span>
                                        </span>
                                        <span class="kt-option__label">
                                            <span class="kt-option__head">
                                                <span class="kt-option__title">
                                                    <b>
                                                        {{ __('Select All') }}
                                                    </b>
                                                </span>

                                            </span>
                                           
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row">
                                @foreach ($columnsWithViewingNames as $fieldName => $displayName)
                                @if(!hideExportField($fieldName , $columnsWithViewingNames))
                                @php
                                            $status_disanbeled_fields = $fieldName == 'net_sales_value' ||  $fieldName == 'invoice_status' || 
                                                            ($fieldName == 'sales_value'  && count(array_intersect($selected_fields, ['quantity_discount','cash_discount','special_discount','other_discounts'])) == 0 );
															$hiddenFields = ['invoice_status','net_balance'];
										if($modelName == 'LoanSchedule'){
											$status_disanbeled_fields = true;
										}
										@endphp
										
										@if(!in_array($fieldName,$hiddenFields))
                                <div class="col-lg-6">
                                    <label class="kt-option @if ($status_disanbeled_fields) not_allowed_curser @endif">
                                        <span class="kt-option__control ">

                                            <label class="kt-checkbox kt-checkbox--bold kt-checkbox--brand
                                                        @if ($status_disanbeled_fields)
                                                            kt-checkbox--disabled
                                                        @endif">
                                                <input type="checkbox" name="fields[]" value="{{$fieldName}}" @if ($fieldName !='net_sales_value' ) class="fields" @endif @if (((false !==$found=array_search($fieldName,$selected_fields)) || $fieldName=='net_sales_value'||$fieldName=='invoice_status'  ) || $modelName == 'LoanSchedule' ) checked @endif @if ($status_disanbeled_fields) disabled="disabled" style="cursor: not-allowed;" @endif id="{{$fieldName}}">
                                                <span></span>
                                            </label>

                                           
                                        </span>
                                        <span class="kt-option__label">
                                            <span class="kt-option__head">
                                                <span class="kt-option__title">
                                                    {{ __($displayName) }}
                                                    @if($fieldName == 'document_type')
                                                    <span> ( Only Allowed Content
                                                        <u>
                                                            [INV , inv , invoice , INVOICE ,فاتوره ]
                                                        </u> )
                                                    </span>

                                                    @endif
                                                </span>

                                            </span>
                                        </span>
                                    </label>
                                </div>
								@endif
                                @endif

                                @endforeach
                            </div>
                        </div>
                        {{-- <label class="col-lg-1 col-form-label"> </label> --}}
                    </div>
                </div>
            </div>

            <x-custom-button-name-to-submit :displayName="__('Download')" />
        </form>

        <!--end::Form-->

        <!--end::Portlet-->
    </div>
</div>
</div>
@endsection
@section('js')
<!--begin::Page Scripts(used by this page) -->
<!--end::Page Scripts -->
<script>
    $('#select_all').change(function(e) {
        if ($(this).prop("checked")) {
            $('.fields').prop("checked", true);
        } else {
            $('.fields').prop("checked", false);
        }
        $('#date').prop('checked', true)
    });
    $('#quantity_discount,#cash_discount,#special_discount,#other_discounts').change(function(e) {
        if ($('#quantity_discount').prop("checked") || $('#cash_discount').prop("checked") || $('#special_discount').prop("checked") || $('#other_discounts').prop("checked")) {
            $('#sales_value').prop("checked", true);
        } else {
            $('#sales_value').prop("checked", false);
        }
    });

</script>

<script>
    $('#date').on('change', function() {
        $(this).prop('checked', true)
    })
    $('#date').prop('checked', true)

</script>
<script>
$('#product_or_service').on('change',function(){
	const val = $(this).val() ;
	const isChecked = $(this).is(":checked")
	if(isChecked){
		$('#product_item').prop('disabled',false)
	}
	else{
		$('#product_item').prop('checked',false).prop('disabled',true)
	}
})
$('#product_or_service').trigger('change')
</script>
@endsection
