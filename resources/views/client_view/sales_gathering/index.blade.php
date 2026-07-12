@extends('layouts.dashboard')
@push('css')
<x-styles.commons></x-styles.commons>
<style>
    .max-w-100 {
        max-width: 100px;
    }

    .show-hide-repeater {
        cursor: pointer
    }

    [data-css-col-name="Code"],
    [data-css-col-name="code"],
    [data-css-col-name="id"],
    [data-css-col-name="ID"],
    [data-css-col-name="Id"],
    [data-css-col-name="Item"],
    [data-css-col-name="item"] {
        max-width: 300px !important;
        min-width: 300px !important;
        width: 300px !important;

    }



    svg[xmlns],
    svg[xmlns] * {
        width: 100%;
        height: 100%;
    }

    .dt-buttons.btn-group.flex-wrap {
        float: right;
    }

    .arrow-right {
        right: 10px !important;
    }

    .arrow-left {
        left: 10px !important;
    }

    .dataTables_filter {
        display: none !important;
    }

    .flex-1 {
        flex: 1 !important;
    }

    tbody td:first-child .kt-option {
        border: none;
        padding: 0 !important;
        position: relative !important;
        top: -20px !important;
        max-width: 30px !important;
        left: 28% !important;
        height: 0 !important;
    }

    th .kt-checkbox.kt-checkbox--brand>span:after {
        border-color: white !important;
    }

    th .kt-checkbox.kt-checkbox--brand>span {
        border-color: white !important;
    }

    th .kt-checkbox.kt-checkbox--brand.kt-checkbox--bold>input~span {
        color: white !important;
    }

    .money-flow-dark .arrow-nav.text-dark {
        color: #f1f5f9 !important;
    }

    .money-flow-dark .table-active {
        background-color: #1e3a5f !important;
        color: #f1f5f9 !important;
    }

    .money-flow-dark .table-active th {
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }

    .loan-schedule-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem !important;
        flex-wrap: nowrap !important;
        min-width: 10.5rem;
    }

    .loan-schedule-actions-form {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .loan-schedule-actions .btn.btn-icon {
        height: 3rem !important;
        width: 3rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0;
    }

    .DTFC_RightBodyLiner .loan-schedule-actions,
    .DTFC_LeftBodyLiner .loan-schedule-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem !important;
        min-width: 10.5rem;
    }

    .DTFC_RightBodyLiner .loan-schedule-actions .btn.btn-icon,
    .DTFC_LeftBodyLiner .loan-schedule-actions .btn.btn-icon {
        height: 3rem !important;
        width: 3rem !important;
    }

</style>
@endpush
@section('css')
<style>
    table {
        white-space: nowrap;

    }

</style>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/af-2.4.0/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/cr-1.5.6/date-1.1.2/fc-4.1.0/fh-3.2.3/r-2.3.0/rg-1.2.0/sl-1.4.0/sr-1.1.1/datatables.min.css" />

<style>
    table.dataTable thead tr>.dtfc-fixed-left,
    table.dataTable thead tr>.dtfc-fixed-right {
        background-color: #086691;
    }

    thead * {
        text-align: center !important;
    }

</style>
@endsection
@section('sub-header')
{{ $modelName === 'ContractLoanSchedule' ? __('Contract Leasing Schedule') : camelToTitle($modelName) }} {{ __('Section') }}
<x-navigators-dropdown :navigators="$navigators ?? []"></x-navigators-dropdown>
@endsection
@section('content')
<div class="money-flow-dark">
    @php
    $user = auth()->user();
    $isScheduleModel = in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true);
    $modelDisplayName = $modelName === 'ContractLoanSchedule' ? __('Contract Leasing Schedule') : camelToTitle($modelName);
    $additionalTitle = '';
    if ($modelName == 'LoanSchedule' && isset($loan)) {
        $additionalTitle = ' [ ' . $loan->getName() . ' ]';
    } elseif ($modelName == 'ContractLoanSchedule' && isset($leasingContract)) {
        $additionalTitle = ' [ ' . $leasingContract->getName() . ' ]';
    }
    $loanScheduleImportParams = [];
    if ($modelName == 'LoanSchedule' && !empty($loanId)) {
        $loanScheduleImportParams = ['medium_term_loan_id' => $loanId];
    } elseif ($modelName == 'ContractLoanSchedule' && !empty($loanId)) {
        $loanScheduleImportParams = ['leasing_contract_id' => $loanId];
    }
    $uploadTableClass = $isScheduleModel
        ? 'kt_table_with_no_pagination_no_fixed_right'
        : 'kt_table_with_no_pagination ';
    @endphp

<div class="row">
    <div class="col-lg-12">
        @if (session('warning'))
        <div class="alert alert-warning">
            <ul>
                <li>{{ session('warning') }}</li>
            </ul>
        </div>
        @endif
    </div>
</div>
@if(count($exportables))
@if($modelName != 'LabelingItem')

<form action="{{ route('multipleRowsDelete', [$company, $modelName]) }}" method="POST">
    @endif
    @csrf
    @method('delete')
    <x-table :instructions-icon="1" :notPeriodClosedCustomerInvoices="$notPeriodClosedCustomerInvoices??[]" :tableTitle="$modelDisplayName.' '.__(' Table') . $additionalTitle " :tableClass="$uploadTableClass" href="#" :importHref="$user->can($uploadPermissionName) ? route('salesGatheringImport',['company'=>$company->id , 'model'=>$modelName]) : '#'" :exportHref="$user->can($exportPermissionName) ? route('salesGathering.export',['company'=>$company->id , 'model'=>$modelName]):'#' " :exportTableHref="$user->can($uploadPermissionName)?route('table.fields.selection.view',[$company,$modelName,'sales_gathering']) : '#'" :truncateHref="$user->can($deletePermissionName)?route('truncate',[$company,$modelName]):'#' ">
        @slot('table_header')

        <tr class="table-active text-center">
            @if($user->can($deletePermissionName))
            <th class="">

                <label style="top:-10px;right:-7px" class="kt-option d-inline-flex border-none p-0 mt-[-15px] top-[-10] position-relative">
                    <span class="kt-option__control">
                        <span class="kt-checkbox kt-checkbox--bold kt-checkbox--brand kt-checkbox--check-bold" checked>
                            <input class="rows" type="checkbox" id="select_all">
                            <span></span>
                        </span>
                    </span>


                </label>


            </th>
            @endif
        


            @foreach ($viewing_names as $name)

            <th @if($modelName=='LabelingItem' ) data-css-col-name="{{ $name }}" @endif>{{ __($name) }}</th>
            @endforeach

        

            @if($isScheduleModel)
            <th class="max-w-100">{{ __('Status') }}</th>
            <th>{{ __('Remaining') }}</th>
            @endif

            <th>{{ __('Actions') }}</th>
        </tr>
        @endslot
        @slot('table_body')
        @foreach ($salesGatherings as $index=>$item)


        <tr>
            @if($user->can($deletePermissionName))
            <td class="text-center">
                <label class="kt-option">
                    <span class="kt-option__control">
                        <span class="kt-checkbox kt-checkbox--bold kt-checkbox--brand kt-checkbox--check-bold" checked>

                            <input class="rows" type="checkbox" name="rows[]" value="{{ $item->id }}">
                            <span></span>
                        </span>
                    </span>
                    <span class="kt-option__label">
                        <span class="kt-option__head">

                        </span>

                    </span>
                </label>
            </td>

            @endif




            @foreach ($db_names as $name)

            @if ($name == 'date' || $name=='invoice_due_date' || $name == 'invoice_date')

            <td class="text-center">{{ isset($item->$name) ? date('d-M-Y',strtotime($item->$name)):  '-' }}</td>
            @elseif($name == 'invoice_amount' || $name == 'vat_amount' || $name == 'withhold_amount' || $name == 'collected_amount' || $name == 'paid_amount' || $name=='net_balance'|| $name=='net_invoice_amount')

            <td class="text-center">{{ number_format($item->$name?:0 ,2 ) }} </td>
            @elseif($name == 'drawee_bank')
            <td class="text-center">{{ $item->getDraweeBankName() }}</td>
            @elseif($name == 'account_number')
            <td class="text-center">{{ $item->getAccountNumber() ?: '-' }}</td>
            @elseif($name == 'cheque_number')
            <td class="text-center">{{ $item->getChequeNumber() ?: '-' }}</td>
            @else
            <td @if($modelName=='LabelingItem' ) data-css-col-name="{{ $name??'' }}" @endif class="text-center">
                @if($name == 'beginning_balance' || $name =='schedule_payment' || $name =='cheque_amount' || $name =='interest_amount' || $name == 'principle_amount' || $name == 'end_balance')
                @php
                $item->$name = number_format($item->$name);
                @endphp
                @endif
                {{ qrcodeSpacing($item->$name??'') }}


                @endif

                @endforeach



          


            @if($isScheduleModel)
            <td style="white-space: wrap !important;" class="text-capitalize text-wrap max-w-100">
                {{ $item->getStatusFormatted() }}
            </td>
            <td class="text-center">
                {{ $item->getRemainingFormatted() }}
            </td>
            @endif


            <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">

                <div class="loan-schedule-actions">

                    @if($modelName == 'LoanSchedule' && $item->hasMediumTermLoan())
                    <a href="{{ route('view.loan.schedule.settlements',['company'=>$company->id , 'loanSchedule'=>$item->id]) }}" class="btn btn-secondary btn-outline-hover-primary btn-icon" title="{{ __('Settlement') }}">
                        <i class="fa fa-dollar-sign"></i>
                    </a>
                    @endif

                    @if($modelName == 'ContractLoanSchedule' && $item->canSettle())
                    <a href="{{ route('view.contract.loan.schedule.settlements',['company'=>$company->id , 'contractLoanSchedule'=>$item->id]) }}" class="btn btn-secondary btn-outline-hover-primary btn-icon" title="{{ __('Settlement') }}">
                        <i class="fa fa-dollar-sign"></i>
                    </a>
                    @endif

                    <form method="post" action="{{route('salesGathering.destroy',[$company->id,$item->id,$modelName])}}" class="loan-schedule-actions-form">
                        @method('DELETE')
                        @csrf
                        <input type="hidden" name="modelType" value="{{ $modelName }}">
                        <a class="btn btn-secondary btn-outline-hover-primary btn-icon" title="Edit" href="{{route('edit.sales.form',['company'=>$company->id,'model'=>$modelName , 'modelId'=>$item->id])}}"><i class="fa fa-edit"></i></a>
                        <button type="submit" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete"><i class="fa fa-trash-alt"></i></button>
                    </form>
                </div>
            </td>
        </tr>

        @endforeach
        @endslot
    </x-table>
    @if($modelName != 'LabelingItem')

</form>
@endif
<div class="kt-portlet">
    <div class="kt-portlet__head kt-portlet__head--lg">
        <div class="kt-portlet__head-label d-flex justify-content-start">
            {{ $salesGatherings->links() }}
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="kt_modal_2" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">{{__("Instructions")}}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <p class="pop-up-font">
                    <b> 1. Click on Template Download button </b>
                </p>
                <p class="pop-up-font">
                    <b> 2. Select the fields that suits your sales data structure </b>
                </p>
                <p class="pop-up-font">
                    <b> 3. Click download </b>
                </p>
                <p class="pop-up-font">
                    <b> 4. Fill your excel template </b>
                </p>
                <p class="pop-up-font">
                    <b> 5. Click Upload Data, choose your excel file then select date format finally click save </b>
                </p>
                <p class="pop-up-font">
                    <b> 6. Review your data, and then click Save Table </b>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


</div>
@endsection

@section('js')
@include('js_datatable')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
@if($modelName != 'LabelingItem' && $modelName != 'LoanSchedule' && $modelName != 'ContractLoanSchedule')
<script>
    $(document).on('click', '#open-instructions', function(e) {
        e.preventDefault();
        $('#kt_modal_2').modal('show');
    })


    $(function() {
        $("td:not(.not-editable)").dblclick(function() {
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
@endif
<script>
    $('#select_all').change(function(e) {
        if ($(this).prop("checked")) {
            $('.rows').prop("checked", true);
        } else {
            $('.rows').prop("checked", false);
        }
    });









    window.addEventListener('scroll', function() {
        const top = window.scrollY > 140 ? window.scrollY + 210 : 250;

        $('.arrow-nav').css('top', top + 'px')
    })
    if ($('.kt-portlet--mobile > .kt-portlet__body').length) {
        const $uploadTableBody = $('.kt-portlet--mobile > .kt-portlet__body').first();

        $uploadTableBody.append(`
								<i class="cursor-pointer text-dark arrow-nav  arrow-left fa fa-arrow-left"></i>
								<i class="cursor-pointer text-dark arrow-nav arrow-right fa  fa-arrow-right"></i>
								`)


        $(document).on('click', '.arrow-nav', function() {
            const scrollLeftOfTableBody = $uploadTableBody[0].scrollLeft
            const scrollByUnit = 500
            if (this.classList.contains('arrow-right')) {
                document.querySelector('.dataTables_scrollBody').scrollLeft += scrollByUnit

            } else {
                document.querySelector('.dataTables_scrollBody').scrollLeft -= scrollByUnit

            }
        })

        window.dispatchEvent(new Event('scroll'));

    }

</script>
<script>
    $(document).on('click', '.show-hide-repeater', function() {
        const query = this.getAttribute('data-query')
        $(query).fadeToggle(300)

    })

</script>
<x-js.commons></x-js.commons>
@endsection
