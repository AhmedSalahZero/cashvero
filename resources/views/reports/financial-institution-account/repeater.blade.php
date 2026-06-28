                @php
                $isRepeater = !(isset($removeRepeater) && $removeRepeater) ;
                $type = 'create';
                @endphp

                <div style="flex-wrap:nowrap;" @if($isRepeater) data-repeater-item @endif class="form-group date-element-parent m-form__group row align-items-center 
                                         @if($isRepeater)
                                         repeater_item
                                         @endif 
				                         ">
                    <input type="hidden" class="form-control " @if($isRepeater) name="id" @else name="accounts[0][id]" @endif value="{{ isset($accountInterest) ? $accountInterest->getId() : 0 }}">












  					 <div class="col-md-2">
                        <x-calendar :classes="$index == 0 ? 'first-interest-rate-js':''" :value="$accountInterest->getStartDateForSelect()" :label="__('Interest Calculation Start Date')" :id="'start_date'" name="start_date"></x-calendar>
                    </div>




                    <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Interest Rate') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="interest_rate" @else name="accounts[0][interest_rate]" @endif type="text" class="form-control " value="{{ number_format(isset($accountInterest) ? $accountInterest->getInterestRate() : old('interest_rate',0)) }}">
                            </div>
                        </div>
                    </div>


                    <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Min Balance') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input type="text" class="form-control only-greater-than-or-equal-zero-allowed trigger-change-repeater" value="{{ number_format(isset($accountInterest) ? $accountInterest->getMinBalance() : old('min_balance',0)) }}">
                                <input type="hidden" value="{{ (isset($accountInterest) ? $accountInterest->getMinBalance() : old('min_balance',0)) }}" @if($isRepeater) name="min_balance" @else name="accounts[0][min_balance]" @endif>
                            </div>
                        </div>
                    </div>

                 


                    @if($isRepeater)
                    <div class="">
                        <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                        </i>
                    </div>
                    @endif


                </div>
