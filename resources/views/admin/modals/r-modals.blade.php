@php
	use App\Helpers\HMath;
	use App\Helpers\HArr;
	use MathPHP\Statistics\Average;

@endphp
<style>
@media (min-width: 1400px) {
    .modal-dialog.modal-xl {
        max-width: 1499px;
    }
	}
</style>
@php
								$salesChange = count($monthlySalesForSalesGathering) ?  Average::mean($monthlySalesForSalesGathering) : 0;
							@endphp
<div class="modal fade " id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="#" class="modal-content" method="post">
		
								
		@csrf
            <div class="modal-header">
				<div class="d-flex flex-column " >
				
                <h5 class="modal-title mb-3"  id="exampleModalLongTitle"> {{ $title }} <br> </h5> 
								 <h5 class="modal-title text-left" style="color:red !important" id="exampleModalLongTitle"> {{ __('For Each Incremental Sales Of '.number_format($salesChange)) }}</h5>
				</div>

				

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="customize-elements">
                    <table class="table">
                        <thead>
                            <tr>
								
                                <th class="text-center w-40-percentage text-capitalize th-main-color">{{ __('Name') }} </th>
								
                                <th class="text-center w-40-percentage text-capitalize th-main-color">{{ __('Fixed / Variable') }}</th>
                                <th class="text-center w-40-percentage text-capitalize th-main-color">{{ __('Expense Change Value') }}</th>
							
                            
                            </tr>
                        </thead>
                        <tbody>
							@php
								$currentSubItemsAndExpenseChange =[];
							@endphp
							@foreach($detailItems as $currentSubItemName => $coefficientCorrelationValue)
							@php
								$expenseItemsValues = $result['report_data'][$mainCategoriesName][$currentSubItemName]['Avg. Prices'] ?? [];
								$currentTextBasedOnCorrelationValue = HMath::generateTextBasedOnCoefficientCorrelationValue($coefficientCorrelationValue);
								
								$expenseChange = count($monthlySalesForSalesGathering) ? HMath::calculateIncreaseInExpensePerSalesValue($coefficientCorrelationValue,$expenseItemsValues,$monthlySalesForSalesGathering,$salesChange) : 0;
								if(HMath::isFixedExpense($coefficientCorrelationValue)){
									$expenseChange = Average::mean($expenseItemsValues);
								}
								$currentSubItemsAndExpenseChange[$currentSubItemName] = [
									'value'=>$expenseChange ,
									'text'=>$currentTextBasedOnCorrelationValue
								] ;
							@endphp
							@endforeach 
							@php
							$currentSubItemsAndExpenseChange = HArr::sortTwoDimArrayAndPreserveKeyNameBasedOnKeyDesc($currentSubItemsAndExpenseChange,'value');
								
							@endphp
                            @foreach($currentSubItemsAndExpenseChange as $currentSubItemName => $expenseChangeTextAndValue)
						@php
							$currentTextBasedOnCorrelationValue = $expenseChangeTextAndValue['text'];
							$expenseChange = $expenseChangeTextAndValue['value'];
						@endphp
                            <tr>
                                <td class="w-40-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-left ignore-global-style" value="{{  $currentSubItemName }}">
                                        </div>
                                    </div>
                                </td>
						
                                <td class="w-15-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control  ignore-global-style text-left" value="{{ $currentTextBasedOnCorrelationValue }}">
                                        </div>
                                    </div>
                                </td>
								
								
								  <td class="w-15-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ number_format($expenseChange) }}">
                                        </div>
                                    </div>
                                </td>
								
						

                            </tr>
                         @endforeach
						 {{-- <tr>
						 	<td>
							
							</td>
							
							
							<td>
							
							</td>
							<td class="text-center">
							
							{{ number_format(0)  }}
							</td>	
							
						
							
						 </tr> --}}
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
