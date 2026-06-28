@extends('layouts.dashboard')
@push('css')
<x-styles.commons></x-styles.commons>
@endpush
@section('css')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.3/r-2.3.0/rg-1.2.0/sl-1.4.0/sr-1.1.1/datatables.min.css" />
@php
$redirectUrl = null;
if($modelName == 'CustomerInvoice'){
	$redirectUrl = route('view.balances',['company'=>$company->id,'modelType'=>'CustomerInvoice']);
}
elseif($modelName == 'SupplierInvoice'){
	$redirectUrl = route('view.balances',['company'=>$company->id,'modelType'=>'SupplierInvoice']);
}
elseif($modelName == 'LoanSchedule'){
	$redirectUrl = route('view.uploading',['company'=>$company->id,'model'=>'LoanSchedule','loanId'=>$loanId ?? request('medium_term_loan_id') ?? session('loan_schedule_import_loan_id_' . $company->id)]);
}
elseif($modelName == 'ContractLoanSchedule'){
	$redirectUrl = route('view.uploading',['company'=>$company->id,'model'=>'ContractLoanSchedule','loanId'=>$loanId ?? request('leasing_contract_id') ?? session('contract_loan_schedule_import_contract_id_' . $company->id)]);
}
elseif($modelName =='ExpenseAnalysis'){
	$redirectUrl = route('view.expense.analysis.dashboard',['company'=>$company->id]);
}
$additionalArray = [];
if ($modelName == 'LoanSchedule' && ($loanId ?? request('medium_term_loan_id') ?? session('loan_schedule_import_loan_id_' . $company->id))) {
    $additionalArray = ['medium_term_loan_id' => $loanId ?? request('medium_term_loan_id') ?? session('loan_schedule_import_loan_id_' . $company->id)];
}
if ($modelName == 'ContractLoanSchedule' && ($loanId ?? request('leasing_contract_id') ?? session('contract_loan_schedule_import_contract_id_' . $company->id))) {
    $additionalArray = ['leasing_contract_id' => $loanId ?? request('leasing_contract_id') ?? session('contract_loan_schedule_import_contract_id_' . $company->id)];
}


@endphp 
<style>
.mx-auto{
	margin-left:auto;
	margin-right:auto;
}
    .table-bordered.table-hover.table-checkable.dataTable.no-footer.fixedHeader-floating {
        display: none
    }

    table.dataTable thead tr>.dtfc-fixed-left,
    table.dataTable thead tr>.dtfc-fixed-right {
        background-color: #086691;
    }

    thead * {
        text-align: center !important;
    }

</style>
<style>
    table {
        white-space: nowrap;
    }

    .bg-table-head {
        background-color: #075d96;
        color: white !important;
    }

    tbody .kt-option {
        border: none !important;
        padding: 0 !important;
        position: relative !important;
        top: -20px !important;
        max-width: 30px !important;
        left: 28% !important;
        height: 0 !important;
    }

    th .kt-checkbox.kt-checkbox--brand>span:after,
    th .kt-checkbox.kt-checkbox--brand>span {
        border-color: white !important;
    }

</style>
@endsection
@section('sub-header')
{{ camelToTitle($modelName) }} {{ __('Import') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{ camelToTitle($modelName) }}
                    </h3>
                </div>
            </div>
        </div>

        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action={{ route('salesGatheringImport', array_merge(['company'=>$company->id , 'model'=>$modelName], $additionalArray)) }} enctype="multipart/form-data">
            @csrf
            @if($modelName == 'LoanSchedule' && !empty($additionalArray['medium_term_loan_id']))
                <input type="hidden" name="medium_term_loan_id" value="{{ $additionalArray['medium_term_loan_id'] }}">
            @endif
            @if($modelName == 'ContractLoanSchedule' && !empty($additionalArray['leasing_contract_id']))
                <input type="hidden" name="leasing_contract_id" value="{{ $additionalArray['leasing_contract_id'] }}">
            @endif
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ camelToTitle($modelName) . ' ' . __('Import') }}
							<span class="text-red">
								(Maximum uploaded rows at a time 50,000 rows)
							</span>
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ __('Import File') }} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input required type="file" name="excel_file" class="form-control" placeholder="{{ __('Import File') }}">
                                    <x-tool-tip title="{{ __('Vero Analysis') }}" />
                                </div>
                            </div>
                        </div>
                        @if($modelName != 'LabelingItem')

                        <div class="col-md-6">
                            <label>{{ __('Date Formatting') }} @include('star')</label>
                            <div class="kt-input-icon">
                                <select name="format" class="form-control" required>
                                    <option value="">{{ __('Select') }}</option>
                                    <option value="d-m-Y">{{ __('Day-Month-Year') }} eg [ 15-01-2024]</option>
									  <option value="d-M-Y" >{{__('Day-Month-Year')}} eg [ 15-Jan-2024]</option>
                                    <option value="m-d-Y">{{ __('Month-Day-Year') }} eg [ 05-15-2024] </option>
                                    <option value="Y-m-d">{{ __('Year-Month-Day') }} eg [2024-05-15] </option>
                                    <option value="Y-d-m">{{ __('Year-Day-Month') }} eg [2024-15-05] </option>
                                </select>
                                <x-tool-tip title="{{ __('Vero Analysis') }}" />
                            </div>
                        </div>
                        @endif
                    </div>
                    @php $active_job = App\Models\ActiveJob::where('company_id', $company->id)
                            ->where('status', 'test_table')
                            ->where('model_name', 'SalesGatheringTest')
							->where('model',$modelName)
                            ->first(); @endphp
                    @php $active_job_for_saving = App\Models\ActiveJob::where('company_id', $company->id)
                    ->where('status', 'save_to_table')
                    ->where('model_name', 'SalesGatheringTest')
                    ->where('model',$modelName)
                    ->first(); @endphp
                    @php
                    use Illuminate\Support\Facades\Cache;
                    $canViewPleaseReviewMessage = !hasFailedRow($company->id,$modelName)&&hasCachingCompany($company->id,$modelName) && ! $active_job_for_saving && Cache::get(getShowCompletedTestMessageCacheKey($company->id,$modelName)) && ! (bool)Cache::get(getCanReloadUploadPageCachingForCompany($company->id,$modelName) );
                    @endphp
					@if($company->hasLastCurrentUploadFileForModel($modelName))
					<h4>{{ __('Current File Name :') .' ' . $company->getCurrentLastFileNameForModel($modelName) }}</h4>
					@elseif(hasFailedRow($company->id,$modelName))
					<h4>{{ __('Current Failed File Name :') .' ' . $company->getCurrentLastFileNameForModel($modelName) }}</h4>
					@elseif($company->hasLastSuccessfullyUploadFileForModel($modelName))
					<h4>{{ __('Last Successfully Uploaded File Name :') .' '. $company->getSuccessLastFileNameForModel($modelName) }}</h4>
					@endif 
                    @if($canViewPleaseReviewMessage)
                    <h4 id="please-review-and-click-save" class="text-center alert alert-info " style="text-transform:capitalize;justify-content:center">{{ __('Please review And Click Save Data Button') }}</h4>
					
                    @endif
                    @if ($active_job)
                    <div class="kt-section__content uploading_div">
                        <label class="text-success text-xl-center"> <b> {{ __('Uploading') }}</b> @include('star')</label>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated  bg-success" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <x-custom-button-name-to-submit :displayName="__('Upload')" />

        </form>

        {{-- @if(!$active_job_for_saving && !$active_job && !$canViewPleaseReviewMessage) --}}
   

        {{-- @endif --}}

        <!--end::Form-->
        <form action="{{ route('deleteMultiRowsFromCaching', ['company'=>$company , 'modelName'=>$modelName]) }}" method="POST" encrypt="multipart/form-data">
            @csrf
            @method('DELETE')

            <x-table :notPeriodClosedCustomerInvoices="$notPeriodClosedCustomerInvoices??[]" :lastUploadFailedHref="hasFailedRow($company->id,$modelName)?route('last.upload.failed',['company'=>$company->id , 'model'=>$modelName]):'#'" :tableTitle="__(\App\Helpers\HVero::capitializeType($modelName). ' ' . 'Table')" :href="route('salesGatheringTest.insertToMainTable',array_merge(['company'=>$company->id , 'modelName'=>$modelName],$additionalArray))" :icon="__('file-import')" :firstButtonName="__('Save Data')" :tableClass="'kt_table_with_no_pagination'" :truncateHref="route('deleteAllCaches',[$company,$modelName])">

                @slot('table_header')

                @if ($active_job_for_saving)
                <div class="row uploading_div_for_saving_data mb-5">
                    <div class="col-md-2"></div>
                    <div class="col-md-8">

                        <div class="kt-section__content text-center ">
                            <label id="saving_data" class="text-success text-xl-center"> <b> {{ __('Saving Data') }}</b> @include('star')</label>
                            <div class="progress ">
                                <div id="progress_id" class="progress-bar progress-bar-striped progress-bar-animated  bg-success" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                                </div>


                            </div>
                            <span id="percentage_value" style="display: block;margin-top:10px;font-size:1.5rem;color:#1dc9b7 !important;font-weight:bold;"> 0 % </span>
                        </div>
                    </div>
                </div>

                <br>
                @endif

                <tr class="table-active text-center">	
                    <th class="select-to-delete">Select To Delete </th>
					
                    @foreach ($viewing_names as $name)
                    <th>{{ __($name) }}</th>
					
                    @endforeach
					
					
                    <th>{{ __('Actions') }}</th>
                </tr>
                @endslot
                @slot('table_body')
                @foreach ($salesGatherings->take(20) as $index=> $item)

                <tr>
                    <td class="text-center">
                        <label class="kt-option">
                            <span class="kt-option__control">
                                <span class="kt-checkbox kt-checkbox--bold kt-checkbox--brand kt-checkbox--check-bold" checked>
                                    <input class="rows" type="checkbox" name="rows[]" value="{{ $item['id'] ?? 0 }}">
                                    <span></span>
                                </span>
                            </span>
                            <span class="kt-option__label">
                                <span class="kt-option__head">

                                </span>

                            </span>
                        </label>
                    </td>
	
                    @foreach ($db_names as $name)
                    @if ($name == 'date')
                    <td class="text-center">
                        {{ isset($item[$name]) ? date('d-M-Y', strtotime($item[$name])) : '-' }}</td>
                    @else
				
                    <td class="text-center">{{ $item[$name] ?? '-' }}</td>
                    @endif
                    @endforeach
					
					
					

                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                        <span class="d-flex justify-content-center" style="overflow: visible; position: relative; width: 110px;">
                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('salesGatheringTest.editCachedRow', array_merge(['company'=>$company->id, 'model'=>$modelName, 'rowId'=>$item['id']], $additionalArray)) }}"><i class="fa fa-pen-alt"></i></a>
                        </span>
                    </td>
                </tr>
                @endforeach
				
				
                @endslot
            </x-table>
        </form>
        <!--end::Portlet-->
    </div>
    <div class="kt-portlet text-center">
        <div class="kt-portlet__head kt-portlet__head--lg">
            <div class="kt-portlet__head-label d-flex justify-content-start">
                {{ $salesGatherings->appends(Request::except('page'))->links() }}
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('js')
@include('js_datatable')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
</script>
@if($active_job_for_saving)
<script>
    setInterval(() => {
        if (!$('#please-review-and-click-save').length) {
            $('.kt-portlet__head-actions > a').each((index, a) => {
                $(a).addClass('disabled');
            })
        } else {
            $('.kt-portlet__head-actions > a').each((index, a) => {
                $(a).removeClass('disabled');
            })
        }
    }, 1000);

</script>
@endif
@if ($active_job)
<script>
    var row = '1';
    $(document).ready(function() {

        setInterval(function() {

            $.ajax({
                type: 'GET'
                , data: {
                    'id': "{{ $active_job->id }}"
                }
                , url: "{{ route('active.job', ['modelName'=>$modelName , 'company'=>$company->id]) }}"
                , dataType: 'json'
                , accepts: 'application/json'
            }).done(function(data) {

                if (data == '0' && row == '1') {
                    $('.uploading_div').fadeOut(300);
                    location.reload();
                }
                row = data;
            });
        }, 3000);

    });

</script>
@endif


@if ($active_job_for_saving )
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    $(document).ready(function() {
        setInterval(function() {
            $.ajax({
                type: 'post'
                , url: "/get-uploading-percentage/" + "{{$company->id}}" + "/" + "{{ $modelName }}"
                , data: {
                    '_token': "{{csrf_token()}}"
                , },

                success: function(data) {
                    $('#progress_id').css('width', (data.totalPercentage) + '%');
                    $('#percentage_value').html(data.totalPercentage.toFixed(2) + ' %');
                    if (parseFloat(data.totalPercentage) >= 100 || data.reloadPage) {
                        $('#saving_data').html("{{ __('Parsing Data .. Please Wait') }}");
                        const company_id = "{{ $company->id }}"
                        Swal.fire({
                            position: 'center'
                            , icon: 'success'
                            , title: '{{ __("Uploading Proccess Has Completed Successfully !") }}'
                            , showConfirmButton: false
                            , timer: 1500
                        }).then(function() {
                            window.location.href = "{{ $redirectUrl }}"

                        })



                    }
                    if (data.reloadPage) {
                        // window.location.reload();
                    }
                }
                , error: function(reject) {}
            });
        }, 5000)
    })

</script>
@endif
@if(hasFailedRow($company->id,$modelName))
<script>
    Swal.fire({
        title: "{{ __('Last Upload Failed ! .. Please Review Last Upload Failed Rows Below') }}"
        , icon: 'error'
    })

</script>
@endif
<script>
    $('#select_all').change(function(e) {
        if ($(this).prop("checked")) {
            $('.rows').prop("checked", true);
        } else {
            $('.rows').prop("checked", false);
        }
    });
    $(function() {
        $("td").dblclick(function() {
            var OriginalContent = $(this).text();
            $(this).addClass("cellEditing");
            $(this).html("<input type='text' value='" + OriginalContent + "' />");
            $(this).children().first().focus();
            $(this).children().first().keypress(function(e) {
                if (e.which == 13) {
                    var newContent = $(this).val();
                    $(this).parent().text(newContent);
                    $(this).parent().removeClass("cellEditing");
                }
            });
            $(this).children().first().blur(function() {
                $(this).parent().text(OriginalContent);
                $(this).parent().removeClass("cellEditing");
            });
            $(this).find('input').dblclick(function(e) {
                e.stopPropagation();
            });
        });
    });
	
	

</script>
@endsection
