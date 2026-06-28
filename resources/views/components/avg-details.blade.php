										@php					
											$currentModalId = convertStringToClass($itemName.$idPrefix.$mainItemName);
											$title = $itemName .  ' ' .__('Models') ;
										@endphp
										
							  				<button
										
											 class="btn 
											@if(!count($currentDates) || (isset($reportData['report_data'][$mainItemName][$itemName]['dates']) && !count($reportData['report_data'][$mainItemName][$itemName]['dates'])))
											 visibility-hidden
											@endif 
											  btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModalId }}">{{ __('Details') }}</button>
							
											@if(isset($reportData['report_data'][$mainItemName][$itemName]['date_and_value_modal']))
                                     	   @include('admin.modals.avg-min-max-outliers-date-value-modals',['detailItems'=> $reportData['report_data'][$mainItemName][$itemName]??[] , 'modalId'=>$currentModalId ,'title'=>$title])
										   @elseif(isset($reportData['report_data'][$mainItemName][$itemName]['only_date_modal']))
                                     	   @include('admin.modals.avg-min-max-outliers-date-modals',['detailItems'=> $reportData['report_data'][$mainItemName][$itemName]??[] , 'modalId'=>$currentModalId ,'title'=>$title])
										   @endif
