<style>
@media (min-width: 1400px) {
    .modal-dialog.modal-xl {
        max-width: 1499px;
    }
	}
</style>

<div class="modal fade " id="{{ $modalId.$currency }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <form action="#" class="modal-content" method="post">
		
								
		@csrf
            <div class="modal-header">
                <h5 class="modal-title"  id="exampleModalLongTitle"> {{ $title }} </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="customize-elements">
                    <table class="table">
                        <thead>
                            <tr>
								
                                <th class="text-center w-40-percentage text-capitalize th-main-color">{{ __('Financial Institution / Branch Name') }}</th>
                                <th class="text-center w-20-percentage text-capitalize th-main-color">{{ __('Account Number') }}</th>
                                <th class="text-center w-15-percentage text-capitalize th-main-color"> {!! __('Amount').' [ '.$currency . ' ]' !!} </th>
								@if($currency != $mainFunctionalCurrency)
                                <th class="text-center w-10-percentage text-capitalize th-main-color"> {!! __('Exchange Rate') !!} </th>
                                <th class="text-center w-15-percentage text-capitalize th-main-color"> {!! __('Amount'). ' [ ' . $mainFunctionalCurrency . ' ]' !!}  </th>
								@endif
                            
                            </tr>
                        </thead>
                        <tbody>
						
							@php
								$total = 0 ;
								$totalInMainFunctionalCurrency = 0 ;
								
								
							@endphp
                            @foreach($detailItems as $detailItem)
							
                       @if($detailItem['amount'] != 0)
                            <tr>
                               
					
                                <td class="w-40-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-left ignore-global-style" value="{{  isset($detailItem['branch_name']) ? $detailItem['branch_name'] : $detailItem['financial_institution_name'] }}">
                                        </div>
                                    </div>
                                </td>
								
								 <td class="w-20-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{   $detailItem['account_number'] ?? '-' }}">
                                        </div>
                                    </div>
                                </td>
								

                                <td class="w-15-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ number_format($detailItem['amount']) }}">
											
                                        </div>
                                    </div>
                                </td>
								
								@if($currency != $mainFunctionalCurrency)
								  <td class="w-10-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ $exchangeRates[$currency] }}">
                                        </div>
                                    </div>
                                </td>
								
								 <td class="w-15-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ number_format($exchangeRates[$currency] * $detailItem['amount'])  }}">
                                        </div>
                                    </div>
                                </td>
								@endif 
								
								
								
											@php
												$total +=$detailItem['amount'];
												if($mainFunctionalCurrency != $currency){
													$totalInMainFunctionalCurrency  += ($exchangeRates[$currency] * $detailItem['amount']);
												}
											@endphp
                              
								
								
								    
								

                            

                            </tr>
							@endif
                         @endforeach
						 <tr>
						 	<td>
							
							</td>
							
							
							<td>
							
							</td>
							<td class="text-center">
							
							{{ number_format($total)  }}
							</td>	
							@if($mainFunctionalCurrency != $currency)
							<td class="text-center">
							
						
							</td>	<td class="text-center">
							
							{{ number_format($totalInMainFunctionalCurrency)  }}
							</td>
							@endif
						
							
						 </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary 
				{{-- submit-form-btn --}}
				"
				 data-dismiss="modal"
				 
				 >{{ __('Close') }}</button>
            </div>
        </form>
    </div>
</div>
