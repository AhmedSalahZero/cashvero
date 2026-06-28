@if($model->isRunning() || $model->isExpired())

 <a data-toggle="modal" data-target="#cancel-deposit-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Cancel Letter') }}" href="#"><i class="fa fa-ban"></i></a>
 <div class="modal fade" id="cancel-deposit-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="{{ route('cancel.letter.of.guarantee.issuance',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$model->getSource() ]) }}" method="post">
                 @csrf
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Cancel This Letter ?') }}</h5>
                     <button type="button" class="close" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>


                 <div class="modal-body">
                     <div class="row mb-3">

                         <div class="col-md-6 mb-4">
                             <label>{{__('Bank Name')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getFinancialInstitutionBankName()  }}" type="text" class="form-control">
                             </div>
                         </div>
						 
						  <div class="col-md-2 mb-4">
                             <label>{{__('LG Code')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getLgCode()  }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed">
                             </div>
                         </div>
                         <div class="col-md-2 mb-4">
                             <label>{{__('LG Current Amount')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getLgCurrentAmountFormatted()  }}" type="text" class="form-control text-center ">
                             </div>
                         </div>

                         <div class="col-md-2 mb-4">
                             <label>{{__('Cancellation Date')}}</label>
                             <div class="kt-input-icon">
                                 <div class="input-group date">
                                     <input required type="text" name="cancellation_date" value="{{ formatDateForDatePicker($model->getRenewalDate()) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
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

@endif
 @if($model->isRunning())
 @if($model->isAdvancedPayment())
 
 <a data-toggle="modal" data-target="#amount-to-be-decreased-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Amount To Be Decreased') }}" href="#"><i class=" fa fa-balance-scale"></i></a>
 <div class="modal fade" id="amount-to-be-decreased-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="{{ route('advanced.lg.payment.apply.amount.to.be.decreased',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$model->getSource() ]) }}" method="post">
                 @csrf
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Amount To Be Decreased To' ) . ' ' . $model->getTransactionName()  }}</h5>
                     <button type="button" class="close" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>


                 <div class="modal-body">
                     <div class="row mb-3">

                         <div class="col-md-6 mb-4">
                             <label>{{__('Bank Name')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getFinancialInstitutionBankName()  }}" type="text" class="form-control">
                             </div>
                         </div>

                         <div class="col-md-2 mb-4">
                             <label>{{__('LG Amount')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getLgAmount()  }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed">
                             </div>
                         </div>

                         <div class="col-md-2 mb-4">
                             <label>{{__('Date')}}</label>
                             <div class="kt-input-icon">
                                 <div class="input-group date">
                                     <input required type="text" name="date" value="{{ formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                     <div class="input-group-append">
                                         <span class="input-group-text">
                                             <i class="la la-calendar-check-o"></i>
                                         </span>
                                     </div>
                                 </div>
                             </div>
                         </div>

                         <div class="col-md-2 mb-4">
                             <label>{{__('Amount To Be Decreased')}} </label>
                             <div class="kt-input-icon">
                                 <input name="amount" value="{{  0  }}" type="text" class="form-control only-greater-than-zero-allowed">
                             </div>
                         </div>

                         <div class="col-md-12">
                             <div class="table-responsive">
                                 <table class="table table-bordered">
                                     <thead>
                                         <tr>
                                             <th>{{ __('#') }}</th>
                                             <th>{{ __('Date') }}</th>
                                             <th>{{ __('Amount') }}</th>
                                             <th>{{ __('Actions') }}</th>
                                         </tr>
                                     </thead>
                                     <tbody>
                                         @foreach($model->advancedPaymentHistories as $index=>$advancedPaymentHistory)
                                         <tr>
                                             <td> {{ ++$index }} </td>
                                             <td class="text-nowrap">{{$advancedPaymentHistory->getDateFormatted() }}</td>
                                             <td> {{ $advancedPaymentHistory->getAmountFormatted() }} </td>
                                             <td>
                                                 <a data-toggle="modal" data-target="#edit-advanced-payment-lg-{{ $advancedPaymentHistory->id }}" type="button" class="btn btn-secondary btn-outline-hover-primary btn-icon" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('edit.letter.of.guarantee.issuance',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$model->getSource()]) }}"><i class="fa fa-pen-alt"></i></a>
                                                 <a data-toggle="modal" data-target="#delete-advanced-payment-lg-{{ $advancedPaymentHistory->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
                                             </td>
                                         </tr>
                                         @endforeach
                                     </tbody>
                                 </table>
                             </div>
                         </div>

                     </div>
                 </div>


                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                     <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                 </div>

             </form>

           
         </div>
     </div>
 </div>
 
   @foreach ($model->advancedPaymentHistories as $index=>$advancedPaymentHistory)
             <div class="modal fade inner-modal-class" id="edit-advanced-payment-lg-{{ $advancedPaymentHistory->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                 <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                     <div class="modal-content">
                         <form action="{{ route('advanced.lg.payment.edit.amount.to.be.decreased',['company'=>$company->id,'lgAdvancedPaymentHistory'=>$advancedPaymentHistory->id,'source'=>$model->getSource() ]) }}" method="post">
                             @csrf
                             <div class="modal-header">
                                 <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Edit Amount To Be Decreased To' ) . ' ' . $model->getTransactionName()  }}</h5>
                                 <button data-dismiss="modal" type="button" class="close" aria-label="Close">
                                     <span aria-hidden="true">&times;</span>
                                 </button>
                             </div>


                             <div class="modal-body">
                                 <div class="row mb-3">

                                     <div class="col-md-6 mb-4">
                                         <label>{{__('Bank Name')}} </label>
                                         <div class="kt-input-icon">
                                             <input disabled value="{{  $model->getFinancialInstitutionBankName()  }}" type="text" class="form-control">
                                         </div>
                                     </div>

                                     <div class="col-md-2 mb-4">
                                         <label>{{__('LG Amount')}} </label>
                                         <div class="kt-input-icon">
                                             <input disabled value="{{  $model->getLgAmount()  }}" type="text" class="form-control only-greater-than-or-equal-zero-allowed">
                                         </div>
                                     </div>

                                     <div class="col-md-2 mb-4">
                                         <label>{{__('Date')}}</label>
                                         <div class="kt-input-icon">
                                             <div class="input-group date">
                                                 <input required type="text" name="decrease_date" value="{{ $advancedPaymentHistory ?formatDateForDatePicker($advancedPaymentHistory->getDate()) : null }}" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                                 <div class="input-group-append">
                                                     <span class="input-group-text">
                                                         <i class="la la-calendar-check-o"></i>
                                                     </span>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>

                                     <div class="col-md-2 mb-4">
                                         <label>{{__('Amount To Be Decreased')}} </label>
                                         <div class="kt-input-icon">
                                             <input name="amount_to_be_decreased" value="{{  $advancedPaymentHistory->getAmount()  }}" type="text" class="form-control only-greater-than-zero-allowed">
                                         </div>
                                     </div>



                                 </div>
                             </div>


                             <div class="modal-footer">
                                 <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                 <button type="submit" class="btn btn-primary submit-form-btn">{{ __('Confirm') }}</button>
                             </div>

                         </form>
                     </div>
                 </div>
             </div>
             <div class="modal fade inner-modal-class" id="delete-advanced-payment-lg-{{ $advancedPaymentHistory->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                 <div class="modal-dialog modal-dialog-centered" role="document">
                     <div class="modal-content">
                         <form action="" method="post">
                             @csrf
                             @method('delete')
                             <div class="modal-header">
                                 <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                 <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                     <span aria-hidden="true">&times;</span>
                                 </button>
                             </div>
                             <div class="modal-footer">
                                 <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>

                                 <a href="{{ route('delete.lg.advanced.payment',['company'=>$company->id,'lgAdvancedPaymentHistory'=>$advancedPaymentHistory->id]) }}" class="btn btn-danger">{{ __('Confirm Delete') }}</a>
                             </div>

                         </form>
                     </div>
                 </div>
             </div>
             @endforeach
			 
 @endif

 @elseif($model->isCancelled())

 <a data-toggle="modal" data-target="#back-to-running-modal-{{ $model->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Back To Running') }}" href="#"><i class="fa fa fa-undo"></i></a>

 <div class="modal fade" id="back-to-running-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="{{ route('back.to.running.letter.of.guarantee.issuance',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$model->getSource() ]) }}" method="post">
                 @csrf
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Change LG Status Back To Running Status ?') }}</h5>
                     <button type="button" class="close" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>


                 <div class="modal-body">
                     <div class="row mb-3">

                         <div class="col-md-6 mb-4">
                             <label>{{__('Bank Name')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  $model->getFinancialInstitutionBankName()  }}" type="text" class="form-control">
                             </div>
                         </div>

                         <div class="col-md-3 mb-4">
                             <label>{{__('LG Amount')}} </label>
                             <div class="kt-input-icon">
                                 <input disabled value="{{  number_format($model->getLgAmount())  }}" type="text" class="form-control text-center">
                             </div>
                         </div>
                     </div>
                 </div>


                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                     <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button>
                 </div>

             </form>
         </div>
     </div>
 </div>


 @endif
