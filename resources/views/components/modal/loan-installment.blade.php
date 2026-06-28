@props([
'id',
'pastDueCustomerInvoices',
'weeks',
'dates',
'reportInterval',
'cashflowReport'=>null,
'currencyName',
'contractCode',
'flowReportId'=>null
])
@php

	
	$cashflowReportId = isset($cashflowReport) ? $cashflowReport->id:0;
	$cashflowReportId = isset($flowReportId) ? $flowReportId :  $cashflowReportId;
	
	$isContract = $contractCode ? 1 : 0 ;
	
@endphp
<div class="modal fade modal-item-js" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-90 modal-dialog-centered" role="document">
        <form action="{{ route('adjust.loan.past.dues.installments',['company'=>$company->id]) }}" class="modal-content" method="post">
		<input type="hidden" name="cashFlowReportId" value="{{ $cashflowReportId }}">
								
		@csrf
            <div class="modal-header">
                <h5 class="modal-title"  id="exampleModalLongTitle">{{ __('Loan Past Due Installment') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="customize-elements">
                    <table class="table  kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                        <thead>
                            <tr class="header-tr">
                                <th class=" view-table-th  text-white header-th  align-middle text-center"> {{ __('Name') }} </th>
                                <th class=" view-table-th  text-white  align-middle text-center"> {!! __('Remaining Installments') !!} </th>
                                <th class=" view-table-th   text-white header-th  align-middle text-center">{{ __('Due Date') }}</th>
                                <th class=" view-table-th   text-white header-th  align-middle text-center"> {!! __('Collection <br> Percentage') !!} </th>
                                <th class="view-table-th   text-white header-th  align-middle text-center"> {!! __('Collection <br> Date') !!} </th>
                            </tr>
                        </thead>
                        <tbody>
						
							@php
								$totalNetBalance = 0 ;
								$allIds = array_column($pastDueCustomerInvoices,'id') ;
								$dueInvoiceRow = \DB::table('weekly_cashflow_custom_past_due_schedules')->where('is_contract',$isContract)->where('cashflow_report_id',$cashflowReportId)->where('company_id',$company->id)->whereIn('loan_schedule_id',$allIds)->get();
								
							@endphp
                            @foreach($pastDueCustomerInvoices as $pastDueCustomerInvoice)
							@php
								$row = $dueInvoiceRow->where('loan_schedule_id',$pastDueCustomerInvoice['id'])->first();
								
							@endphp
						
                            <input type="hidden" name="loan_schedule_id[]" value="{{ $pastDueCustomerInvoice['id'] }}">
							<input type="hidden" name="invoice_amount[{{ $pastDueCustomerInvoice['id'] }}]"  value="{{ $pastDueCustomerInvoice['remaining'] }}">
							<input type="hidden" name="currency_name"  value="{{ $currencyName }}">
							<input type="hidden" name="cashflow_report_id"  value="{{ $cashflowReportId }}">
							<input type="hidden" name="is_contract"  value="{{ $contractCode ? 1 : 0 }}">
							@if($contractCode)
							<input type="hidden" name="contract_code"  value="{{ $contractCode }}">
							@endif
                            <tr>
                                <td>
								
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="numeric" step="0.1" class="form-control" value="{{ \App\Models\MediumTermLoan::find($pastDueCustomerInvoice['medium_term_loan_id'])->getName() }}">
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center" value="{{ number_format($pastDueCustomerInvoice['remaining']) }}">
											@php
												$totalNetBalance +=$pastDueCustomerInvoice['remaining']; 
											@endphp
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center" value="{{ $pastDueCustomerInvoice['date'] }}">
                                        </div>
                                    </div>
                                </td>
								            <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input type="text" name="percentage[{{ $pastDueCustomerInvoice['id'] }}]" class="form-control text-center only-percentage-allowed" value="{{ $row ? $row->percentage : 100 }}">
                                        </div>
                                    </div>
                                </td>
								

                                <td>
                                    <select class="form-control" name="week_start_date[{{ $pastDueCustomerInvoice['id'] }}]">
									
                                      @foreach($weeks as $weekDate => $weekNo )
									  @php
										$startDate = $dates[$weekDate]['start_date'] ;
									 	$title = __('Week ') . ' ' . $weekNo  . ' ( ' . $dates[$weekDate]['start_date'] . ' - ' . $dates[$weekDate]['end_date'] . ' )';
										if($reportInterval == 'daily'){
											$title  = $dates[$weekDate]['start_date'] ;
										}
										elseif($reportInterval == 'monthly'){
											$title = $dates[$weekDate]['end_date'] ;
										}
									  @endphp
									
									  <option @if($row && $row->week_start_date == $startDate ) selected @endif  class="text-center" value="{{ $startDate }}"> {{ $title }}   </option>
									  @endforeach 
                                    </select>
                                </td>

                            </tr>
                         @endforeach
						 <tr>
						 	<td>
							
							</td>
							
							<td>
								{{ __('Total Past Dues') }}
							</td>
							<td>
							
							{{ number_format($totalNetBalance) }}
							</td>
							<td>
							</td>
							
							<td>
							
							</td>
							<td>
							
							</td>
						 </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary submit-form-btn"
				 {{-- data-dismiss="modal" --}}
				 
				 >{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
