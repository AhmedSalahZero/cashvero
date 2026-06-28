<a data-toggle="modal" data-target="#edit-opening-cheques{{ $moneyPayment->id }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Edit Cheque') }}" href="#"><i class="fa fa-pen-alt exclude-icon default-icon-color"></i></a>

<div class="modal closest-parent-class editable-opening-balance-cheque fade" id="edit-opening-cheques{{ $moneyPayment->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog  modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form   action="{{ route('update.opening.payable.cheque',['company'=>$company->id,'moneyPayment'=>$moneyPayment->id,'payableCheque'=>$moneyPayment->payableCheque->id]) }}" method="post">
                @csrf
				<input type="hidden" >
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Opening Payable Cheque Edit') }}</h5>
                    <button type="button" class="close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">

                        <div class="col-md-4">
                            <label>{{__('Supplier Name')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <x-form.select :options="$suppliersFormatted" :add-new="false" :label="' '" class="customer_name_class repeater-select" data-filter-type="{{ 'create' }}" :all="false" name="supplier_id" :selected-value="isset($moneyPayment) ? $moneyPayment->getSupplierId() : 0"></x-form.select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-1">
                            <label>{{__('Currency')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="currency" class="form-control select-for-currency-temp ajax-get-invoice-numbers" js-when-change-trigger-change-account-type>
                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                        <option value="{{ $currencyName }}" @if(isset($moneyPayment) && $moneyPayment->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-1">
                            <label>{{__('FX Rate')}}</label>

                            <div class="kt-input-icon width-15">
                                <div class="input-group">
                                    <input name="exchange_rate" type="numeric" class="form-control " value="{{ isset($moneyPayment) ? $moneyPayment->getExchangeRate() : old('exchange_rate',1) }}">
                                </div>
                            </div>


                        </div>



                        <div class="col-md-2">
                            <label>{{__('Due Date')}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <x-calendar :onlyMonth="false" :showLabel="false" :value="isset($moneyPayment) ?  formatDateForDatePicker($moneyPayment->getPayableChequeDueDate()) : formatDateForDatePicker(now())" :label="__('Due Date')" :id="'due_date'" name="due_date"></x-calendar>

                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label>{{__('Amount')}}</label>

                            <div class="kt-input-icon width-15">
                                <div class="input-group">
                                    <input name="paid_amount" type="text" class="form-control " value="{{ number_format(isset($moneyPayment) ? $moneyPayment->getPaidAmount() : old('paid_amount',0)) }}">
                                </div>
                            </div>

                        </div>

                        <div class="col-md-2">
                            <label>{{__('Cheque No.')}}</label>

                            <div class="kt-input-icon width-15">
                                <div class="input-group">
                                    <input name="cheque_number" type="text" class="form-control " value="{{ isset($moneyPayment) ? $moneyPayment->getPayableChequeNumber() : old('cheque_number',0)}}">
                                </div>
                            </div>


                        </div>



                        <div class="col-md-6 mb-3 mt-3">
                            <label>{{__('Drawal Bank')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date ">
                                    <select js-when-change-trigger-change-account-type data-financial-institution-id required name="drawl_bank_id" class="form-control js-drawl-bank">
                                        @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                        <option value="{{ $financialInstitutionBank->id }}" {{ isset($moneyPayment) && $moneyPayment->cheque && $moneyPayment->cheque->getDraweeBankId() == $financialInstitutionBank->id ? 'selected':'' }}>{{ $financialInstitutionBank->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mt-3">
                            <label>{{__('Account Type')}} @include('star')</label>
                           <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="account_type" class="form-control js-update-account-number-based-on-account-type">
                                        <option value="" selected>{{__('Select')}}</option>
                                        @foreach($accountTypes as $index => $accountType)
                                        <option value="{{ $accountType->id }}" @if(isset($moneyPayment) && $moneyPayment->getPayableChequeAccountType() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mt-3">
                            <label>{{__('Account Number')}} @include('star')</label>
                            
							
							  <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select data-current-selected="{{ isset($moneyPayment) ? $moneyPayment->getPayableChequeAccountNumber(): 0 }}" name="account_number" class="form-control js-account-number">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                        </select>
                                                    </div>
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
