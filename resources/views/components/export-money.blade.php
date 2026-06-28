@props([
'selectedBanks','banks','hasBatchCollection','hasSearch','moneyReceivedType','searchFields'
,'financialInstitutionBanks',
'isFirstExportMoney'=>false,
'accountTypes'
])
@php
use App\Models\MoneyReceived ;
@endphp
<div class="kt-portlet__head-toolbar" style="flex:1 !important;">
    <div class="kt-portlet__head-wrapper">
        <div class="kt-portlet__head-actions">
            &nbsp;
            @if($hasBatchCollection)
            <a data-money-type="{{ $moneyReceivedType }}" data-type="multi" data-toggle="modal" data-target="#send-to-under-collection-modal{{ $moneyReceivedType }}" id="js-send-to-under-collection-trigger{{ $moneyReceivedType }}" href="{{route('create.money.receive',['company'=>$company->id])}}" title="{{ __('Please Select More Than One Cheque') }}" class="btn  active-style btn-icon-sm js-can-trigger-cheque-under-collection-modal disabled">
                <i class="fas fa-book"></i>
                {{ __('Create Batch Send To Collection') }}
            </a>
            @endif
            @if($hasSearch)
            <a data-type="multi" data-toggle="modal" data-target="#search-money-modal-{{ $moneyReceivedType }}" id="js-search-money-received" href="#" title="{{ __('Search Money Received') }}" class="btn  active-style btn-icon-sm  ">
                <i class="fas fa-search"></i>
                {{ __('Advanced Filter') }}
            </a>

            <div class="modal fade" id="search-money-modal-{{ $moneyReceivedType }}" tabindex="-1" role="dialog" aria-hidden="true">
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
                            <form action="{{ route('view.money.receive',['company'=>$company->id]) }}" class="row ">
                                <input name="active" type="hidden" value="{{ $moneyReceivedType }}">
                                <div class="form-group col-4">
                                    <label for="Select Field " class="label">{{ __('Field Name') }}</label>
                                    <select id="js-search-modal-name-{{ $moneyReceivedType }}" data-type="{{ $moneyReceivedType }}" class="form-control js-search-modal" type="date" name="field" placeholder="{{ __('Delete From') }}">
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
                                    <button href="#" id="reset-search-id" type="button" class="btn btn-primary">{{ __('Reset') }}</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @endif





            <div class="modal fade" id="send-to-under-collection-modal{{ $moneyReceivedType }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form data-money-type="{{ $moneyReceivedType }}" id="ajax-send-cheques-to-collection-id{{ $moneyReceivedType }}" class="ajax-send-cheques-to-collection" action="{{ route('cheque.send.to.collection',['company'=>$company->id]) }}" method="post">
                            <input type="hidden" id="single-or-multi{{ $moneyReceivedType }}" value="single">
                            <input type="hidden" id="current-single-item{{ $moneyReceivedType }}" value="0">
                            <input type="hidden" id="current-currency{{ $moneyReceivedType }}" class="current-currency" value="">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Send This Cheque / Cheques To Under Collection ?') }}</h5>
                                <button type="button" class="close" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label>{{__('Cheque Deposit Date')}}</label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <input required type="text" name="deposit_date" value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" max-date="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control kt_datepicker_max_date_is_today" readonly placeholder="Select date" id="send-under-collection-deposit-{{ $moneyReceivedType }}" />
                                                <div class="input-group-append">
                                                    <span class="input-group-text">
                                                        <i class="la la-calendar-check-o"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-9 mb-3">
                                        <label>{{__('Drawal Bank')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date ">
                                                <select js-when-change-trigger-change-account-type data-financial-institution-id required name="drawl_bank_id" class="form-control js-drawl-bank">
                                                    @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                    <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->cheque && $model->cheque->getDraweeBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <div class="row mb-3">


                                    <div class="col-md-3 ">
                                        <label>{{__('Account Type')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select name="account_type" class="form-control js-update-account-number-based-on-account-type">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                    @foreach($accountTypes as $index => $accountType)
                                                    <option value="{{ $accountType->id }}" @if(isset($model) && $model->getCashInBankAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>{{__('Account Number')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select name="account_number" class="form-control js-account-number">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-3">
                                        <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                        <div class="kt-input-icon">
                                            <input value="0" type="text" disabled class="form-control balance-js" placeholder="{{__('Account Balance')}}">
                                        </div>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label>{{__('Net Balance')}} <span class="net-balance-date-js"></span> </label>
                                        <div class="kt-input-icon">
                                            <input value="0" type="text" disabled class="form-control net-balance-js" placeholder="{{__('Net Balance')}}">

                                        </div>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label>{{__('Clearance Days')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <input value="0" required name="clearance_days" step="any" min="0" class="form-control only-greater-than-zero-or-equal-allowed" placeholder="{{__('Clearance Days')}}">
                                            <x-tool-tip title="{{__('Kash Vero')}}" />
                                        </div>
                                    </div>



                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success 
								
								{{-- submit-form-btn --}}
								
								">{{ __('Confirm') }}</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>
