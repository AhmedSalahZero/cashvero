<div class="col-md-3 hidden hide-only-bond">
                                        <label> {{ __('Contract Reference') }}
                                            @include('star')
                                        </label>
                                        <select  data-contract-type="{{ isset($model) ? $model->getContractType() : '' }}"  js-update-purchase-orders-based-on-contract id="contract-id" data-current-selected="{{ isset($model) ?  $model->getContractId() : 0 }}" name="contract_id" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                        </select>
                                    </div>

                                    <div class="col-md-2 hidden hide-only-bond">

                                        <label> {{ __('Purchase Order') }}
                                            @include('star')
                                        </label>

                                        <select id="purchase-order-id" data-current-selected="{{ isset($model) ? $model->getPurchaseOrderId() : 0 }}" name="purchase_order_id" data-live-search="true" class="form-control kt-bootstrap-select select2-select kt_bootstrap_select">
                                          
                                        </select>
										<input placeholder="{{ __('New PO') }}" id="new-purchase-order-id" class="form-control " type="text" name="new_purchase_order_number" value="{{ isset($model) ? $model->getNewPoNumber(): '' }}" style="display:none">
                                    </div>

                                    <div class="col-md-2 hidden hide-only-bond">

                                        <x-form.date :label="__('Purchase Order Date')" :required="true" :model="$model??null" :name="'purchase_order_date'" :placeholder="__('Select Purchase Order Date')"></x-form.date>
                                    </div>
@push('js_end')
	 <script>
               $(function(){
				
				 $(document).on('change', '[js-update-contracts-based-on-customers]', function(e) {
        const customerId = $('select#customer_name').val()
        if (!customerId) {
            return;
        }
        $.ajax({
            url: "{{route('update.contracts.based.on.customer',['company'=>$company->id,'is_lc'=>1])}}"
            , data: {
                customerId
            , }
            , type: "GET"
            , success: function(res) {
                var currentSelectedId = $('select#contract-id').attr('data-current-selected')
				let contractType = $('select#contract-id').attr('data-contract-type');
                var contractsOptions = `<option value="-1" ${contractType == 'no-po' ? 'selected' : '' }>{{ __("New PO") }}</option> <option ${contractType == 'existing-po' ? 'selected' : '' } value="-2">{{ __("Existing PO") }}</option>`;
                for (var contractName in res.contracts) {
					var contractId = res.contracts[contractName].id ;
					var contractCurrency = res.contracts[contractName].currency ;
                     contractsOptions += `<option data-contract-currency="${contractCurrency}" ${currentSelectedId == contractId ? 'selected' : '' } value="${contractId}"> ${contractName}  </option> `;
                }
                $('select#contract-id').empty().append(contractsOptions).selectpicker("refresh");
                $('select#contract-id').trigger('change')
            }
        })
    })
    $('[js-update-contracts-based-on-customers]').trigger('change')
		
				 $(document).on('change', '[js-update-purchase-orders-based-on-contract]', function(e) {
                    let contractId = $('select#contract-id').val()
					$('select#purchase-order-id').empty().append('').selectpicker("refresh");
                    if (!contractId) {
                        contractId = -2;
                    }
					const currentNewPurchaseOrder = $('#new-purchase-order-id').val()
					const currencyName = $(this).find('option:selected').attr('data-contract-currency');
					
                    $.ajax({
                        url: "{{route('update.purchase.orders.based.on.contract',['company'=>$company->id])}}"
                        , data: {
                            contractId,
							currentNewPurchaseOrder
                        , }
                        , type: "GET"
                        , success: function(res) {
							$('select#purchase-order-id').parent().parent().find('.form-element-hidden').removeClass('form-element-hidden');
								$('input#new-purchase-order-id').hide();
							if(res.showTextInputForNewPO){
								$('select#purchase-order-id').addClass('form-element-hidden');
								$('input#new-purchase-order-id').show();
								return 
							}
                            var purchaseOrdersOptions = '';
                            var currentSelectedId = $('select#purchase-order-id').attr('data-current-selected')
                            for (var purchaseOrderId in res.purchase_orders) {
                                var contractName = res.purchase_orders[purchaseOrderId];
                                purchaseOrdersOptions += `<option ${currentSelectedId == purchaseOrderId ? 'selected' : '' } value="${purchaseOrderId}"> ${contractName}  </option> `;
                            }
					//		$('select.lc-currency').val(currencyName).trigger('change');
                            $('select#purchase-order-id').empty().append(purchaseOrdersOptions).selectpicker("refresh");
                        }
                    })
                })
			$('[js-update-purchase-orders-based-on-contract]').trigger('change')
			   })

            </script>
@endpush
