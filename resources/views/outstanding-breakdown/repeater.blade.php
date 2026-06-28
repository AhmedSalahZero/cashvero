                @php
                $isRepeater = !(isset($removeRepeater) && $removeRepeater) ;
                $type = 'create';
                @endphp

                <div style="flex-wrap:nowrap;" @if($isRepeater) data-repeater-item @endif class="form-group date-element-parent m-form__group row align-items-center 
                                         @if($isRepeater)
                                         repeater_item
                                         @endif 
				                         ">
                    <input type="hidden" class="form-control " @if($isRepeater) name="id" @else name="outstandingBreakdowns[0][id]" @endif value="{{ isset($outstandingBreakdown) ? $outstandingBreakdown->getId() : 0 }}">
                    <div class="col-3">
                        <label class="form-label font-weight-bold">{{ __('Amount') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="amount" @else name="outstandingBreakdowns[0][amount]" @endif type="text" class="form-control " value="{{ number_format(isset($outstandingBreakdown) ? $outstandingBreakdown->getAmount() : old('amount',0)) }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <x-calendar :value="isset($outstandingBreakdown)?$outstandingBreakdown->getSettlementDateForSelect():''" :label="__('Settlement Date')" :id="'settlement_date'" name="settlement_date"></x-calendar>
                    </div>






                    @if($isRepeater)
                    <div class="">
                        <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                        </i>
                    </div>
                    @endif


                </div>
