
@php
	
	$isTimeOfDeposit = $model instanceOf \App\Models\TimeOfDeposit ;
	
	$applyPeriodInterestRouteAction = $isTimeOfDeposit ? route('apply.period.interest.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id ]) : route('apply.period.interest.to.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'certificatesOfDeposit'=>$model->id ]) ;
	$viewPeriodicInterestRouteAction = $isTimeOfDeposit ? route('view.period.interest.to.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'timeOfDeposit'=>$model->id]) : route('view.period.interest.to.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'certificatesOfDeposit'=>$model->id]);
	$title = $isTimeOfDeposit?  __('Do You Want To Apply Periodic Interest To This Time Of Deposit ?') : __('Do You Want To Apply Periodic Interest To This Certificate Of Deposit ?') ;
@endphp
  <a
											
											 data-toggle="modal" data-target="#apply-periodic-interest-modal-{{ $model->id }}" type="button" class="btn 
											 
											 {{-- @if($model->isDueTodayOrGreater())
											 disabled 
											@endif  --}}
											 
											  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Apply Periodic Interest') }}" href="#"><i class="fa fa-bolt"></i></a>
											  
											 
                                            <div class="modal fade" id="apply-periodic-interest-modal-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" action="{{ $applyPeriodInterestRouteAction }}" method="post">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-left" id="exampleModalLongTitle">{{ $title }}</h5>
                                                                <button type="button" class="close" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-3">

                                                                    <div class="col-md-6 mb-4">
                                                                        <label>{{__('Interest Amount')}} </label>
                                                                        <div class="kt-input-icon">
                                                                            <input value="{{ 0 }}" type="text" name="periodic_interest_amount" class="form-control only-greater-than-or-equal-zero-allowed">
                                                                            {{-- <input value="{{ $model->isMatured() ? $model->getActualInterestAmount() : $model->getInterestAmount() }}" type="text" name="periodic_interest_amount" class="form-control only-greater-than-or-equal-zero-allowed"> --}}
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6 mb-4">
                                                                        <label>{{__('Deposit Date')}}</label>
                                                                        <div class="kt-input-icon">
                                                                            <div class="input-group date">
                                                                                <input max="" required type="text" name="periodic_interest_date" value="" class="form-control kt_datepicker_max_date_is_today" readonly placeholder="Select date" />
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
                                                                <a href="{{ $viewPeriodicInterestRouteAction }}" type="button" class="btn btn-primary" >{{ __('View Periodic Interests') }}</a>
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn bg-green text-white">{{ __('Confirm') }}</button>
                                                            </div>

                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
