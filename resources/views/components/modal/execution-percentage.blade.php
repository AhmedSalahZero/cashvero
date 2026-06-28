@props([
'id',
'tableId',
'isRepeater',
'subModel',
'salesOrder'=> $subModel,
'popupTitle'=>__('Customer Collection')
])

<div class="modal fade modal-item-js" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">{{ $popupTitle }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="customize-elements">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="text-center">{{ __('Execution Percentage %') }}</th>
                                <th class="text-center">{{ __('Amount') }}</th>
                                <th class="text-center">{{ __('Start Date') }}</th>
                                {{-- <th class="text-center">{{ __('Execution Months') }}</th> --}}
                                <th class="text-center">{{ __('End Date') }}</th>
                                <th class="text-center">{{ __('Collection Days') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($i = 1 ; $i <= 5 ; $i++) <tr>
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input name="execution_percentage_{{ $i }}" type="numeric" step="0.1" class="form-control execution-percentage-js must-not-exceed-100" data-parent-query=".customize-elements" value="{{ isset($salesOrder) ? $salesOrder->getExecutionPercentage($i) : old('salesOrders.execution_percentage_'.$i,0) }}">
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input readonly type="text" class="form-control amount-js" value="{{ isset($salesOrder) ? $salesOrder->getActualAmount($i) : old('salesOrders.execution_percentage_'.$i,0) }}">
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <x-form.date :type="'text'" :classes="'datepicker-input recalc-end-date-2 start-date-2 recheck-start-date-rule-js'" :default-value="formatDateForDatePicker(isset($salesOrder)  ? $salesOrder->getStartDate($i) : now())" :model="$salesOrder??null" :label="''" :type="'text'" :placeholder="__('')" :name="'start_date_'.$i" :required="true"></x-form.date>
                                </td>

                                {{-- <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input name="execution_days_{{ $i }}" type="numeric" step="1" class="form-control duration-2 recalc-end-date-2" value="{{ isset($salesOrder) ? $salesOrder->getExecutionDays($i) : old('salesOrders.execution_days_'.$i,0) }}">
                                        </div>
                                    </div>
                                </td> --}}
                                <td>
                                    <x-form.date :type="'text'" :classes="'datepicker-input recheck-start-date-rule-js  end-date-2'" :default-value="formatDateForDatePicker(isset($salesOrder)  ? $salesOrder->getEndDate($i) : now())" :model="$salesOrder??null" :label="''" :type="'text'" :placeholder="__('')" :name="'end_date_'.$i" :required="true"></x-form.date>
                                </td>
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input name="collection_days_{{ $i }}" type="numeric" step="1" class="form-control " value="{{ isset($salesOrder) ? $salesOrder->getCollectionDays($i) : old('salesOrders.collection_days_'.$i,0) }}">
                                        </div>
                                    </div>
                                </td>




                                </tr>
                                @endfor
                                {{-- <tr style="border-top:1px solid gray;padding-top:5px;text-align:center">
                                    <td class="td-for-total-payment-rate">
                                        0
                                    </td>
                                    <td>-</td>
                                </tr> --}}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
