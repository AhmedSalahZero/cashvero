 <a data-toggle="modal" data-target="#edit-fees-modal-{{ $currentStatementId }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Edit Commission Fees') }}" href="#"><i class="fa fa-pen"></i></a>
 
 <div class="modal fade" id="edit-fees-modal-{{ $currentStatementId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
         <div class="modal-content">
             <form  action="{{ route('update.bank.statement.debit.or.credit',['company'=>$company->id ]) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post">
                 @csrf
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Please Confirm End Of Month Fees Date & Amount ?') }}</h5>
                     <button type="button" class="close" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>


                 <div class="modal-body">
                     <div class="row mb-3">

                       
						 <input type="hidden" name="statement_model_name" value="{{ $statementModelName }}">
						 <input type="hidden" name="statement_id" value="{{ $currentStatementId }}">
						 {{-- @if( ) --}}
						<div class="col-md-3 mb-4">
						
						<label>{{__('Final Result?')}} </label>
						  <div class="kt-input-icon">
							 <input class="form-control" style="height:20px;width:20px;" type="checkbox" name="is_end_of_month_final" value="1"  checked  >
						  </div>
							 </div>
                         <div class="col-md-5 mb-4">
                             <label>{{__('Amount')}} </label>
                             <div class="kt-input-icon">
                                 <input  value="{{  $currentDebitOrCredit  }}" type="text" name="{{ $debitOrCreditText }}" class="form-control text-center only-greater-than-or-equal-zero-allowed">
                             </div>
                         </div>

                         <div class="col-md-4 mb-4">
                             <label>{{__('Date')}}</label>
                             <div class="kt-input-icon">
                                 <div class="input-group date">
                                     <input required type="text" name="date" value="{{ formatDateForDatePicker($currentDate) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
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
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                     <button type="submit" class="btn btn-danger">{{ __('Confirm') }}</button>
                 </div>

             </form>
         </div>
     </div>
 </div>
