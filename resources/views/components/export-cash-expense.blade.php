@props([
'selectedBanks','banks','hasBatchCollection','hasSearch','cashExpenseType','searchFields'
,'financialInstitutionBanks',
'isFirstExportMoney'=>false,
'accountTypes',
'popupTitle'=>'',
'routeAction'=>'#',
'routeRedirect'=>route('view.cash.expense',['company'=>$company->id])
])
@php
use App\Models\CashExpense ;
@endphp
<div class="kt-portlet__head-toolbar" style="flex:1 !important;">
    <div class="kt-portlet__head-wrapper">
        <div class="kt-portlet__head-actions">
            &nbsp;
            @if($hasBatchCollection)
            <a  data-money-type="{{ $cashExpenseType }}" data-type="multi" data-toggle="modal" data-target="#send-to-under-collection-modal{{ $cashExpenseType }}" id="js-send-to-under-collection-trigger{{ $cashExpenseType }}" href="{{route('create.money.receive',['company'=>$company->id])}}" title="{{ __('Please Select More Than One Cheque') }}" class="btn  active-style btn-icon-sm js-can-trigger-cheque-under-collection-modal disabled">
                <i class="fas fa-book"></i>
                {{ __('Create Batch Mark As Paid') }}
            </a>
            @endif
            @if($hasSearch)
            <a data-type="multi" data-toggle="modal" data-target="#search-money-modal-{{ $cashExpenseType }}" id="js-search-money-received" href="#" title="{{ __('Search Money Payments') }}" class="btn  active-style btn-icon-sm  ">
                <i class="fas fa-search"></i>
                {{ __('Advanced Filter') }}
            </a>

            <div class="modal fade" id="search-money-modal-{{ $cashExpenseType }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="delete_from_to_modalTitle">{{ __('Filter Form') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            @csrf
                            <form action="{{ $routeRedirect }}" class="row ">
                                <input name="active" type="hidden" value="{{ $cashExpenseType }}">
                                <div class="form-group col-4">
                                    <label for="Select Field " class="label">{{ __('Field Name') }}</label>
                                    <select id="js-search-modal-name-{{ $cashExpenseType }}" data-type="{{ $cashExpenseType }}" class="form-control js-search-modal" type="date" name="field" placeholder="{{ __('Delete From') }}">
                                        @foreach($searchFields as $name=>$value)
                                        <option @if(Request('field')==$name) selected @endif value="{{ $name }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-4">
                                    <label for="Select Field " class="label">{{ __('Search Text') }}</label>
                                    <input name="value" type="text" value="{{ request('value') }}" placeholder="{{ __('Search Text') }}" class="form-control search-field">
                                </div>

                                <div class="form-group col-2">
                                    <label for="search-from " class="label">{{ __('From') }} <span class="data-type-span">{{ __('[ Receiving Date ]') }}</span> </label>
                                    <input name="from" type="date" value="{{ request('from') }}" class="form-control">
                                </div>

                                <div class="form-group col-2">
                                    <label for="search-to " class="label">{{ __('To') }} <span class="data-type-span">{{ __('[ Receiving Date ]') }}</span> </label>
                                    <input name="to" type="date" value="{{ request('to') }}" class="form-control">

                                </div>



                                <div class="modal-footer">
                                    <button type="submit" href="{{ route('view.money.receive',['company'=>$company->id]) }}" id="js-search-id" type="submit" id="" class="btn btn-primary">{{ __('Search') }}</button>
									<button  href="#" id="reset-search-id" type="button"  class="btn btn-primary">{{ __('Reset') }}</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @endif





            <div class="modal fade" id="send-to-under-collection-modal{{ $cashExpenseType }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog  modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form  data-money-type="{{ $cashExpenseType }}" id="ajax-send-cheques-to-collection-id{{ $cashExpenseType }}" class="ajax-send-cheques-to-collection" action="{{ $routeAction }}" method="post">
                            <input type="hidden" id="single-or-multi{{ $cashExpenseType }}" value="single">
                            <input type="hidden" id="current-single-item{{ $cashExpenseType }}" value="0">
                            <input type="hidden" id="current-currency{{ $cashExpenseType }}" class="current-currency"  value="">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLongTitle">{{ $popupTitle }}</h5>
                                <button type="button" class="close" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label>{{__('Actual Payment Date')}}</label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <input required type="text" name="actual_payment_date" value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                                <div class="input-group-append">
                                                    <span class="input-group-text">
                                                        <i class="la la-calendar-check-o"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>
