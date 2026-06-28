@extends('layouts.dashboard')
@php
use App\Models\InternalMoneyTransfer ;
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
{{ __('Internal Money Transfer') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ !Request('active') || Request('active') == InternalMoneyTransfer::BANK_TO_BANK ?'active':'' }}" data-toggle="tab" href="#{{InternalMoneyTransfer::BANK_TO_BANK  }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Bank To Bank Transfer Table') }}
                    </a>
                </li>

                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == InternalMoneyTransfer::SAFE_TO_BANK ?'active':'' }}" data-toggle="tab" href="#{{ InternalMoneyTransfer::SAFE_TO_BANK }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Safe To Bank Deposit Table') }}
                    </a>
                </li>


                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == InternalMoneyTransfer::BANK_TO_SAFE ?'active':'' }}" data-toggle="tab" href="#{{ InternalMoneyTransfer::BANK_TO_SAFE }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Bank To Safe Withdrawal Table') }}
                    </a>
                </li>

                <li class="nav-item">
                    <a onclick="return false" class="nav-link {{ Request('active') == InternalMoneyTransfer::SAFE_TO_SAFE ?'active':'' }}" data-toggle="tab" href="#{{ InternalMoneyTransfer::SAFE_TO_SAFE }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Safe To Safe Withdrawal Table') }}
                    </a>
                </li>




            </ul>
            @if(auth()->user()->can('create internal money transfer'))
            <div class="flex-tabs">
                <a href="{{ route('internal-money-transfers.create',['company'=>$company->id,InternalMoneyTransfer::BANK_TO_BANK]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Bank To Bank') }}
                </a>


                <a href="{{ route('internal-money-transfers.create',['company'=>$company->id,InternalMoneyTransfer::SAFE_TO_BANK]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Safe To Bank') }}
                </a>

                <a href="{{ route('internal-money-transfers.create',['company'=>$company->id,InternalMoneyTransfer::BANK_TO_SAFE]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Bank To Safe') }}
                </a>

                <a href="{{ route('internal-money-transfers.create',['company'=>$company->id,InternalMoneyTransfer::SAFE_TO_SAFE]) }}" class="btn  active-style btn-icon-sm align-self-center">
                    <i class="fas fa-plus"></i>
                    {{ __('Safe To Safe') }}
                </a>

            </div>
            @endif

            {{-- <a href="" class="btn  active-style btn-icon-sm  align-self-center ">
				<i class="fas fa-plus"></i>
				<span>{{ __('New Record') }}</span>
            </a> --}}
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="tab-content  kt-margin-t-20">
            @php
            $currentType = InternalMoneyTransfer::BANK_TO_BANK ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{ !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Bank To Bank')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-internal-money-transfer :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('internal-money-transfers.create',['company'=>$company->id,'type'=>$currentType])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <div class="table-responsive">
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Transfer Date') }}</th>
                                    <th>{{ __('Transfer Days') }}</th>
                                    <th>{{ __('Receiving Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('From Bank') }}</th>
                                    <th>{{ __('From Account Type') }}</th>
                                    <th>{{ __('From Account Number') }}</th>
                                    <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th>
                                    @if(auth()->user()->can('update internal money transfer') || auth()->user()->can('delete internal money transfer'))
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

                                    <td class="text-nowrap">{{ $model->getTransferDateFormatted() }}</td>
                                    <td class="text-nowrap">{{ $model->getTransferDays() }}</td>
                                    <td class="text-nowrap">{{ $model->getReceivingDateFormatted() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getFromBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getFromAccountNumber() }}</td>
                                    <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td>

                                    @if(auth()->user()->can('update internal money transfer') || auth()->user()->can('delete internal money transfer'))
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">


                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @include('reports._user_comment_modal',['model'=>$model])
											@include('reports._integrated_modal',['model'=>$model])
											
                                            @if(auth()->user()->can('update internal money transfer'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('internal-money-transfers.edit',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(auth()->user()->can('delete internal money transfer'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('internal-money-transfers.destroy',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType ]) }}" method="post">
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
            $currentType = InternalMoneyTransfer::SAFE_TO_BANK ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Safe To Bank')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-internal-money-transfer :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('internal-money-transfers.create',['company'=>$company->id,'type'=>$currentType])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Deposit Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('From Branch') }}</th>
                                    <th>{{ __('To Bank') }}</th>
                                    <th>{{ __('To Account Type') }}</th>
                                    <th>{{ __('To Account Number') }}</th>
                                    <th>{{ __('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($models[$currentType] as $index=>$model)
                                <tr>
                                    <td>
                                        {{ $index+1 }}
                                    </td>

                                    <td class="text-nowrap">{{ $model->getTransferDateFormatted() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getFromBranchName() }}</td>
                                    <td>{{ $model->getToBankName() }}</td>
                                    <td class="text-uppercase">{{ $model->getToAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getToAccountNumber() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell" data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @include('reports._user_comment_modal',['model'=>$model])
											@include('reports._integrated_modal',['model'=>$model])
                                            @if(auth()->user()->can('update internal money transfer'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('internal-money-transfers.edit',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(auth()->user()->can('delete internal money transfer'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('internal-money-transfers.destroy',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType ]) }}" method="post">
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
{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>











            @php
            $currentType = InternalMoneyTransfer::BANK_TO_SAFE ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Bank To Safe')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-internal-money-transfer :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('internal-money-transfers.create',['company'=>$company->id,'type'=>$currentType])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Withdrawal Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('From Bank') }}</th>
                                    <th>{{ __('Cheque Number') }}</th>
                                    <th>{{ __('From Account Type') }}</th>
                                    <th>{{ __('From Account Number') }}</th>
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

                                    <td class="text-nowrap">{{ $model->getTransferDateFormatted() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getFromBankName() }}</td>
                                    <td>{{ $model->getChequeNumber() }}</td>
                                    <td class="text-uppercase">{{ $model->getFromAccountTypeName() }}</td>
                                    <td class="text-transform">{{ $model->getFromAccountNumber() }}</td>
                                    <td>{{ $model->getToBranchName() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @include('reports._user_comment_modal',['model'=>$model])
											@include('reports._integrated_modal',['model'=>$model])
                                            @if(auth()->user()->can('update internal money transfer'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('internal-money-transfers.edit',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(auth()->user()->can('delete internal money transfer'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('internal-money-transfers.destroy',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType ]) }}" method="post">
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
{{ $models[$currentType]->appends(array_merge(request()->all(),['active' => $currentType]))->links('pagination::bootstrap-4') }}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>



			  @php
            $currentType = InternalMoneyTransfer::SAFE_TO_SAFE ;
            @endphp
            <!--Begin:: Tab Content-->
            <div class="tab-pane {{  Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
                <div class="kt-portlet kt-portlet--mobile">
                    <x-table-title.with-two-dates :type="$currentType" :title="__('Safe To Safe')" :startDate="$filterDates[$currentType]['startDate']??''" :endDate="$filterDates[$currentType]['endDate']??''">
                        <x-export-internal-money-transfer :search-fields="$searchFields[$currentType]" :money-received-type="$currentType" :has-search="1" :has-batch-collection="0" href="{{route('internal-money-transfers.create',['company'=>$company->id,'type'=>$currentType])}}" />
                    </x-table-title.with-two-dates>
                    <div class="kt-portlet__body">

                        <!--begin: Datatable -->
                        <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                            <thead>
                                <tr class="table-standard-color">
                                    <th>{{ __('#') }}</th>
                                    <th>{{ __('Withdrawal Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Currency') }}</th>
                                    <th>{{ __('From Safe') }}</th>
                                    <th>{{ __('Cheque Number') }}</th>
                                    <th>{{ __('From Branch') }}</th>
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

                                    <td class="text-nowrap">{{ $model->getTransferDateFormatted() }}</td>
                                    <td>{{ $model->getAmountFormatted() }}</td>
                                    <td>{{ $model->getCurrencyFormatted() }}</td>
                                    <td>{{ $model->getFromBankName() }}</td>
                                    <td>{{ $model->getChequeNumber() }}</td>
                        
                                    <td>{{ $model->getFromBranchName() }}</td>
                                    <td>{{ $model->getToBranchName() }}</td>
                                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                        <span style="overflow: visible; position: relative; width: 110px;">
                                            @include('reports._user_comment_modal',['model'=>$model])
											@include('reports._integrated_modal',['model'=>$model])
                                            @if(auth()->user()->can('update internal money transfer'))
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('internal-money-transfers.edit',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType]) }}"><i class="fa fa-pen-alt"></i></a>
                                            @endif
                                            @if(auth()->user()->can('delete internal money transfer'))
                                            <a data-toggle="modal" data-target="#delete-financial-institution-bank-id-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                            <div class="modal fade" id="delete-financial-institution-bank-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form action="{{ route('internal-money-transfers.destroy',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$currentType ]) }}" method="post">
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
