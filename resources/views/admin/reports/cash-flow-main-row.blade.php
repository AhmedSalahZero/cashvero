
					 <tr class=" @if($customerName == __('Total Cash Inflow') || $customerName == __('Total Cash Outflow') ||  $customerName == __('Total Cash')) bg-lighter @else  @endif  parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   " data-model-id="{{ $rowIndex }}">
                                    <td class="red reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize main-tr is-close"> @if($hasSubRows) + @endif  </td>
                                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ $customerName }}</td>
                                    <td class="  sub-numeric-bg text-center editable-date"> 
										{{-- @if($customerName == __('Cancelled LGs Cash Cover'))
										<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
										@endif  --}}
										@if($customerName == __('Customers Past Due Invoices'))
										<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'CustomerInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueCustomerInvoices[$currentCurrencyName]??[]" :id="'test-modal-id'"></x-modal.due-invoices>
										@endif 
										
										
										
											@if($customerName == 'Suppliers Past Due Invoices')
												<button   class="btn btn-sm btn-danger text-white js-show-customer-due-invoices-modal">{{ __('View') }}</button>
                                                <x-modal.due-invoices :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport) ? $cashflowReport:null" :report-interval="$reportInterval" :currentInvoiceType="'SupplierInvoice'" :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueSupplierInvoices" :id="'test-modal-id'"></x-modal.due-invoices>
										
											@endif 
												@if($customerName == 'Loan Past Due Installments')
												<button   class="btn btn-sm btn-danger text-white js-show-loan-past-due-installment-modal">{{ __('View') }}</button>
                                                <x-modal.loan-installment :contractCode="$contractCode" :currencyName="$currencyName" :cashflowReport="isset($cashflowReport)?$cashflowReport:null" :report-interval="$reportInterval"  :dates="$dates" :weeks="$weeks" :pastDueCustomerInvoices="$pastDueInstallments" :id="'test-modal-id'"></x-modal.loan-installment>
										
											@endif 
											
									
									 </td>
									 @php
										//	$currentMainRowTotal = $finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['total']['total_of_total']??0;
											$currentMainRowTotal = 0;
									 @endphp
                                    @foreach($weeks as $weekAndYear => $week)
                                    @php
								
									$year = explode('-',$weekAndYear)[1];
									
                                    $currentValue = 0 ;
								
									if(isset($finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear]))
									{
										$currentValue = $finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['weeks'][$weekAndYear];
										$currentMainRowTotal += $currentValue;
									}
									if(isset($isTotalRow) && isset($finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['total'][$weekAndYear])){
										$currentValue = $finalResult[$currentCurrencyName][$mainReportKey][$parentKeyName]['total'][$weekAndYear];
										$currentMainRowTotal += $currentValue;
									}
									if($customerName == __('Customers Past Due Invoices') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($customerDueInvoices[$currentCurrencyName] ?? [], function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
										
									}
									if($customerName == __('Suppliers Past Due Invoices') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
										$filtered = array_filter($supplierDueInvoices, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
									}
									if($customerName == __('Loan Past Due Installments') )
									{
										$startDate = $dates[$weekAndYear]['start_date'] ;
											$filtered = array_filter($pastDueLoanInstallments, function ($item) use ($startDate) {
    												return $item['week_start_date'] == $startDate;
											});
										$currentRow = reset($filtered) ?: null ;
										$currentValue =$currentRow ?  $currentRow['amount'] : 0;
										$currentMainRowTotal += $currentValue;
									}
									 if($customerName == 'Accumulated Net Cash (+/-)'){
									}
									$allMainRowsTotals[$customerName][$weekAndYear] = $currentMainRowTotal ;
										
                                    @endphp
									
                                    <td  data-id="{{ $currentValue }}" class="  sub-numeric-bg text-center editable-date">{{ number_format($currentValue,0) }}</td>
                                    @endforeach
									
                                   
                                    <td class="  sub-numeric-bg text-center editable-date">
									{{ number_format(  $currentMainRowTotal ) }}
								
									 </td>

                                </tr>
								
				
					
					