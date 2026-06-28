

<div class="modal fade " id="{{ $modalId.$currency.$lgOrLcType }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
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
								
                                <th class="text-center w-50-percentage text-capitalize th-main-color">{{ __('Financial Institution') }}</th>
                           
                                <th class="text-center  text-capitalize th-main-color"> {!! __('Limit') !!} </th>
                                <th class="text-center  text-capitalize th-main-color"> {!! __('Outstanding') !!} </th>
                                <th class="text-center  text-capitalize th-main-color"> {!! __('Room') !!} </th>
                                <th class="text-center  text-capitalize th-main-color"> {!! __('Cash Cover') !!} </th>
								
							
                            
                            </tr>
                        </thead>
                        <tbody>
						
							@php
								$totals = [] ;
								
							@endphp
                            @foreach($detailItems as $detailItem)
							
                       
                            <tr>
                               
					
                                <td class="w-50-percentage">
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-left ignore-global-style" value="{{  isset($detailItem['branch_name']) ? $detailItem['branch_name'] : $detailItem['financial_institution_name'] }}">
                                        </div>
                                    </div>
                                </td>
								@foreach(['limit','outstanding_balance','room','cash_cover'] as $colName)
                                <td >
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ number_format($detailItem[$colName]) }}">
											@php
												$totals[$colName]= isset($totals[$colName]) ? $totals[$colName] +  $detailItem[$colName] : ($detailItem[$colName])??0 ;
											@endphp
                                        </div>
                                    </div>
                                </td>
								@endforeach

                              
								
								
								    
								

                            

                            </tr>
                         @endforeach
						 <tr>
						 	<td>
							
							</td>
							
							
							
							@foreach(['limit','outstanding_balance','room','cash_cover'] as $colName)
							<td class="text-center">
								{{ number_format($totals[$colName]??0) }}
							</td>
							@endforeach 
							
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
