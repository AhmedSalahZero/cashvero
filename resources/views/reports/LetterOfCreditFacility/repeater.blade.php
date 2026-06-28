                @php
                $isRepeater = !(isset($removeRepeater) && $removeRepeater) ;
                $type = 'create';
                @endphp


                <div style="flex-wrap:nowrap;" @if($isRepeater) data-repeater-item @endif class="form-group date-element-parent m-form__group row align-items-center 
                                         @if($isRepeater)
                                         repeater_item
                                         @endif 
				                         ">
                    <input type="hidden" class="form-control " @if($isRepeater) name="id" @else name="termAndConditions[0][id]" @endif value="{{ isset($termAndCondition) ? $termAndCondition->id : 0 }}">



				
                    <div class="col-lc-2">
                        <label>{{__('Select LC Type')}} @include('star')</label>
                        <div class="input-group">
                            <select @if($isRepeater) name="lc_type" @else name="termAndConditions[0][lc_type]" @endif class="form-control repeater-select">
                                <option selected>{{__('Select')}}</option>
                                @foreach(getLcTypes() as $name => $nameFormatted )
                                <option value="{{ $name  }}" @if(isset($termAndCondition) && $termAndCondition->getLcType() == $name ) selected @endif > {{ $nameFormatted }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>



                    <div class="col-2">
                        <label class="form-label font-weight-bold ">{{ __('Outstanding Balance') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input placeholder="{{ __('Outstanding Balance') }}" type="text" class="form-control only-greater-than-zero-allowed" @if($isRepeater) name="outstanding_balance" @else name="termAndConditions[0][outstanding_balance]" @endif value="{{ isset($termAndCondition) ? $termAndCondition->getOutstandingBalance() : old('outstanding_balance') }}">
                            </div>
                        </div>
                    </div>
					
						  <div class="col-md-2">
                                <x-form.date :label="__('Outstanding Date')" :required="true" :model="$termAndCondition??null" :name="'outstanding_date'" :placeholder="__('Select Outstanding Date')"></x-form.date>
                            </div>



                
                    <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Cash Cover Rate (%) *') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="cash_cover_rate" @else name="termAndConditions[0][cash_cover_rate]" @endif type="text" class="form-control only-percentage-allowed
								{{-- trigger-change-repeater --}}
								" value="{{ (isset($termAndCondition) ? $termAndCondition->cash_cover_rate : old('cash_cover_rate',0)) }}">
                            </div>
                        </div>
                    </div>
					
					  <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Commission Rate (%) *') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="commission_rate" @else name="termAndConditions[0][commission_rate]" @endif type="text" class="form-control only-percentage-allowed
								{{-- trigger-change-repeater --}}
								" value="{{ (isset($termAndCondition) ? $termAndCondition->commission_rate : old('commission_rate',0)) }}">
                            </div>
                        </div>
                    </div>



					  <div class="col-lc-2">
                        <label>{{__('Commission Interval')}} @include('star')</label>
                        <div class="input-group">
                            <select @if($isRepeater) name="commission_interval" @else name="termAndConditions[0][commission_interval]" @endif class="form-control repeater-select">
                                <option selected>{{__('Select')}}</option>
                                @foreach(getCommissionInterval() as $name => $nameFormatted )
                                <option value="{{ $name  }}" @if(isset($termAndCondition) && $termAndCondition->getCommissionInterval() == $name ) selected @endif > {{ $nameFormatted }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

















                    @if($isRepeater)
                    <div class="">
                        <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                        </i>
                    </div>
                    @endif


                </div>
