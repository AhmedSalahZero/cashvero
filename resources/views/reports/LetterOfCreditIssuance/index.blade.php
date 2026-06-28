@extends('layouts.dashboard')
@section('css')
@php
use App\Enums\LcTypes;
use App\Models\LetterOfCreditIssuance;
$slightLcType = LcTypes::SIGHT_LC ;
$deferredType = LcTypes::DEFERRED ;
$cashAgainstDocumentType = LcTypes::CASH_AGAINST_DOCUMENT ;
$allLcs = LcTypes::getAll() ;
$currentActiveTab = isset($currentActiveTab) ? $currentActiveTab : null ;


@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
.money-flow-dark .custom-w-25{
	width:23% !important;
}
    .money-flow-dark input[type="checkbox"] {
        cursor: pointer;
    }

    .money-flow-dark .bank-max-width {
        max-width: 300px !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Letter Of Credit Issuance ')  }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lc nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                @php
                $index = 0 ;
                @endphp
                @foreach($allLcs as $type => $name)
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active',$currentActiveTab) && $index==0 || Request('active',$currentActiveTab) == $type ?'active':'' }}" data-toggle="tab" href="#{{ $type }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{$name .' '. __('Table') }}
                    </a>
                </li>
                @php
                $index = $index+1;
                @endphp
                @endforeach

            </ul>
		@if(hasAuthFor('create letter of credit issuance'))
            <div class="flex-tabs">
				<a href="{{ route('create.letter.of.credit.issuance',['company'=>$company->id,'source'=>LetterOfCreditIssuance::LC_FACILITY  ]) }}" class="btn btn-sm active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New From LC Facility') }}
				</a>
				{{-- <a href="{{ route('create.letter.of.credit.issuance',['company'=>$company->id,'source'=>LetterOfCreditIssuance::AGAINST_CD  ]) }}" class="btn btn-sm active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New LC Agnist CDs') }}
				</a>
				<a href="{{ route('create.letter.of.credit.issuance',['company'=>$company->id,'source'=>LetterOfCreditIssuance::AGAINST_TD  ]) }}" class="btn btn-sm active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New LC Agnist TDs') }}
				</a> --}}
				<a href="{{ route('create.letter.of.credit.issuance',['company'=>$company->id,'source'=>LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER]) }}" class="btn btn-sm active-style btn-icon-sm align-self-center">
					<i class="fas fa-plus"></i>
					{{ __('New LC 100% Cash Cover') }}
				</a>
			</div >
			@endif 

        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">
            @php
            $currentTab = $slightLcType ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active',$currentActiveTab) && $currentTab == $slightLcType  || Request('active',$currentActiveTab) == $currentTab ?'active':'' }}" id="{{ $currentTab }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentTab" :title="$allLcs[$currentTab] . ' ' .__('Table') " :startDate="$filterDates[$currentTab]['startDate']" :endDate="$filterDates[$currentTab]['endDate']">
                        <x-export-letter-of-credit-issuance :search-fields="$searchFields[$currentTab]" :type="$currentTab" href="{{route('create.letter.of.credit.issuance',['company'=>$company->id,'active'=>$currentTab,'source'=>LetterOfCreditIssuance::LC_FACILITY])}}" />
                    </x-table-title.with-two-dates>

                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="text-center align-middle">{{ __('#') }}</th>
                                    <th class="text-center align-middle"> {!! __('Transaction <br> Name') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Source') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Status') !!} </th>
									
                                    <th class="text-center align-middle bank-max-width" >{{ __('Bank Name') }}</th>
                                    <th class="text-center align-middle">{{ __('Supplier Name') }}</th>
                                    <th class="text-center align-middle"> {!! __('Transaction <br> Reference') !!} </th>
                                    <th class="text-center align-middle">{{ __('LC Amount') }}</th>
                                    {{-- <th class="text-center align-middle"> {!! __('Transaction <br> No Date') !!} </th> --}}
                                    <th class="text-center align-middle">{{ __('Issuance Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Due Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentTab] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td>{{ $model->getTransactionName() }}</td>
                                    <td class="text-transform">{{ $model->getSourceFormatted() }}</td>
                                    <td class="text-transform">{{ $model->getStatusFormatted() }}</td>
                                    <td class="bank-max-width">{{ $model->getFinancialInstitutionBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getSupplierName() }}</td>
                                    <td class="text-transform">{{ $model->getTransactionReference() }}</td>
                                    <td class="text-transform">{{ $model->getLcAmountFormatted() . ' ' . $model->getLcCurrency() }}</td>
                                    {{-- <td class="text-transform text-nowrap">{{ $model->getTransactionDateFormatted() }}</td> --}}
                                    <td class="text-transform text-nowrap">{{ $model->getIssuanceDateFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getDueDateFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										 @include('reports._user_comment_modal',['model'=>$model])
                                          @include('reports.LetterOfCreditIssuance.actions')
										@if(!$model->isPaid())
											@if(hasAuthFor('update letter of credit issuance'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete letter of credit issuance'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
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

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>











            @php
            $currentTab = $deferredType ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active',$currentActiveTab) && $currentTab == $slightLcType  || Request('active',$currentActiveTab) == $currentTab ?'active':'' }}" id="{{ $currentTab }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentTab" :title="$allLcs[$currentTab] . ' ' .__('Table') " :startDate="$filterDates[$currentTab]['startDate']" :endDate="$filterDates[$currentTab]['endDate']">
                        <x-export-letter-of-credit-issuance :search-fields="$searchFields[$currentTab]" :type="$currentTab" href="{{route('create.letter.of.credit.issuance',['company'=>$company->id,'active'=>$currentTab,'source'=>LetterOfCreditIssuance::LC_FACILITY])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="text-center align-middle">{{ __('#') }}</th>
                                    <th class="text-center align-middle"> {!! __('Transaction <br> Name') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Source') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Status') !!} </th>
                                    <th class="text-center align-middle bank-max-width">{{ __('Bank Name') }}</th>
                                    <th class="text-center align-middle">{{ __('Supplier Name') }}</th>
                                    <th class="text-center align-middle">{{ __('LC Amount') }}</th>
							
                                    <th class="text-center align-middle"> {!! __('Purchase <br> No Date') !!} </th>
                                    <th class="text-center align-middle">{{ __('Issuance Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Due Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentTab] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td>{{ $model->getTransactionName() }}</td>
                                    <td>{{ $model->getSourceFormatted() }}</td>
                                    <td>{{ $model->getStatusFormatted() }}</td>
                                    <td class="bank-max-width">{{ $model->getFinancialInstitutionBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getSupplierName() }}</td>
                                    <td class="text-transform">{{ $model->getLcAmountFormatted(). ' ' . $model->getLcCurrency() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getPurchaseOrderDateFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getIssuanceDateFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getDueDateFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										 @include('reports._user_comment_modal',['model'=>$model])
                                          @include('reports.LetterOfCreditIssuance.actions')
											@if(!$model->isPaid())
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
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

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>








            @php
            $currentTab = $cashAgainstDocumentType ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active',$currentActiveTab) && $currentTab == $cashAgainstDocumentType  || Request('active',$currentActiveTab) == $currentTab ?'active':'' }}" id="{{ $currentTab }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentTab" :title="$allLcs[$currentTab] . ' ' .__('Table') " :startDate="$filterDates[$currentTab]['startDate']" :endDate="$filterDates[$currentTab]['endDate']">
                        <x-export-letter-of-credit-issuance :search-fields="$searchFields[$currentTab]" :type="$currentTab" href="{{route('create.letter.of.credit.issuance',['company'=>$company->id,'active'=>$currentTab,'source'=>LetterOfCreditIssuance::LC_FACILITY])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th class="text-center align-middle">{{ __('#') }}</th>
                                    <th class="text-center align-middle"> {!! __('Transaction <br> Name') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Source') !!} </th>
                                    <th class="text-center align-middle"> {!! __('Status') !!} </th>
                                    <th class="text-center align-middle bank-max-width">{{ __('Bank Name') }}</th>
                                    <th class="text-center align-middle">{{ __('Supplier Name') }}</th>
                                    <th class="text-center align-middle">{{ __('LC Amount') }}</th>
                                    <th class="text-center align-middle">{{ __('LC Current Amount') }}</th>
                                    <th class="text-center align-middle"> {!! __('Purchase <br> No Date') !!} </th>
                                    <th class="text-center align-middle">{{ __('Issuance Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Due Date') }}</th>
                                    <th class="text-center align-middle">{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentTab] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
                                    <td>{{ $model->getTransactionName() }}</td>
									<td>{{ $model->getSourceFormatted() }}</td>
									<td>{{ $model->getStatusFormatted() }}</td>
                                    <td class="bank-max-width">{{ $model->getFinancialInstitutionBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getSupplierName() }}</td>
                                    <td class="text-transform">{{ $model->getLcAmountFormatted(). ' ' . $model->getLcCurrency() }}</td>
                                    <td class="text-transform">{{ $model->getLcCurrentAmountFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getPurchaseOrderDateFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getIssuanceDateFormatted() }}</td>
                                    <td class="text-transform text-nowrap">{{ $model->getDueDateFormatted() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
										 @include('reports._user_comment_modal',['model'=>$model])
                                        	  @include('reports.LetterOfCreditIssuance.actions')
											  
											{{-- @if(!$model->advancedPaymentHistories->count() && !$model->isPaid())   --}}
											@if(!$model->isPaid())
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}">
											<i class="fa fa-pen-alt"></i>
											
											</a>
											{{-- @endif --}}
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form  onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('delete.letter.of.credit.issuance',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource()]) }}" method="post">
                                                            @csrf
                                                            @method('delete')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
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

                        <!--end: Datatable -->
                    </div>
                </div>
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
<script>

</script>
<script>


</script>



{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/validation/form-widgets.js') }}" type="text/javascript">
</script> --}}

{{-- <script>
    $(function() {
        $('#firstColumnId').trigger('change');
    })

</script> --}}

<script>
    $(document).on('click', '.js-close-modal', function() {
        $(this).closest('.modal').modal('hide');
    })

</script>
<script>
    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'purchase_order_date') {
            modal.find('.data-type-span').html('[{{ __("Purchase No Date") }}]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'issuance_date') {
            modal.find('.data-type-span').html('[ {{ __("Issuance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
            modal.find('.data-type-span').html('[ {{ __("Issuance Date") }} ]')
            $(modal).find('.search-field').prop('disabled', false);
        }
    })
    $(function() {
        $('.js-search-modal').trigger('change')
    })


$("button[data-dismiss=modal2]").click(function(){
    $(this).closest('.modal').modal('hide');
});

</script>
<script>
    $(document).on('change', '.recalculate-amount-in-main-currency', function() {
        const parent = $(this).closest('.modal-body');
        const amount = parseFloat(number_unformat($(parent).find('.amount-js').val()))
        const exchangeRate = parseFloat($(parent).find('.exchange-rate-js').val())
        const amountInMainCurrency = parseFloat(amount * exchangeRate);
        $(parent).find('.amount-in-main-currency-js-hidden').val(amountInMainCurrency)
        $(parent).find('.amount-in-main-currency-js').val(number_format(amountInMainCurrency))
    })
	$(document).on('change','select.update-net-balance-inputs',function(){
		const selectedOption = $(this).find('option:selected')
		const currency = $(selectedOption).attr('data-currency')
		const netBalance = $(selectedOption).attr('data-invoice-net-balance')
		const exchangeRate = $(selectedOption).attr('data-exchange-rate')
		const netBalanceInMainCurrency = $(selectedOption).attr('data-invoice-net-balance-in-main-currency');
		const parent = $(this).closest('.modal-body')
		$(parent).find('.net-balance').val(number_format(netBalance) + ' ' + currency)
		$(parent).find('.exchange-rate').val(number_format(exchangeRate,2))
		$(parent).find('.net-balance-in-main-currency').val(number_format(netBalanceInMainCurrency))
	})
	$('select.update-net-balance-inputs').trigger('change')
</script>

<script>
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })
    $('.m_repeater_9').repeater({
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
                parent.find('select.contracts-js').selectpicker("refresh")
            }
        })
    })
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency').toUpperCase()
        $(parent).find('.contract-code').val(code)
        $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)

    })
    $(function() {
        $('select.suppliers-or-customers-js').trigger('change')
    })

</script>
<script src="/custom/money-receive.js"></script>
<script>
$(document).on('change','select.update-remaining-class',function(){
	const parent = $(this).closest('.closest-parent');
	const mainFunctionalCurrency = "{{ $company->getMainFunctionalCurrency() }}"
	const paymentCurrency = parent.find('select[name="payment_currency"]').val();
	const lcAmountInMainCurrency = parent.find('.lc-amount-in-main-currency').attr('data-value');
	const lcAmount = parent.find('.lc-amount').attr('data-value');
	const cashCoverRate = parseFloat(parent.find('.cash-cover-rate').val() / 100) ;
	let amount = 0;
	if(paymentCurrency == mainFunctionalCurrency){
		amount = lcAmountInMainCurrency;
	}else{
		amount = lcAmount;
	}
	const remaining = amount * (1-cashCoverRate);
	$(parent).find('.lc-remaining-amount-class').val(number_format(remaining));
	
})
$('select.update-remaining-class').trigger('change')
</script>
@endsection
@push('js')
{{-- <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script> --}}
@endpush
