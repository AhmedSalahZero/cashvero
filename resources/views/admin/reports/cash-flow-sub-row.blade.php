<tr class="edit-info-row add-sub maintable-1-row-class{{ $rowIndex }} is-sub-row d-none">
    <td class=" reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize is-close "></td>
    <td class="sub-text-bg max-w-classes-name is-name-cell ">
        <div class="ml-son">
            {{ $currentSubRowKeyName }}
        </div>
        {{-- {{ $result[$mainReportKey][$parentKeyName][$currentSubRowKeyName]['sub_key'] ?? '-' }} --}}

    </td>
    <td class="sub-text-bg text-center">


    </td>
    {{-- <td class="sub-text-bg max-w-classes-name editable editable-text is-name-cell ">{{$currentSubRowKeyName }}</td> --}}
    @php
    $currentSubTotal = 0;
    @endphp
    @foreach($weeks as $weekAndYear => $week)
    @php
    $currentValue = $result[$mainReportKey][$parentKeyName][$currentSubRowKeyName]['weeks'][$weekAndYear] ?? 0;
    $currentSubTotal+=$currentValue;

    if($currentSubRowKeyName == 'Customers Past Due Invoices' )
    {
    $startDate = $dates[$weekAndYear]['start_date'] ;
    $filtered = array_filter($customerDueInvoices, function ($item) use ($startDate) {
    return $item['week_start_date'] == $startDate;
    });
    $currentRow = reset($filtered) ?: null ;
    $currentValue =$currentRow ? $currentRow['amount'] : 0;
    $currentSubTotal+=$currentValue;
    }
    if($currentSubRowKeyName == 'Suppliers Past Due Invoices' )
    {
    $startDate = $dates[$weekAndYear]['start_date'] ;
    $filtered = array_filter($supplierDueInvoices, function ($item) use ($startDate) {
    return $item['week_start_date'] == $startDate;
    });
    $currentRow = reset($filtered) ?: null ;
    $currentValue =$currentRow ? $currentRow['amount'] : 0;
    $currentSubTotal+=$currentValue;

    }
    if($currentSubRowKeyName == 'Loan Past Due Installments' )
    {
    $startDate = $dates[$weekAndYear]['start_date'] ;
    $filtered = array_filter($pastDueLoanInstallments, function ($item) use ($startDate) {
    return $item['week_start_date'] == $startDate;
    });
    $currentRow = reset($filtered) ?: null ;
    $currentValue =$currentRow ? $currentRow['amount'] : 0;
    $currentSubTotal+=$currentValue;
    }
    @endphp
    <td class="  sub-numeric-bg text-center editable-date">{{ number_format($currentValue) }}

        @if($customerName == __('Cancelled LGs Cash Cover') && $currentValue)
        @php
        $currentId = convertStringToClass($currentSubRowKeyName.$weekAndYear);
        @endphp
        <i data-toggle="modal" data-target="#lg-breakdown-modal-{{ $currentId }}" class="flaticon2-information fs-15 kt-font-primary exclude-icon ml-2 cursor-pointer "></i>



        <div class="modal fade " id="lg-breakdown-modal-{{ $currentId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-left" id="exampleModalLabel">
                            {{ __('Breakdown') }} [{{ $currentSubRowKeyName }}] [{{ $weekAndYear }}]
                            {{-- {{ __('Invoice Number #' . $invoice->getInvoiceNumber()  ) }} <br> --}}
                            {{-- {{ __('Dated') . ' ' . $invoice->getInvoiceDate() }} --}}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                        <table class="table table-bordered active-header">
                            <thead>
                                <th class="th-main-color" style="border-left:2px solid #ebedf2">{{ __('Name') }}</th>
                                <th class="th-main-color">{{ __('LG Code') }}</th>
                                <th class="th-main-color">{{ __('Amount') }}</th>
                            </thead>
                            <tbody>
                                @php
                                $currentModalTotal = 0 ;
                                @endphp
                                @foreach($letterOfGuaranteeModelData[$currentSubRowKeyName]['weeks'][$weekAndYear]??[] as $currentModalArr)

                                <tr>

                                    <td>{{ $currentModalArr['name'] }}</td>
                                    <td>{{ $currentModalArr['lg_code'] }}</td>
                                    <td>{{ number_format($currentModalArr['amount'],2) }}</td>
                                    @php
                                    $currentModalTotal+= $currentModalArr['amount'];
                                    @endphp
                                </tr>


                                @endforeach


                                <tr>

                                    <td>{{ __('Total') }}</td>
                                    <td>--</td>
                                    <td>{{ number_format($currentModalTotal,2) }}</td>

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
    @endforeach
    @php
    // $currentSubTotal = $result[$mainReportKey][$parentKeyName][$currentSubRowKeyName]['total'] ?? 0 ;
    // $currentSubTotal = is_array($currentSubTotal) ? 0 : $currentSubTotal;
    // $currentSubTotal = -8;
    @endphp
    <td class="  sub-numeric-bg text-center editable-date">{{ number_format($currentSubTotal) }}</td>


</tr>
