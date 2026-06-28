<div class="row " id="contract-row-id">
    <div class="col-12">
        <hr>
    </div>
    <div class="col-md-12">
        <h3 class="kt-portlet__head-title head-title text-primary">{{ __('Choose Contract For Down Payment') }}</h3>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="contracts"> {{ __('Contracts') }} </label>
            <select data-current-selected="{{ isset($model)  ? $model->getContractId() :0 }}" name="contract_id" id="contract-id" class="form-control ajax-get-sales-orders-for-contract">
            </select>
        </div>
    </div>
	@if(isset($warningMessage) && $warningMessage)
	@include('_warning__message')
	@endif
	<div class="col-md-12">
		

                    <div class="js-append-down-payment-to">
                        <div class="col-md-12 js-duplicate-node">

                        </div>
                    </div>

                    <div class="js-down-payment-template hidden">
                        <div class="col-md-12 js-duplicate-node">
                            <div class=" kt-margin-b-10 border-class">
                                @include('reports.moneyReceived._down-payments-sales-orders')
                            </div>
                        </div>
                    </div>


              
	</div>
</div>
