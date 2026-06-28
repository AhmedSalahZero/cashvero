<style>
@media (min-width: 1400px) {
    .modal-dialog.modal-xl {
        max-width: 1499px;
    }
	}
</style>

<div class="modal fade " id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
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
								
                                <th class="text-center w-40-percentage text-capitalize th-main-color">{{ __('Name') }}</th>
                                <th class="text-center w-20-percentage text-capitalize th-main-color">{{ __('Total') }}</th>
                                <th class="text-center w-20-percentage text-capitalize th-main-color">{{ __('% Of Total') }}</th>
                                <th class="text-center w-20-percentage text-capitalize th-main-color">{{ __('% Of Sales') }}</th>
								
                            
                            </tr>
                        </thead>
                        <tbody>
						
							@php
								$total = 0 ;
								
								
							@endphp
						
                            @foreach($subItems as $subItemName => $itemArr)
							@if($subItemName == 'Total' || $subItemName == 'Growth Rate %')
							@continue
							@endif 
							
							
                       
                            <tr>
                               
					
                                <td class="w-40-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-left ignore-global-style" value="{{  $subItemName }}">
                                        </div>
                                    </div>
                                </td>
								
								@php
									$currentTotal = array_sum($itemArr['Avg. Prices'] ?? []);
									$percentageOfTotal = $cardTotal ? $currentTotal / $cardTotal * 100  : 0 ; 
									$percentageOfSales = $totalSales ? $currentTotal / $totalSales * 100  : 0 ; 
									$chartData['pie'][$name][] = ['name'=>$subItemName , 'value'=>$currentTotal];
								@endphp
								 <td class="w-20-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{   number_format($currentTotal) }}">
                                        </div>
                                    </div>
                                </td>
								
								
								<td class="w-20-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{   number_format($percentageOfTotal,2) . ' %'  }}">
                                        </div>
                                    </div>
                                </td>
								
								
								<td class="w-20-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{   number_format($percentageOfSales,2) . ' %'  }}">
                                        </div>
                                    </div>
                                </td>
								
								

                                
							
								
								
								
											
                              
								
								
								    
								

                            

                            </tr>
                         @endforeach
						 <tr>
						 	<td>
							
							</td>
							
							
							<td>
							
							</td>
						
							
						
							
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
