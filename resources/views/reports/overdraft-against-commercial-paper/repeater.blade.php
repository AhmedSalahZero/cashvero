                @php
                $isRepeater = !(isset($removeRepeater) && $removeRepeater) ;
                $type = 'create';
                @endphp


                <div style="flex-wrap:nowrap;" @if($isRepeater) data-repeater-item @endif class="form-group date-element-parent m-form__group row align-items-center 
                                         @if($isRepeater)
                                         repeater_item
                                         @endif 
				                         ">
                    <input type="hidden" class="form-control " @if($isRepeater) name="id" @else name="infos[0][id]" @endif value="{{ isset($infos) ? $infos->getId() : 0 }}">
                    <input type="hidden" class="form-control " @if($isRepeater) name="id" @else name="infos[0][company_id]" @endif value="{{ $company->id }}">
		


				
                   



                   

                    <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Commercial Papers Due Within') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="for_commercial_papers_due_within_days" @else name="infos[0][for_commercial_papers_due_within_days]" @endif type="text" class="form-control 
								
								" value="{{ (isset($infos) ? $infos->for_commercial_papers_due_within_days : old('for_commercial_papers_due_within_days',0)) }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <label class="form-label font-weight-bold">{{ __('Lending Rate (%) *') }}
                        </label>
                        <div class="kt-input-icon">
                            <div class="input-group">
                                <input @if($isRepeater) name="lending_rate" @else name="infos[0][lending_rate]" @endif type="text" class="form-control only-percentage-allowed
						
								" value="{{ (isset($infos) ? $infos->lending_rate : old('lending_rate',0)) }}">
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
