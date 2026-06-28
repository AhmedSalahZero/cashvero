@extends('layouts.dashboard')
@section('css')
@php
use App\Models\CashExpense;
$selectedBanks = [];
$banks = [];
@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
#kt_content .money-flow-dark th:not(.bank-max-width),
#kt_content .money-flow-dark td:not(.bank-max-width){
		text-wrap:nowrap !important;
	}
#kt_content .money-flow-dark td{
	vertical-align:middle !important;
}

.money-flow-dark .color-green{
	color:white !important;
	background-color:green !important;
}

    .money-flow-dark .kt-portlet__body {
        padding-top: 0 !important;
    }

    .money-flow-dark input[type="checkbox"] {
        cursor: pointer;
    }

    .money-flow-dark .bank-max-width {
        max-width: 200px !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Cash Expense') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
               

                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == CashExpense::OUTGOING_TRANSFER ? 'active':''  }}" data-toggle="tab" href="#{{ CashExpense::OUTGOING_TRANSFER }}" role="tab">
                        <i class="fa fa-money-check-alt"></i>{{ __('Outgoing Transfer') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == CashExpense::CASH_PAYMENT ? 'active':''  }}" data-toggle="tab" href="#{{ CashExpense::CASH_PAYMENT }}" role="tab">
                        <i class="fa fa-money-check-alt"></i>{{ __('Cash Payment') }}
                    </a>
                </li>
 				<li class="nav-item">
                    <a onclick="return false" class="nav-link {{  Request('active') == CashExpense::PAYABLE_CHEQUE ?'active':'' }}" data-toggle="tab" href="#{{ CashExpense::PAYABLE_CHEQUE }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Payable Cheques') }}
                    </a>
                </li>
              

            </ul>
			@if(auth()->user()->can('create cash expenses'))
            <div class="flex-tabs">
			<a href="{{route('create.cash.expense',['company'=>$company->id])}}" class="btn  btn-sm active-style btn-icon-sm align-self-center">
                <i class="fas fa-plus"></i>
                {{ __('Cash Expense') }}
            </a>
			</div>
			@endif 

        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{   Request('active') == CashExpense::PAYABLE_CHEQUE ?'active':'' }}" id="{{ CashExpense::PAYABLE_CHEQUE }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="CashExpense::PAYABLE_CHEQUE" :title="__('Payable Cheques')" :startDate="$filterDates[CashExpense::PAYABLE_CHEQUE]['startDate']??''" :endDate="$filterDates[CashExpense::PAYABLE_CHEQUE]['endDate']??''">
                        <x-export-cash-expense :route-redirect="route('view.cash.expense',['company'=>$company->id])" :route-action="route('cash.expense.payable.cheque.mark.as.paid',['company'=>$company->id])" :popup-title="__('Do You Want To Mark This Cheque / Cheques As Paid ?')" :account-types="$accountTypes" :financialInstitutionBanks="$financialInstitutionBanks" :search-fields="$payableChequesTableSearchFields" :cash-expense-type="CashExpense::PAYABLE_CHEQUE" :has-search="1" :has-batch-collection="1" :banks="$banks??[]" :selectedBanks="$selectedBanks" href="{{route('create.cash.expense',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>

                    <div class="kt-portlet__body">

                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="align-middle">{{ __('Select') }}</th>
                                    <th class="align-middle">{{ __('Type') }}</th>
                                    {{-- <th class="align-middle bank-max-width">{{ __('Status') }}</th> --}}
                                    {{-- <th class="align-middle bank-max-width">{{ __('Supplier Name') }}</th> --}}
                                    <th class="align-middle">{!! __('Category <br> Name') !!}</th>
                                    <th class="align-middle">{!! __('Expense <br> Name') !!}</th>
                                    <th class="align-middle">{!! __('Payment <br> Date') !!}</th>
                                    <th class="align-middle">{!! __('Cheque<br>Number') !!}</th>
                                    <th class="align-middle">{!! __('Cheque<br>Amount') !!}</th>
                                    <th class="align-middle">{{ __('Currency') }}</th>
                                    <th class="align-middle bank-max-width ">{{ __('Payment Bank') }}</th>
                                    <th class="align-middle bank-max-width">{{ __('Account Type') }}</th>
                                    <th class="align-middle">{{ __('Account No') }}</th>
                                    <th class="align-middle">{!! __('Due<br>Date') !!}</th>
                                    {{-- <th class="align-middle">{!! __('Due <br> After Days') !!}</th> --}}
                                    <th class="align-middle">{!! __('Status') !!}</th>
                                    <th class="align-middle">{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payableCheques as $cashExpense)
                                <tr>
                                    <td>
                                        <input style="max-height:25px;" id="cash-send-to-collection{{ $cashExpense->id }}" type="checkbox" name="second_to_collection[]" value="{{ $cashExpense->id }}" data-money-type="{{ CashExpense::PAYABLE_CHEQUE }}" class="form-control checkbox js-send-to-collection">
                                    </td>
                                    <td class="bank-max-width @if($cashExpense->payableCheque->getStatus() == 'paid') exclude-td font-weight-bold text-success color-green @endif ">{{ $cashExpense->payableCheque->getStatusFormatted() }}</td>
                                    {{-- <td class="bank-max-width">{{ $cashExpense->getSupplierName() }}</td> --}}
                                    <td class="text-nowrap">{{ $cashExpense->getExpenseCategoryName() }}</td>
                                    <td class="text-nowrap">{{ $cashExpense->getExpenseName() }}</td>
                                    <td class="text-nowrap">{{ $cashExpense->getPaymentDateFormatted() }}</td>
                                    <td>{{ $cashExpense->payableCheque->getChequeNumber() }}</td>
                                    <td>{{ $cashExpense->getPaidAmountFormatted() }}</td>
                                    <td class="text-transform" data-currency="{{ $cashExpense->getCurrency() }}">{{ $cashExpense->getCurrencyToPaymentCurrencyFormatted() }}</td>
                                    <td class="bank-max-width ">{{ $cashExpense->payableCheque->getPaymentBankName() }}</td>
                                    {{-- <td class="bank-max-width ">{{ $cashExpense->payableCheque->getPaymentBankName() }}</td> --}}
                                    <td class="bank-max-width">{{ $cashExpense->payableCheque->getAccountTypeName() }}</td>
                                    <td class="text-nowrap">{{ $cashExpense->payableCheque->getAccountNumber() }}</td>
                                    <td class="text-nowrap">{{ $cashExpense->payableCheque->getDueDateFormatted() }}</td>
                                    {{-- <td>{{ $cashExpense->payableCheque->getDueAfterDays() }}</td> --}}
									@php
										$dueStatus = $cashExpense->payableCheque->getDueStatusFormatted() ;
									@endphp
									
                                    <td class="font-weight-bold bank-max-width" style="color:{{ $dueStatus['color'] }}!important">
									@if($cashExpense->payableCheque->getStatus() == 'paid') 
									-
									@else  
									{{ $dueStatus['status'] }}
									@endif
									</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
											@include('reports._user_comment_modal',['model'=>$cashExpense])
											@include('reports._integrated_modal',['model'=>$cashExpense])
											@if(auth()->user()->can('update cash expenses'))
											@include('reports._review_modal',['model'=>$cashExpense])
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.cash.expense',['company'=>$company->id,'cashExpense'=>$cashExpense->id]) }}"><i class="fa fa-pen-alt"></i></a>
									@endif 
                                            <a data-id="{{ $cashExpense->id }}" data-type="single" data-currency="{{ $cashExpense->getCurrency() }}" data-due-date="{{ formatDateForDatePicker($cashExpense->getPayableChequeDueDate()) }}" data-money-type="{{ CashExpense::PAYABLE_CHEQUE }}" data-toggle="modal"  data-target="#send-to-under-collection-modal{{ CashExpense::PAYABLE_CHEQUE }}" type="button" class="btn js-can-trigger-cheque-under-collection-modal btn-secondary btn-outline-hover-primary btn-icon" title="{{ __('Mark As Paid') }}" href=""><i class="fa fa-money-bill"></i></a>
								
											@if(!$cashExpense->isOpenBalance())
                                            <a data-toggle="modal" data-target="#delete-cheque-id-{{ $cashExpense->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-cheque-id-{{ $cashExpense->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.cash.expense',['company'=>$company->id,'cashExpense'=>$cashExpense->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											@endif 
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
						{{ $payableCheques->appends(array_merge(request()->all(),['active' => CashExpense::PAYABLE_CHEQUE]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>




        





            <!--End:: Tab Content-->

            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == CashExpense::OUTGOING_TRANSFER ? 'active':''  }}" id="{{ CashExpense::OUTGOING_TRANSFER }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">

                    <x-table-title.with-two-dates :type="CashExpense::OUTGOING_TRANSFER" :title="__('Outgoing Transfer')" :startDate="$filterDates[CashExpense::OUTGOING_TRANSFER]['startDate']??''" :endDate="$filterDates[CashExpense::OUTGOING_TRANSFER]['endDate']??''">
                        <x-export-cash-expense :route-redirect="route('view.cash.expense',['company'=>$company->id])" :route-action="route('cash.expense.outgoing.transfer.mark.as.paid',['company'=>$company->id])" :popup-title="__('Do You Want To Mark This Outcoming Transfer/s As Paid ?')"  :account-types="$accountTypes" :financialInstitutionBanks="$financialInstitutionBanks" :search-fields="$outgoingTransferTableSearchFields" :cash-expense-type="CashExpense::OUTGOING_TRANSFER" :has-search="1" :has-batch-collection="1" :banks="$banks??[]" :selectedBanks="$selectedBanks" href="{{route('create.cash.expense',['company'=>$company->id])}}" />
					
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="align-middle">{{ __('Select') }}</th>
								 	<th class="align-middle">{!! __('Category <br> Name') !!}</th>
                                    <th class="align-middle">{!! __('Expense <br> Name') !!}</th>
                                    {{-- <th class="bank-max-width">{{ __('Status') }}</th> --}}
                                    {{-- <th class="bank-max-width">{{ __('Supplier Name') }}</th> --}}
                                    <th>{{ __('Payment Date') }}</th>
                                    <th class="bank-max-width">{{ __('Payment Bank') }}</th>
                                    <th>{{ __('Transfer Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th class="bank-max-width">{{ __('Account Type') }}</th>
                                    <th>{{ __('Account Number') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach($outgoingTransfer as $money)

                                <tr>
								<td>
                                        <input style="max-height:25px;" id="cash-send-to-collection{{ $money->id }}" type="checkbox" name="second_to_collection[]" value="{{ $money->id }}" data-money-type="{{ CashExpense::OUTGOING_TRANSFER }}" class="form-control checkbox js-send-to-collection">
                                    </td>
                                    {{-- <td class="bank-max-width">{{ $money->getSupplierName() }}</td> --}}
									   <td class="text-nowrap">{{ $money->getExpenseCategoryName() }}</td>
                                    <td class="text-nowrap">{{ $money->getExpenseName() }}</td>
                                    <td class="text-nowrap">{{ $money->getPaymentDateFormatted() }}</td>
                                    <td class="bank-max-width">{{ $money->getOutgoingTransferDeliveryBankName() }}</td>
                                    <td>{{ $money->getPaidAmountFormatted() }}</td>
                                    <td data-currency="{{ $money->getCurrency() }}"> {{ $money->getCurrencyToPaymentCurrencyFormatted() }}</td>
                                    <td class="bank-max-width">{{ $money->getOutgoingTransferAccountTypeName() }}</td>
                                    <td>{{ $money->getOutgoingTransferAccountNumber() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$money])
										@include('reports._integrated_modal',['model'=>$money])
										@if(!$money->isOpenBalance())
										@if(auth()->user()->can('update cash expenses'))
										@include('reports._review_modal',['model'=>$money])
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.cash.expense',['company'=>$company->id,'cashExpense'=>$money->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
										@endif 
										@if(!$money->isOpenBalance())
                                            <a data-toggle="modal" data-target="#delete-transfer-id-{{ $money->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-transfer-id-{{ $money->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.cash.expense',['company'=>$company->id,'cashExpense'=>$money->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											@endif 
                                        </span>
                                    </td>
                                </tr>
                                @endforeach


                            </tbody>
                        </table>
						{{ $outgoingTransfer->appends(array_merge(request()->all(),['active' => CashExpense::OUTGOING_TRANSFER]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>

            <!--End:: Tab Content-->


            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ Request('active') == CashExpense::CASH_PAYMENT ? 'active':''  }}" id="{{ CashExpense::CASH_PAYMENT }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">

                    <x-table-title.with-two-dates :type="CashExpense::CASH_PAYMENT" :title="__('Cash Payment')" :startDate="$filterDates[CashExpense::CASH_PAYMENT]['startDate']??''" :endDate="$filterDates[CashExpense::CASH_PAYMENT]['endDate']??''">
                        <x-export-cash-expense :account-types="$accountTypes" :financialInstitutionBanks="$financialInstitutionBanks" :search-fields="$payableCashTableSearchFields" :cash-expense-type="CashExpense::CASH_PAYMENT" :has-search="1" :has-batch-collection="0" :banks="$banks??[]" :selectedBanks="$selectedBanks" href="{{route('create.cash.expense',['company'=>$company->id])}}" />

                    </x-table-title.with-two-dates>

                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    {{-- <th>{{ __('Type') }}</th> --}}
                                    <th class="align-middle">{{ __('Select') }}</th>
									
                                    {{-- <th class="bank-max-width">{{ __('Supplier Name') }}</th> --}}
									<th class="align-middle">{!! __('Category <br> Name') !!}</th>
                                    <th class="align-middle">{!! __('Expense <br> Name') !!}</th>
                                    <th>{{ __('Payment Date') }}</th>
                                    <th>{{ __('Branch') }}</th>
                                    <th>{{ __('Payment Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('Receipt Number') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cashPayments as $cashExpense)

                                <tr>
									
								<td>
                                        <input style="max-height:25px;" id="cash-send-to-collection{{ $cashExpense->id }}" type="checkbox" name="second_to_collection[]" value="{{ $cashExpense->id }}" data-money-type="{{ CashExpense::OUTGOING_TRANSFER }}" class="form-control checkbox js-send-to-collection">
                                    </td>
									       <td class="text-nowrap">{{ $cashExpense->getExpenseCategoryName() }}</td>
                                    <td class="text-nowrap">{{ $cashExpense->getExpenseName() }}</td>
                            
                                    {{-- <td class="bank-max-width">{{ $cashExpense->getSupplierName() }}</td> --}}
									
                                    <td class="text-nowrap">{{ $cashExpense->getPaymentDateFormatted() }}</td>
									
                                    <td>{{ $cashExpense->getCashPaymentBranchName() }}</td>
                                    <td>{{ $cashExpense->getPaidAmountFormatted() }}</td>
                                    <td data-currency="{{ $cashExpense->getCurrency() }}">{{ $cashExpense->getCurrencyToPaymentCurrencyFormatted() }}</td>
                                    <td>{{ $cashExpense->getCashPaymentReceiptNumber() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$cashExpense])
										@include('reports._integrated_modal',['model'=>$cashExpense])
										@if(!$cashExpense->isOpenBalance())
										@if(auth()->user()->can('update cash expenses'))
										@include('reports._review_modal',['model'=>$cashExpense])
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.cash.expense',['company'=>$company->id,'cashExpense'=>$cashExpense->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(auth()->user()->can('delete cash expenses'))
                                            <a data-toggle="modal" data-target="#delete-transfer-id-{{ $cashExpense->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-transfer-id-{{ $cashExpense->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.cash.expense',['company'=>$company->id,'cashExpense'=>$cashExpense->id]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
											@endif 
@endif 
                                        </span>
                                    </td>
                                </tr>
                                @endforeach





                            </tbody>
                        </table>
						{{ $cashPayments->appends(array_merge(request()->all(),['active' => CashExpense::CASH_PAYMENT]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>

            <!--End:: Tab Content-->







            <!--End:: Tab Content-->

        </div>
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
    $(document).on('click', '.js-can-trigger-cheque-under-collection-modal', function(e) {
        e.preventDefault();
        const moneyType = $(this).attr('data-money-type')
        const type = $(this).attr('data-type') // single or multi
        $('#single-or-multi' + moneyType).val(type);
        if (type == 'single') {
            $('#current-single-item' + moneyType).val($(this).attr('data-id'));
            $('#current-currency' + moneyType).val($(this).attr('data-currency'));
			$('input[name="actual_payment_date"]').val($(this).attr('data-due-date'));
        }else{
			$('input[name="actual_payment_date"]').val("{{ now()->format('m/d/Y') }}");
		}
    })
    $(document).on('submit', '.ajax-send-cheques-to-collection', function(e) {
        e.preventDefault();
        const url = $(this).attr('action');
        const moneyType = $(this).attr('data-money-type')
        const type = $('#single-or-multi' + moneyType).val();
        const singleId = parseInt($('#current-single-item' + moneyType).val());
        let checked = [];
        $('.js-send-to-collection[data-money-type="' + moneyType + '"]:checked').each(function(index, element) {
            checked.push(parseInt($(element).val()));
        });

        const checkedItems = type == 'multi' ? checked : [singleId];
        let form = document.getElementById('ajax-send-cheques-to-collection-id' + moneyType);
        let formData = new FormData(form);
        formData.append('cheques', checkedItems);
        // $('button').prop('disabled', true)
         $.ajax({
            cache: false
            , contentType: false
            , processData: false
            , url: url
            , data: formData
            , type: "post"
        }).then(function(res) {
			if(res.status === false){
				 Swal.fire({
                text: res.msg
                , icon: 'error'
                , timer: 2000
            }).then(function() {
              window.location.href = res.pageLink;
            });
			}
           else{
			 Swal.fire({
                text: 'Done'
                , icon: 'success'
                , timer: 2000
            }).then(function() {
              window.location.href = res.pageLink;
            });
		   }
        }).catch(res=>{
			title ="{{ __('Error !') }}";
			message = "{{ __('Something went Wrong') }}";
			if (res.responseJSON && res.responseJSON.errors) {
                            message = res.responseJSON.errors[Object.keys(res.responseJSON.errors)[0]][0]
            }
			 Swal.fire({
                            icon: 'error'
                            , title: title
                            , text: message

                        })
		})
    });

</script>
<script>
    $(document).on('click', '.js-close-modal', function() {
        $(this).closest('.modal').modal('hide');
    })
    $(document).on('click', '#js-drawee-bank', function(e) {
        e.preventDefault();
        $('#js-choose-bank-id').modal('show');
    })

    $(document).on('click', '#js-append-bank-name-if-not-exist', function() {
        const receivingBank = document.getElementById('js-drawee-bank').parentElement;
        const newBankId = $('#js-bank-names').val();
        const newBankName = $('#js-bank-names option:selected').attr('data-name');
        const isBankExist = $(receivingBank).find('select.js-drawl-bank').find('option[value="' + newBankId + '"]').length;
        if (!isBankExist) {
            const option = '<option selected value="' + newBankId + '">' + newBankName + '</option>'
            $('#js-drawee-bank').parent().find('select.js-drawl-bank').append(option);
        }
        $('#js-choose-bank-id').modal('hide');
    });

</script>
<script>
    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'due_date') {
            $('.data-type-span').html('[ {{ __("Due Date") }} ]')
            modal.find(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName == 'receiving_date') {
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
            modal.find('.data-type-span').html('[ {{ __("Payment Date") }} ]')
        } else if (searchFieldName == 'payment_date') {
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
            modal.find('.data-type-span').html('[ {{ __("Payment Date") }} ]')
        } else {
            $(modal).find('.search-field').prop('disabled', false);
        }
    })
    $(function() {

        $('.js-search-modal').trigger('change')

    })

</script>
@endsection
@push('js')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script> --}}
@endpush
