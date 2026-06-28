<div class="form-group row align-items-end">

    <div class="col-md-4">
        <label>{{__('SO Number')}} </label>
        <div class="kt-input-icon">
            <input name="sales_orders_amounts[0][sales_order_name]" type="text" readonly class="form-control js-sales-order-name">
            <input name="sales_orders_amounts[0][sales_order_id]" type="hidden" readonly class="form-control js-sales-order-number">
        </div>
    </div>

    <div class="col-md-2 closest-parent">
        <label>{{__('Amount')}}  <span class="contract-currency"></span> </label>
        <div class="kt-input-icon">
            <input name="sales_orders_amounts[0][net_invoice_amount]" type="text" disabled class="form-control js-amount">
        </div>
    </div>

    <div class="col-md-2 closest-parent">
        <label>{{__('Received Amount')}} <span class="contract-currency"> </label> @include('star')</label>
        <div class="kt-input-icon">
            <input name="sales_orders_amounts[0][received_amount]" placeholder="{{ __('Received Amount') }}" value="0" type="text" class="form-control js-received-amount only-greater-than-or-equal-zero-allowed settlement-amount-class">
        </div>
    </div>


</div>
