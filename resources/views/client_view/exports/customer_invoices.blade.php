<table>
    <thead>
    <tr>

        <th>{{__('customerName')}}</th>
        <th>{{__('businessSector')}}</th>
        <th>{{__('invoiceNumber')}}</th>
        <th>{{__('invoiceDate')}}</th>
        <th>{{__('dueWithin')}}</th>
        <th>{{__('invoiceDueDate')}}</th>
        <th>{{__('contractCode')}}</th>
        <th>{{__('contractDate')}}</th>
        <th>{{__('purchaseOrderNumber')}}</th>
        <th>{{__('purchaseOrderDate')}}</th>
        <th>{{__('salesOrderNumber')}}</th>
        <th>{{__('salesOrderDate')}}</th>
        <th>{{__('salesPersonName')}}</th>
        <th>{{__('salesPersonRate')}}</th>
        <th>{{__('invoiceAmount')}}</th>
        <th>{{__('currency')}}</th>
        <th>{{__('advancePaymentAmount')}}</th>
        <th>{{__('vatAmount')}}</th>
        <th>{{__('deductionOne')}}</th>
        <th>{{__('deductionAmountOne')}}</th>
        <th>{{__('deductionTwo')}}</th>
        <th>{{__('deductionAmountTwo')}}</th>
        <th>{{__('deductionThree')}}</th>
        <th>{{__('deductionAmountThree')}}</th>
        <th>{{__('deductionFour')}}</th>
        <th>{{__('deductionAmountFour')}}</th>
        <th>{{__('deductionFive')}}</th>
        <th>{{__('deductionAmountFive')}}</th>
        <th>{{__('deductionSix')}}</th>
        <th>{{__('deductionAmountSix')}}</th>
        <th>{{__('totalDeduction')}}</th>
        <th>{{__('invoiceNetAmount')}}</th>
        <th>{{__('invoicesDueNotificationDays')}}</th>
        <th>{{__('pastDueInvoicesNotificationDays')}}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoices as $invoice)
        <tr>
            <td>  {{$invoice->customer_name}} </td>
            <td>  {{$invoice->business_sector}} </td>
            <td>  {{$invoice->invoice_number}} </td>
            <td>  {{$invoice->invoice_date}} </td>
            <td>  {{$invoice->due_within}} </td>
            <td>  {{$invoice->invoice_due_date}} </td>
            <td>  {{$invoice->contract_code}} </td>
            <td>  {{$invoice->contract_date}} </td>
            <td>  {{$invoice->purchase_order_number}} </td>
            <td>  {{$invoice->purchase_order_date}} </td>
            <td>  {{$invoice->sales_order_number}} </td>
            <td>  {{$invoice->sales_order_date}} </td>
            <td>  {{$invoice->sales_person_name}} </td>
            <td>  {{$invoice->sales_person_rate}} </td>
            <td>  {{$invoice->invoice_amount}} </td>
            <td>  {{$invoice->currency}} </td>
            <td>  {{$invoice->advance_payment_amount}} </td>
            <td>  {{$invoice->vat_amount}} </td>
            <td>  {{$invoice->deductionName('deduction_id_one')}} </td>
            <td>  {{$invoice->deduction_amount_one}} </td>
            <td>  {{$invoice->deductionName('deduction_id_two')}} </td>
            <td>  {{$invoice->deduction_amount_two}} </td>
            <td>  {{$invoice->deductionName('deduction_id_three')}} </td>
            <td>  {{$invoice->deduction_amount_three}} </td>
            <td>  {{$invoice->deductionName('deduction_id_four')}} </td>
            <td>  {{$invoice->deduction_amount_four}} </td>
            <td>  {{$invoice->deductionName('deduction_id_five')}} </td>
            <td>  {{$invoice->deduction_amount_five}} </td>
            <td>  {{$invoice->deductionName('deduction_id_six')}} </td>
            <td>  {{$invoice->deduction_amount_six}} </td>
            <td>  {{$invoice->total_deduction}} </td>
            <td>  {{$invoice->invoice_net_amount}} </td>
            <td>  {{$invoice->invoices_due_notification_days}} </td>
            <td>  {{$invoice->past_due_invoices_notification_days}} </td>
        </tr>
    @endforeach
    </tbody>
</table>
