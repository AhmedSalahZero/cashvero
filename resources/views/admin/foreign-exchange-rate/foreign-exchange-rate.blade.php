@extends('layouts.dashboard')
@section('css')
@php

$currentActiveTab = isset($currentActiveTab) ? $currentActiveTab : null ;


@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
    .money-flow-dark .custom-w-25 {
        width: 23% !important;
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
{{ __('Foreign Exchange Rate')  }}
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
                @foreach($existingCurrencies as $currentCurrencyName)
                <li class="nav-item">
                    <a data-currency="{{$currentCurrencyName}}" class="nav-link currency-change-js {{ !Request('active',$currentActiveTab) && $index==0 || (!in_array($mainFunctionalCurrency,$existingCurrencies) && $currentCurrencyName == array_key_first($existingCurrencies) && !$currentActiveTab ) || Request('active',$currentActiveTab) == $currentCurrencyName ?'active':'' }}" data-toggle="tab" href="#{{ $currentCurrencyName }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{$currentCurrencyName .' '. __('Table') }}
                    </a>
                </li>
                @php
                $index = $index+1;
                @endphp
                @endforeach

            </ul>


        </div>
    </div>
    <div class="kt-portlet__body">
	
	  	@php
			$index = 0 ;
		@endphp
        <div class="tab-content  kt-margin-t-20">
			@foreach($existingCurrencies as $existingCurrency)
			
            @php
            $currentTab = $existingCurrency ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ (!Request('active',$currentActiveTab) && $currentTab == $mainFunctionalCurrency || !in_array($mainFunctionalCurrency,$existingCurrencies) && $currentTab == array_key_first($existingCurrencies) && !$currentActiveTab   )  || Request('active',$currentActiveTab) == $existingCurrency ?'active':'' }}" id="{{ $currentTab }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentTab" :title="$existingCurrency . ' ' .__('Table') " :startDate="$filterDates[$currentTab]['startDate']" :endDate="$filterDates[$currentTab]['endDate']">
                        {{-- <x-export-letter-of-credit-issuance :search-fields="$searchFields[$currentTab]" :type="$currentTab" href="#" /> --}}
                    </x-table-title.with-two-dates>

                    <div class="kt-portlet__body">
					{{-- @if(hasAuthFor('create foreign exchange rate') && !$company->hasOdooIntegrationCredentials()) --}}
					<div class="row">
                    <div class="col-md-12">
                       @include('admin.foreign-exchange-rate._form')
                    </div>
                </div>
				{{-- @endif  --}}

                        <!--begin: Datatable -->
                          <table class="table kt_table_with_no_pagination_no_collapse table-for-currency  table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class-for-currency dataTable no-footer">
                                        <thead>

                                            <tr class="header-tr ">

                                                <th class="view-table-th max-w-serial  header-th  align-middle text-center">
                                                    {{ __('#') }}
                                                </th>

                                                <th class="view-table-th   max-w-16  align-middle text-center">
                                                    {{ __('Date') }}
                                                </th>

                                                <th class="view-table-th max-w-16    header-th  align-middle text-center">
                                                    {{ __('From Currency') }}
                                                </th>

                                                <th class="view-table-th max-w-16    header-th  align-middle text-center">
                                                    {{ __('To Currency') }}
                                                </th>

                                                <th class="view-table-th  max-w-16  header-th  align-middle text-center">
                                                    {{ __('Exchange Rate') }}
                                                </th>
                                                <th class="view-table-th  max-w-16  header-th  align-middle text-center">
                                                    {{ __('Reciprocal Exchange Rate') }}
                                                </th>
@if(hasAuthFor('update foreign exchange rate') || hasAuthFor('delete foreign exchange rate'))
                                                <th class="view-table-th  max-w-action  header-th  align-middle text-center">
                                                    {{ __('Actions') }}
                                                </th>
												@endif 







                                            </tr>

                                        </thead>
                                        <tbody>
                                            @php
                                            $previousDate = null ;
                                            @endphp
                                            @foreach($models[$existingCurrency] as $index => $foreignExchangeRate)
                                            <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                                                <td class="sub-text-bg max-w-serial text-center   ">{{ ++$index }}</td>
                                                <td class="sub-text-bg max-w-16  text-center   ">{{ $currentDueDate = $foreignExchangeRate->getDateFormatted() }} </td>
                                                <td class="sub-text-bg  max-w-16 text-center   ">{{ $foreignExchangeRate->getFromCurrency() }}</td>
                                                @php
                                                $previousDate = $foreignExchangeRate->getDate();
                                                @endphp
                                                <td class="sub-text-bg max-w-16  text-center  ">{{ $foreignExchangeRate->getToCurrency() }}</td>
                                                <td class="sub-text-bg max-w-16  text-center  ">{{ number_format($foreignExchangeRate->getExchangeRate(),4) }}</td>
                                                <td class="sub-text-bg max-w-16  text-center  ">{{ number_format(1/$foreignExchangeRate->getExchangeRate(),4) }}</td>
												{{-- @if(hasAuthFor('update foreign exchange rate') || hasAuthFor('delete foreign exchange rate')) --}}
                                                <td class="sub-text-bg   text-center max-w-action   ">
                                                    @if($loop->first)
													{{-- @if(hasAuthFor('update foreign exchange rate')) --}}
                                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{route('edit.foreign.exchange.rate',[$company,$foreignExchangeRate->id])}}"><i class="fa fa-pen-alt"></i></a>
													{{-- @endif  --}}
													{{-- @if(hasAuthFor('delete foreign exchange rate')) --}}
                                                    <a class="btn btn-secondary btn-outline-hover-danger btn-icon  " href="#" data-toggle="modal" data-target="#modal-delete-{{ $foreignExchangeRate['id']}}" title="Delete"><i class="fa fa-trash-alt"></i>
                                                    </a>
													<div id="modal-delete-{{ $foreignExchangeRate['id'] }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h4 class="modal-title">{{ __('Delete Foreign Exchange ' .$foreignExchangeRate->getDateFormatted()) }}</h4>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <h3>{{ __('Are You Sure To Delete This Item ? ') }}</h3>
                                                                </div>
                                                                <form action="{{ route('delete.foreign.exchange.rate',[$company,$foreignExchangeRate->id]) }}" method="post" id="delete_form">
                                                                    {{ csrf_field() }}
                                                                    {{ method_field('DELETE') }}
                                                                    <div class="modal-footer">
                                                                        <button class="btn btn-danger">
                                                                            {{ __('Delete') }}
                                                                        </button>
                                                                        <button class="btn btn-secondary" data-dismiss="modal" aria-hidden="true">
                                                                            {{ __('Close') }}
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
													{{-- @endif  --}}
                                                    @endif
	
                                                    

                                                </td>
												{{-- @endif  --}}
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
			@endforeach
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
            modal.find('.data-type-span').html('[{{ __("Purchase Order Date") }}]')
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


    $("button[data-dismiss=modal2]").click(function() {
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
    $(document).on('change', 'select.update-net-balance-inputs', function() {
        const selectedOption = $(this).find('option:selected')
        const currency = $(selectedOption).attr('data-currency')
        const netBalance = $(selectedOption).attr('data-invoice-net-balance')
        const exchangeRate = $(selectedOption).attr('data-exchange-rate')
        const netBalanceInMainCurrency = $(selectedOption).attr('data-invoice-net-balance-in-main-currency');
        const parent = $(this).closest('.modal-body')
        $(parent).find('.net-balance').val(number_format(netBalance) + ' ' + currency)
        $(parent).find('.exchange-rate').val(number_format(exchangeRate, 2))
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

@endsection
@push('js')

@endpush
