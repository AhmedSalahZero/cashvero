
@extends('layouts.dashboard')
@php
use App\Models\BuyOrSellCurrency ;
@endphp
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
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
{{ __('Sell Or Buy Currencies') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !Request('active') || Request('active') == BuyOrSellCurrency::BANK_TO_BANK ?'active':'' }}" data-toggle="tab" href="#{{BuyOrSellCurrency::BANK_TO_BANK  }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Bank To Bank') }}
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ Request('active') == BuyOrSellCurrency::SAFE_TO_BANK ?'active':'' }}" data-toggle="tab" href="#{{ BuyOrSellCurrency::SAFE_TO_BANK }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Safe To Bank') }}
                    </a>
                </li>


                <li class="nav-item">
                    <a class="nav-link {{ Request('active') == BuyOrSellCurrency::BANK_TO_SAFE ?'active':'' }}" data-toggle="tab" href="#{{ BuyOrSellCurrency::BANK_TO_SAFE }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Bank To Safe') }}
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ Request('active') == BuyOrSellCurrency::SAFE_TO_SAFE ?'active':'' }}" data-toggle="tab" href="#{{ BuyOrSellCurrency::SAFE_TO_SAFE }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Safe To Safe') }}
                    </a>
                </li>

            </ul>
			@if(hasAuthFor('create buy or sell currency'))
            <div class="flex-tabs">
                <a href="{{ route('buy-or-sell-currencies.create',['company'=>$company->id]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Sell Or Buy Currencies') }}
                </a>
            </div>
			@endif 
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">
            @php
            $currentType = BuyOrSellCurrency::BANK_TO_BANK ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__(BuyOrSellCurrency::getAllTypes()[$currentType])" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-buy-or-sell-currency :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('buy-or-sell-currencies.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <div class="table-responsive">
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Transaction Date') }}</th>
                                    <th>{{ __('Amount To Sell') }}</th>
                                    <th>{{ __('Currency To Sell') }}</th>
                                    <th>{{ __('Exchange Rate') }}</th>
                                    <th>{{ __('Amount To Buy') }}</th>
                                    <th>{{ __('Currency To Buy') }}</th>
                                    <th>{{ __('From Bank') }}</th>
                                    <th>{{ __('From Account Type') }}</th>
                                    <th>{{ __('From Account Number') }}</th>
                                    <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th>
									@if(hasAuthFor('update buy or sell currency') || hasAuthFor('delete buy or sell currency') )
                                    <th>{{ __('Control') }}</th>
									@endif 
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getTransactionDateFormatted() }}</td>

                                    <td>{{ $model->getAmountToSellFormatted() }}</td>
                                    <td>{{ $model->getCurrencyToSellFormatted() }}</td>
									<td>{!! $model->getExchangeRateWithItsReverse()  !!}
                                    <td>{{ $model->getAmountToBuyFormatted() }}</td>

									</td>
                                    <td>{{ $model->getCurrencyToBuyFormatted() }}</td>
                                    <td>{{ $model->getFromBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getFromAccountNumber() }}</td>
                                    <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td>

									@if(hasAuthFor('update buy or sell currency') || hasAuthFor('delete buy or sell currency') )
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$model])
										@include('reports._integrated_modal',['model'=>$model])
										@if(hasAuthFor('update buy or sell currency'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('buy-or-sell-currencies.edit',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
											@endif 
											@if(hasAuthFor('delete buy or sell currency'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('buy-or-sell-currencies.destroy',['company'=>$company->id,'buy_or_sell_currency'=>$model->id ]) }}" method="post">
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
									@endif 
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
						{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>

















            @php
            $currentType = BuyOrSellCurrency::BANK_TO_SAFE ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__(BuyOrSellCurrency::getAllTypes()[$currentType])" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-buy-or-sell-currency :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('buy-or-sell-currencies.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <div class="table-responsive">
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Transaction Date') }}</th>
                                    <th>{{ __('Amount To Sell') }}</th>
                                    <th>{{ __('Currency To Sell') }}</th>
									<th>{{ __('Exchange Rate') }}</th>
                                    <th>{{ __('Amount To Buy') }}</th>
                                    <th>{{ __('Currency To Buy') }}</th>
                                    <th>{{ __('From Bank') }}</th>
                                    <th>{{ __('From Account Type') }}</th>
                                    <th>{{ __('From Account Number') }}</th>
                                    {{-- <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th> --}}
                                    <th>{{ __('To Branch') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getTransactionDateFormatted() }}</td>

                                    <td>{{ $model->getAmountToSellFormatted() }}</td>
                                    <td>{{ $model->getCurrencyToSellFormatted() }}</td>
									<td>{!! $model->getExchangeRateWithItsReverse()  !!}
                                    <td>{{ $model->getAmountToBuyFormatted() }}</td>

									</td>
                                    <td>{{ $model->getCurrencyToBuyFormatted() }}</td>
                                    <td>{{ $model->getFromBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getFromAccountNumber() }}</td>
                                    {{-- <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td> --}}
                                    <td class="text-uppercase">{{ $model->getToBranchName() }}</td>


                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$model])
										@include('reports._integrated_modal',['model'=>$model])		
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('buy-or-sell-currencies.edit',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('buy-or-sell-currencies.destroy',['company'=>$company->id,'buy_or_sell_currency'=>$model->id ]) }}" method="post">
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
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
						{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>












            @php
            $currentType = BuyOrSellCurrency::SAFE_TO_BANK ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__(BuyOrSellCurrency::getAllTypes()[$currentType])" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-buy-or-sell-currency :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('buy-or-sell-currencies.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <div class="table-responsive">
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Transaction Date') }}</th>
                                    <th>{{ __('Amount To Sell') }}</th>
                                    <th>{{ __('Currency To Sell') }}</th>
									<th>{{ __('Exchange Rate') }}</th>
                                    <th>{{ __('Amount To Buy') }}</th>
                                    <th>{{ __('Currency To Buy') }}</th>
                                    <th>{{ __('From Branch') }}</th>
                                    <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th>
                                    {{-- <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th> --}}
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getTransactionDateFormatted() }}</td>

                                    <td>{{ $model->getAmountToSellFormatted() }}</td>
                                    <td>{{ $model->getCurrencyToSellFormatted() }}</td>
									<td>{!! $model->getExchangeRateWithItsReverse()  !!}
                                    <td>{{ $model->getAmountToBuyFormatted() }}</td>

									</td>
                                    <td>{{ $model->getCurrencyToBuyFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromBranchName() }}</td>
                                    <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td>
                                    {{-- <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td> --}}


                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$model])
										@include('reports._integrated_modal',['model'=>$model])
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('buy-or-sell-currencies.edit',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('buy-or-sell-currencies.destroy',['company'=>$company->id,'buy_or_sell_currency'=>$model->id ]) }}" method="post">
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
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
						{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}	 
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
























            @php
            $currentType = BuyOrSellCurrency::SAFE_TO_SAFE ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__(BuyOrSellCurrency::getAllTypes()[$currentType])" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-buy-or-sell-currency :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('buy-or-sell-currencies.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <div class="table-responsive">
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Transaction Date') }}</th>
                                    <th>{{ __('Amount To Sell') }}</th>
                                    <th>{{ __('Currency To Sell') }}</th>
									<th>{{ __('Exchange Rate') }}</th>
                                    <th>{{ __('Amount To Buy') }}</th>
                                    <th>{{ __('Currency To Buy') }}</th>
                                    <th>{{ __('From Branch') }}</th>
                                    <th>{{ __('To Branch') }}</th>
                                    {{-- <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th> --}}
                                    {{-- <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th> --}}
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getTransactionDateFormatted() }}</td>

                                    <td>{{ $model->getAmountToSellFormatted() }}</td>
                                    <td>{{ $model->getCurrencyToSellFormatted() }}</td>
									<td>{!! $model->getExchangeRateWithItsReverse()  !!}
                                    <td>{{ $model->getAmountToBuyFormatted() }}</td>

									</td>
                                    <td>{{ $model->getCurrencyToBuyFormatted() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromBranchName(true) }}</td>
                                    <td class="text-uppercase">{{ $model->getToBranchName() }}</td>
                                    {{-- <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td> --}}
                                    {{-- <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td> --}}


                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
										@include('reports._user_comment_modal',['model'=>$model])
										@include('reports._integrated_modal',['model'=>$model])
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('buy-or-sell-currencies.edit',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ route('buy-or-sell-currencies.destroy',['company'=>$company->id,'buy_or_sell_currency'=>$model->id ]) }}" method="post">
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
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
						{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}	 
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
        if (searchFieldName === 'transfer_date') {
            modal.find('.data-type-span').html('[ {{ __("Transfer Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'transaction_date') {
            modal.find('.data-type-span').html('[ {{ __("Transaction Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
            modal.find('.data-type-span').html('[ {{ __("Contract Start Date") }} ]')
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
