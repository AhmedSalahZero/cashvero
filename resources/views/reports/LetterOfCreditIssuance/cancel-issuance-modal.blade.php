<a data-toggle="modal" data-target="#cancel-deposit-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Apply payment') }}" href="#"><i class="fa fa-coins"></i></a>
<div class="modal fade" id="cancel-deposit-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('make.letter.of.credit.issuance.as.paid',['company'=>$company->id,'letterOfCreditIssuance'=>$model->id,'source'=>$model->getSource() ]) }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Pay This LC ?') }}</h5>
                    <button type="button" class="close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body closest-parent">
                    <div class="row mb-3">

                        <div class="col-md-6 mb-4">
                            <label>{{__('Bank Name')}} </label>
                            <div class="kt-input-icon">
                                <input type="hidden" name="financial_institution_id" value="{{  $model->getFinancialInstitutionBankId()}}">
                                <input disabled value="{{  $model->getFinancialInstitutionBankName()  }}" type="text" class="form-control">
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('LC Amount')}} </label>
                            <div class="kt-input-icon">
                                <input data-value="{{ $model->getLcAmount() }}" disabled value="{{  number_format($model->getLcAmount() ) . ' ' . $model->getLcCurrency()  }}" type="text" class="form-control lc-amount text-center">
                            </div>
                        </div>



                        <div class="col-md-2 mb-4">
                            <label>{{__('Exchange Rate')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="{{  number_format($model->getExchangeRate(),2 )  }}" type="text" class="form-control  text-center">
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('In Payment Currency')}} </label>
                            <div class="kt-input-icon">
                                <input data-value="{{ $model->getLcAmountInMainCurrency() }}" disabled value="{{  $model->getAmountInMainCurrencyFormatted()  }}" type="text" class="form-control lc-amount-in-main-currency text-center">
                            </div>
                        </div>











                        <input type="hidden" class="cash-cover-rate" value="{{ $model->getCashCoverRate() }}">

                        <div class="col-md-6 mb-4">
                            <label>{{__('Cash Cover')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="{{  __('Cash Cover') }}" type="text" class="form-control">
                            </div>
                        </div>
                        @php
                        $cashCoverAmount = $model->getCashCoverAmount();
                        @endphp
                        <div class="col-md-2 mb-4">
                            <label>{{__('Amount')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="{{  $model->getCashCoverAmountFormatted() . ' ' . $model->getLcCashCoverCurrency()  }}" type="text" class="form-control text-center">
                            </div>
                        </div>

                        @php
                        $exchangeRate = $model->getLcCashCoverCurrency() == $company->getMainFunctionalCurrency() ? 1 : $model->getExchangeRate();
                        @endphp
                        <div class="col-md-2 mb-4">
                            <label>{{__('Exchange Rate')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="{{ number_format($exchangeRate,2)  }}" type="text" class="form-control text-center">
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('In Payment Currency')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="{{  number_format($cashCoverAmount * $exchangeRate)  }}" type="text" class="form-control text-center">
                            </div>
                        </div>













                        <div class="col-md-3 mb-4">
                            <label>{{__('Payment Date (mm/dd/yy)' )}}</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="text" name="payment_date" value="{{ formatDateForDatePicker($model->getDueDate()) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="la la-calendar-check-o"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                        $lcAmount = $model->getLcAmount();
                        $invoices = \App\Models\SupplierInvoice::onlyCompany($company->id)->onlyForPartner($model->getBeneficiaryId())
                        ->where(function($q) use($lcAmount){
                        $q->orHas('letterOfCreditIssuancePaymentSettlements')
	                        ->orWhere('net_balance','>=',$lcAmount);
                    	})
                        ->onlyCurrency($model->getLcCurrency())
                        ->get();



                        @endphp
                        <div class="col-md-3">
                            <label>{{ __('Invoice') }} <span class=""></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="supplier_invoice_id" class="form-control update-net-balance-inputs">
                                        @foreach($invoices as $invoice)
                                        <option @if($model->getSupplierInvoiceId() == $invoice->id )
                                            selected
                                            @endif
                                            data-current-select="{{ $model->getSupplierInvoiceId() }}" data-currency="{{ $invoice->getCurrency() }}" data-invoice-net-balance="{{ $invoice->getNetBalance() }}" data-exchange-rate="{{ $invoice->getExchangeRate() }}" data-invoice-net-balance-in-main-currency="{{ $invoice->getNetBalanceInMainCurrency() }}" value="{{ $invoice->id }}">{{ $invoice->getInvoiceNumber() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('Invoice Net Balance')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="0" type="text" class="form-control net-balance text-center">
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('Exchange Rate')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="0" type="text" class="form-control exchange-rate text-center">
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{__('NB In Main Currency')}} </label>
                            <div class="kt-input-icon">
                                <input disabled value="0" type="text" class="form-control net-balance-in-main-currency text-center">
                            </div>
                        </div>







                        @if($model->isFinancedBySelf())

                        <div class="col-md-3">
                            <label>{{__('LC Payment Currency')}}
                                @include('star')
                            </label>
                            <div class="input-group">
                                <select name="payment_currency" class="form-control update-remaining-class current-currency" js-when-change-trigger-change-account-type>
                                    <option selected>{{__('Select')}}</option>
                                    @foreach([$company->getMainFunctionalCurrency()=>$company->getMainFunctionalCurrency() , $model->getLcCurrency()=>$model->getLcCurrency() ] as $currencyName => $currencyValue )
                                    <option value="{{ $currencyName }}" @if(isset($model) && $model->getPaymentCurrency() == $currencyName ) selected @elseif($currencyName == $company->getMainFunctionalCurrency() ) selected @endif > {{ $currencyValue }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>



                        <div class="col-md-3 mb-4">
                            <label>{{__('LC Remaining Amount')}} </label>
                            <div class="kt-input-icon">
                                <input readonly name="lc_remaining_amount" value="0" type="text" class="form-control lc-remaining-amount-class text-center">
                            </div>
                        </div>



                        <div class="col-md-3">
                            <label>{{ __('Account Type') }} <span class=""></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="account_type_id" name="payment_account_type_id" class="form-control  js-update-account-id-based-on-account-type">
                                        @foreach($currentAccounts as $index => $accountType)
                                        <option value="{{ $accountType->id }}" @if($accountType->id == $model->getPaymentAccountTypeId())
                                            selected
                                            @endif
                                            >{{ $accountType->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label>{{ __('Account Number') }} <span class=""></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select js-cd-or-td-account-number data-current-selected="{{ isset($model) ? $model->getPaymentAccountNumberId(): 0 }}" name="payment_account_number_id" class="form-control js-account-number">
                                        <option value="" selected>{{__('Select')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
						
						
						 <div class="col-md-3">
                            <label>{{__('Interest Currency')}}
                                @include('star')
                            </label>
                            <div class="input-group">
                                <select name="interest_currency" class="form-control">
                                    <option selected>{{__('Select')}}</option>
                                    @foreach([$company->getMainFunctionalCurrency()=>$company->getMainFunctionalCurrency() , $model->getLcCurrency()=>$model->getLcCurrency() ] as $currencyName => $currencyValue )
                                    <option value="{{ $currencyName }}" @if(isset($model) && $model->getInterestCurrency() == $currencyName ) selected @elseif($currencyName == $company->getMainFunctionalCurrency() ) selected @endif > {{ $currencyValue }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
						
						
                        <div class="col-md-3 mb-4">
                            <label>{{__('Interest Amount')}} </label>
                            <div class="kt-input-icon">
                                <input  name="interest_amount" value="{{ $model->getInterestAmountFormatted() }}" type="text" class="form-control text-center">
                            </div>
                        </div>
						



                        @endif





                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Allocate Payment To Customer Contract') }}</h5>
                        </div>






                        <div class="form-group row justify-content-center w-100">
                            @php
                            $index = 0 ;
                            @endphp

                            {{-- start of fixed monthly repeating amount --}}
                            @php
                            $tableId = 'allocations';

                            $repeaterId = 'm_repeater_9';

                            @endphp
                            {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                            <x-tables.repeater-table :initialJs="false" :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                                <x-slot name="ths">
                                    @foreach([
                                    __('Customer')=>'th-main-color custom-w-25',
                                    __('Contract Name')=>'th-main-color custom-w-25 ',
                                    __('Contract Code')=>'th-main-color ',
                                    __('Contract Amount')=>'th-main-color',
                                    __('Allocate Amount')=>'th-main-color',
                                    ] as $title=>$classes)
                                    <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                                    @endforeach
                                </x-slot>
                                <x-slot name="trs">
                                    @php

                                    $rows = isset($model) ? $model->settlementAllocations :[-1] ;

                                    @endphp
                                    @foreach( count($rows) ? $rows : [-1] as $settlementAllocation)
                                    @php
                                    $fullPath = new \App\Models\SettlementAllocation;
                                    if( !($settlementAllocation instanceof $fullPath) ){
                                    unset($settlementAllocation);
                                    }
                                    @endphp
                                    <tr @if($isRepeater) data-repeater-item @endif>

                                        <td class="text-center">
                                            <input type="hidden" name="company_id" value="{{ $company->id }}">
                                            <div class="">
                                                <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                                </i>
                                            </div>
                                        </td>
                                        <td>

                                            <x-form.select :insideModalWithJs="false" :selectedValue="isset($settlementAllocation) && $settlementAllocation->partner_id ? $settlementAllocation->partner_id : ''" :options="formatOptionsForSelect($clientsWithContracts)" :add-new="false" class=" suppliers-or-customers-js " data-filter-type="{{ 'create' }}" :all="false" data-name="partner_id" name="partner_id"></x-form.select>
                                        </td>

                                        <td>
                                            <x-form.select :insideModalWithJs="false" data-current-selected="{{ isset($settlementAllocation) ? $settlementAllocation->contract_id : '' }}" :selectedValue="isset($settlementAllocation) ? $settlementAllocation->contract_id : ''" :options="[]" :add-new="false" class=" contracts-js   " data-filter-type="{{ 'create' }}" :all="false" data-name="contract_id" name="contract_id"></x-form.select>
                                        </td>

                                        <td>
                                            <div class="kt-input-icon custom-w-20">
                                                <div class="input-group">
                                                    <input disabled type="text" class="form-control contract-code " value="">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="kt-input-icon custom-w-15">
                                                <div class="input-group">
                                                    <input disabled type="text" class="form-control contract-amount" value="0">
                                                </div>
                                            </div>
                                        </td>


                                        <td>
                                            <div class="kt-input-icon custom-w-15">
                                                <div class="input-group">
                                                    <input type="text" data-name="allocation_amount" name="allocation_amount" class="form-control " value="{{ isset($settlementAllocation) ? $settlementAllocation->getAmount(): 0 }}">
                                                </div>
                                            </div>
                                        </td>


                                    </tr>



                                    @endforeach

                                </x-slot>




                            </x-tables.repeater-table>
                            {{-- end of fixed monthly repeating amount --}}















































































                        </div>





                    </div>
                </div>


                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                    @if(!isset($disabled))
                    <button type="submit" class="btn btn-danger">{{ __('Confirm') }}</button>
                    @endif
                </div>

            </form>
        </div>
    </div>
</div>
