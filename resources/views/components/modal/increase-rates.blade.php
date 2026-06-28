@props([
'subModel',
'study'=>$study,
'title'=>__('Annual Increase Rate'),
'name'=>null,
'isByBranch'=>false,
'product'=>null
])

<script>
 var translations = {
        deleteConfirm: @json(__('Are you sure you want to delete this position?'))
    };
	</script>
<div class="modal modal-increase-rates  fade"  tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-blue" id="exampleModalLongTitle">{{ $title }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="customize-elements">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="text-center">{{ __('Year') }}</th>
                                <th class="text-center">{{ __('Rate %') }}</th>
                            </tr>
                        </thead>
                        <tbody>
						@php
							$totalRate = 0 ;
							$isReadonly = false ;
						@endphp
                            @for($yearNumber = 1 ;$yearNumber < $study->getDurationInYears() ; $yearNumber ++) <tr>
                        @php
					
							$yearFormatted = $study->getYearFromYearIndex($yearNumber);
							$currentIncreaseRate = isset($subModel) ? $subModel->getIncreaseRateAtYearIndex($yearNumber ) :  0;
							if($isByBranch){
												$currentVal = $study->microfinanceByBranchProductMixes->where('microfinance_product_id',$product->id)->first();
												
												$currentIncreaseRate= $currentVal->getIncreaseRateAtYearIndex($yearNumber) ;
												$isReadonly =true ;
											}
						@endphp
						        <td >
								<div class="max-w-selector-popup">
                                    <input readonly  class="form-control " value="Yr-{{ $yearFormatted }}" placeholder="{{ __('Year') .  ' ' . $yearNumber  }}">
								</div>
                                </td>
								
								 <td >
								<div class="max-w-selector-popup">
                                    <input @if($isReadonly) readonly @endif multiple name="{{ isset($name) ? $name.'['.$yearNumber.']' : 'increase_rates' }}" class="form-control " value="{{ $currentIncreaseRate }}" placeholder="{{ __('Increase %') .  ' ' . $yearNumber  }}">
								</div>
                                </td>
                                
                                
                                </tr>
                                @endfor
								{{-- <tr style="border-top:1px solid gray;padding-top:5px;text-align:center">
									<td class="td-for-total-payment-rate " disabled readonly>
										{{ $totalRate }} %
									</td>
									<td class="">-</td>
								</tr> --}}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn save-modal btn-primary" data-dismiss="modal">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
