										 <td class="sub-text-bg text-center  {{ isset($excludeMaxWith) ? 'exclude-max-width' : '' }} text-nowrap ">{{ $invoice->getInvoiceDateFormatted() }}</td>
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getInvoiceNumber() }}</td>
										 @if(isset($showInvoiceCurrency) && $showInvoiceCurrency)
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getCurrency() }}</td>
										 @endif
										 <td class="sub-text-bg text-center  text-nowrap ">
										     {{ $invoice->getInvoiceAmountFormatted() }}
										     @if($currency != $company->getMainFunctionalCurrency())
										     <i data-toggle="modal" data-target="#net-invoice-amount-modal-{{ $invoice->id }}" class="flaticon2-information fs-15 kt-font-primary exclude-icon ml-2 cursor-pointer "></i>
										     <div class="modal fade " id="net-invoice-amount-modal-{{ $invoice->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
										         <div class="modal-dialog modal-lg" role="document">
										             <div class="modal-content">
										                 <div class="modal-header">
										                     <h5 class="modal-title text-left" id="exampleModalLabel">
										                         {{ __('Invoice Number #' . $invoice->getInvoiceNumber()  ) }} <br>
										                         {{ __('Dated') . ' ' . $invoice->getInvoiceDate() }}
										                     </h5>
										                     <button type="button" class="close" data-dismiss="modal" aria-label="Close">
										                         <span aria-hidden="true">&times;</span>
										                     </button>
										                 </div>
										                 <div class="modal-body">

										                     <table class="table table-bordered ">
										                         <thead>
										                             <th style="border-left:2px solid #ebedf2">{{ __('Item') }}</th>
										                             <th>{{ __('Value') }}</th>
										                         </thead>
										                         <tbody>
										                             <tr>
										                                 <td class="text-left">{{ __('Amount In Main Currency') }}</td>
										                                 <td>{{ number_format($invoice->getNetInvoiceInMainCurrencyAmount(),2) }}</td>
										                             </tr>
										                             <tr>
										                                 <td class="text-left">{{ __('Exchange Rate') }}</td>
										                                 <td>{{ number_format($invoice->getExchangeRate(),4) }}</td>
										                             </tr>

										                         </tbody>

										                     </table>
										                     <div class="modal-footer">
										                         <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
										                     </div>
										                 </div>
										             </div>
										         </div>
										     </div>
										     @endif


										 </td>

										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getTotalWithholdAmountFormatted() }}</td>
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getVatAmountFormatted() }}</td>
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getTotalDeductionFormatted() }}</td>
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getTotalCollectedOrPaidFormatted() }}</td>
										 <td class="sub-text-bg text-center  text-nowrap ">{{ $invoice->getDueDateFormatted() }}</td>
										 <td class="sub-text-bg text-center text-nowrap">{{ $invoice->getNetBalanceFormatted() }}</td>
										 <td class="sub-text-bg text-center text-wrap">{{ $invoice->getStatusFormatted() }}</td>
										 <td class="sub-text-bg  text-center">
										     {{ $invoice->getAging() }}
										 </td>
