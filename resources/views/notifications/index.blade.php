@extends('layouts.dashboard')
@php
use App\Models\Notification ;
@endphp
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

<style>
    input[type="checkbox"] {
        cursor: pointer;
    }

    th {
        background-color: #0742A6;
        color: white;
    }

    .bank-max-width {
        max-width: 200px !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style) {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Buy Or Sell Currencies') }}
@endsection
@section('content')

<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
				@foreach($notificationTypes as $typeId => $typeTitle)
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ 
						# !Request('active') ||
						 $activeType == $typeId  ?'active':'' }}" data-toggle="tab" href="#{{$typeId  }}" role="tab">
					
                        <i class="fa fa-money-check-alt"></i> {{ $typeTitle }}
                    </a>
                </li>
				@endforeach

            

            </ul>

            <div class="flex-tabs">
                {{-- <a href="{{ route('buy-or-sell-currencies.create',['company'=>$company->id,BuyOrSellCurrency::BANK_TO_BANK]) }}" class="btn active-style btn-icon-sm align-self-center">
                <i class="fas fa-plus"></i>
                {{ __('Bank To Bank') }}
                </a>


                <a href="{{ route('buy-or-sell-currencies.create',['company'=>$company->id,BuyOrSellCurrency::SAFE_TO_BANK]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Safe To Bank') }}
                </a>

                <a href="{{ route('buy-or-sell-currencies.create',['company'=>$company->id,BuyOrSellCurrency::BANK_TO_SAFE]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Bank To Safe') }}
                </a>
                --}}
                {{-- <a href="{{ route('buy-or-sell-currencies.create',['company'=>$company->id]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Buy Or Sell Currencies') }}
                </a> --}}
            </div>

            {{-- <a href="" class="btn  active-style btn-icon-sm  align-self-center ">
				<i class="fas fa-plus"></i>
				<span>{{ __('New Record') }}</span>
            </a> --}}
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">










			@foreach($notificationTypes as $typeId => $typeTitle)
            @php
            $currentType = $typeId ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ $activeType  == $currentType  ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    {{-- <x-table-title.with-two-dates :type="$currentType" :title="__(BuyOrSellCurrency::getAllTypes()[$currentType])" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-buy-or-sell-currency :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('buy-or-sell-currencies.create',['company'=>$company->id])}}" />
                    </x-table-title.with-two-dates> --}}
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Message') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    {{-- <th>{{ __('Control') }}</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>
									<td> {{ $model->data['message_'.app()->getLocale()] }} </td>
                                    <td class="text-nowrap">{{ $model->created_at->diffForHumans() }}</td>


                                    {{-- <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('buy-or-sell-currencies.edit',['company'=>$company->id,'buy_or_sell_currency'=>$model->id]) }}"><i class="fa fa-pen-alt"></i></a>
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('buy-or-sell-currencies.destroy',['company'=>$company->id,'buy_or_sell_currency'=>$model->id ]) }}" method="post">
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
                                    </td> --}}
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
			@endforeach 



















































            <!--End:: Tab Content-->



            <!--End:: Tab Content-->
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
        } else if (searchFieldName === 'contract_end_date') {
            modal.find('.data-type-span').html('[ {{ __("Contract End Date") }} ]')
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
