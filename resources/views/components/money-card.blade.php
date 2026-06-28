@props([
'currencyName','total','color','customerName','showReport','invoiceType',
'mainFunctionalCurrency',
'downPayment'
])
@once

<style>
.black-card-title-css{
	color: white !important;
}
    .report-flex {
        display: flex;
        gap: 5px;
        flex-direction: column;
    }


</style>
@endonce
<div class="col-md-6 col-lg-4 col-xl-4">
    <!--begin::Total Profit-->
    <div class="kt-widget24 text-center pb-0">
        <div class="kt-widget24__details">
            <div class="kt-widget24__info">
                <h4 class="kt-widget24__title font-size text-nowrap black-card-title-css">
					@if($currencyName == 'main_currency')
                    {{ __('Balance In Main Currency ' . $mainFunctionalCurrency ) }}
					@else
                    {{ __('Balance In ' . $currencyName ) }}
					@endif
					
                </h4>

            </div>
            @if($showReport && $currencyName)
					
            <div class="report-flex
			@if($currencyName== 'main_currency')
			visibility-hidden
			@endif
			"
			>
                <div class="kt-align-right ">
                    <a href="{{ route('show.total.net.balance.in',['company'=>$company->id , 'currency'=>$currencyName ,'modelType'=>$invoiceType   ]) }}" type="button" class="d-flex ml-3 btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('All Invoices Report') }} </a>
                </div>

                <div class="kt-align-right ">
                    <a href="{{ route('show.total.net.balance.in',['company'=>$company->id , 'currency'=>$currencyName ,'modelType'=>$invoiceType,'only'=>'past_due'   ]) }}" type="button" class="d-flex ml-3 btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Past Dues Report') }} </a>
                </div>
            </div>
			
			
			@else 


 <div class="report-flex 
 
 visibility-hidden
 
 ">
                <div class="kt-align-right ">
                    <a href="#" type="button" class="d-flex ml-3 btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </a>
                </div>

                <div class="kt-align-right ">
                    <a href="#" type="button" class="d-flex ml-3 btn btn-sm btn-brand btn-elevate btn-pill"><i class="fa fa-chart-line"></i> {{ __('Report') }} </a>
                </div>
            </div>
			
            @endif

        </div>
        <div class="kt-widget24__details">
            <span class="kt-widget24__stats kt-font-{{ $color ?? 'brand' }}">
                {{ number_format($total)  }}
            </span>
        </div>

        <div class="progress progress--sm">
            <div class="progress-bar kt-bg-{{ $color ?? 'brand' }}" role="progressbar" style="width: 100%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="kt-widget24__action">

        </div>


    </div>

    <!--end::Total Profit-->
</div>
