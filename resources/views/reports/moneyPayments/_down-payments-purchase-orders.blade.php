  <div class="form-group row align-items-end">

      <div class="col-md-4">
          <label>{{__('PO Number')}} </label>
          <div class="kt-input-icon">
              <input name="purchases_orders_amounts[][purchases_order_name]" type="text" readonly class="form-control js-purchases-order-name">
              <input name="purchases_orders_amounts[][purchases_order_id]" type="hidden" readonly class="form-control js-purchases-order-number">
          </div>
      </div>

      <div class="col-md-2 closest-parent">
          <label>{{__('Amount')}} <span class="contract-currency"> </label>
          <div class="kt-input-icon">
              <input name="purchases_orders_amounts[][net_invoice_amount]" type="text" disabled class="form-control js-amount">
          </div>
      </div>


      <div class="col-md-2 closest-parent">
          <label>{{__('Paid Amount')}} 
		  <span class="contract-currency"> </label>
		  @include('star')</label>
          <div class="kt-input-icon">
              <input name="purchases_orders_amounts[][paid_amounts]" placeholder="{{ __('Paid Amount') }}" type="text" class="form-control js-paid-amount only-greater-than-or-equal-zero-allowed settlement-amount-class">
          </div>
      </div>


  </div>
