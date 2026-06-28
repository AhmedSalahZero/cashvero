@php
use Carbon\Carbon;
@endphp
<style>
.three-dots-parent {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 0 !important;
    margin-top: 10px;
}
</style>
@php
	$isContract = $contractCode?1:0;
@endphp  
<div class="tab-pane " id="{{ $currentTabId }}" role="tabpanel">
<form action="{{ route('save.projection',['company'=>$company->id]) }}" method="post">
	@csrf
    {{-- <div class="kt-portlet">

        <div class="kt-portlet__body"> --}}

            <div class="form-group row justify-content-center" style="overflow:scroll">
                @php
                $index = 0 ;
                @endphp


                {{-- start of fixed monthly repeating amount --}}
                @php
                $tableId = $currentTabId.'id';
        



                @endphp
                <input type="hidden" name="tableIds[]" value="{{ $tableId }}">
                <input type="hidden" name="dates[]" value="{{ json_encode($dates) }}">
                <input type="hidden" name="type" value="{{ $projectionType }}">
                <input type="hidden" name="cashFlowReportId" value="{{ isset($cashflowReport) ? $cashflowReport->id:0 }}">
                <input type="hidden" name="is_contract" value="{{ $isContract }}">
                <x-tables.repeater-table  :initEmpty="false" :firstElementDeletable="true" :repeater-with-select2="false" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                    <x-slot name="ths">

                        <tr class="header-tr ">
                            <th 
							{{-- rowspan="{{ $noRowHeaders }}" --}}
							 class="view-table-th expand-all is-open-parent header-th editable-date max-w-classes-expand align-middle text-center trigger-child-row-1">
                                {{ __('Actions' ) }}
                                {{-- <span>+</span> --}}
                            </th>
                            <th 
							{{-- rowspan="{{ $noRowHeaders }}" --}}
							 class="view-table-th header-th max-w-classes-name align-middle text-center">
							
                                {{ __('Item') }}
                            </th>
                            
                            @if($reportInterval == 'weekly')
                            @foreach($weeks as $weekAndYear => $week)
                                
                            @php
                            $year = explode('-',$weekAndYear)[1];
                            @endphp
                            <th class="view-table-th bg-lighter header-th max-w-weeks align-middle text-center">
                                <span class="d-block">{{ __('Week ' .  $week ) }}</span>
                                <span class="d-block">{{ '[ ' . $year . ' ]' }}</span>
                            </th>
                            @endforeach
                            @elseif($reportInterval == 'monthly')
                            @foreach($months as $month)
                            <th class="view-table-th  header-th max-w-weeks align-middle text-center">
                                @if($loop->first || $loop->last)
                                <span class="d-block">{{ Carbon::make($month)->format('d-m-Y') }}</span>
                                @else
                                <span class="d-block">{{ Carbon::make($month)->format('m-Y') }}</span>
                                @endif
                            </th>
                            @endforeach


                            @elseif($reportInterval == 'daily')

                            @foreach($days as $day)
                            <th class="view-table-th  header-th max-w-weeks align-middle text-center">
                                <span class="d-block">{{ Carbon::make($day)->format('d-m-Y') }}</span>
                            </th>
                            @endforeach

                            @endif
                            {{-- <th rowspan="{{ $noRowHeaders }}" class="view-table-th editable-date align-middle text-center header-th max-w-grand-total">
                            {{ __('Total') }}
                            </th> --}}

                        </tr>
                    </x-slot>
                    <x-slot name="trs">
                        @php
						$model = isset($cashflowReport) ? $cashflowReport : $company; 
                        $rows = count($model->cashProjects->where('is_contract',$isContract)->where('type',$projectionType)) ? $model->cashProjects->where('is_contract',$isContract)->where('type',$projectionType) :[-1] ;
                        @endphp
                        @foreach( count($rows) ? $rows : [-1] as $currentRow)
                        @php
                        if( !($currentRow instanceof \App\Models\CashProjection) ){
                        unset($currentRow);
                        }
                        @endphp
						
                        <tr @if($isRepeater) data-repeater-item @endif>
					
					
                            <td class="text-center">
                                <div class="">
                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                    </i>
                                </div>
                            </td>
                            <td>
                                <input type="hidden" name="id" value="{{ isset($currentRow) ? $currentRow->id : 0 }}">
                                <input type="hidden" name="type" value="out">
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="name" type="text" class="form-control " value="{{ (isset($currentRow) ? $currentRow->name : old('name','')) }}">
                                    </div>
                                </div>
                            </td>
							@php
								 $columnIndex = 0 ;
							@endphp
                            @foreach($weeks as $weekAndYear => $week)
											 <td>
											 
											 <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :multiple="true" :removeCurrency="true" :currentVal="isset($currentRow) ? $currentRow->amounts[$weekAndYear]??0 : 0" :classes="'only-greater-than-or-equal-zero-allowed'" :is-percentage="false" :name="'amounts'" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>

                                        </div>
										
                                                                                   {{-- <x-repeat-right-dot-inputs :removeCurrency="true" number-format-decimals="0" :mark="''" :remove-three-dots="false" :currentVal="isset($currentRow) ? $currentRow->amounts[$weekAndYear]??0 : 0" :classes="'only-greater-than-or-equal-zero-allowed'" :is-percentage="false" :name="'amounts'" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs> --}}

                                    </td>
									
                            {{-- <td>
							
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input name="amounts" multiple type="text" class="form-control " value="{{ number_format() }}">
                                    </div>
                                </div>
                            </td> --}}
							 @php
                                    $columnIndex++;
                                    @endphp
									
                            @endforeach

                        </tr>
                        @endforeach

                    </x-slot>




                </x-tables.repeater-table>


                {{-- end of fixed monthly repeating amount --}}



            </div>


        {{-- </div>
    </div> --}}
  <x-submitting />
</form>
</div>
