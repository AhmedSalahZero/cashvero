@php
	$mode = isset($rate) ? 'edit' : 'create';
@endphp
<input type="hidden" name="company_id" value="{{ $company->id }}">
<div class="col-md-2">
    <label>{{__('Date')}} </label>
    <div class="kt-input-icon">
        <div class="input-group date">
            <input name="date_{{ $mode }}" type="date" value="{{ isset($rate) ? $rate->getDate() : formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control" />
        </div>
    </div>
</div>

<div class="col-md-2 mb-4 ">
    <label class="form-label font-weight-bold ">{{ __('Borrowing Rate') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input type="number" class="form-control only-percentage-allowed borrowing-rate-class recalculate-interest-rate" name="borrowing_rate_{{ $mode }}" value="{{ isset($rate) ? $rate->getBorrowingRate() : 0 }}" step="any">
        </div>
    </div>
</div>

<div class="col-md-2 mb-4 ">
    <label class="form-label font-weight-bold ">{{ __('Margin Rate') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input type="number" class="form-control only-percentage-allowed margin-rate-class recalculate-interest-rate" name="margin_rate_{{ $mode }}" value="{{ isset($rate) ? $rate->getMarginRate() : 0 }}" step="any">
        </div>
    </div>
</div>

<div class="col-md-2 mb-4 ">
    <label class="form-label font-weight-bold ">{{ __('Interest Rate') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input disabled type="number" class="form-control interest-rate-class" value="{{ isset($rate) ? $rate->getInterestRate() : '' }}" step="any">
        </div>
    </div>
</div>

<div class="col-md-2 mb-4 ">
    <label class="form-label font-weight-bold ">{{ __('Min Interest Rate') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input name="min_interest_rate_{{ $mode }}" type="number" class="form-control" value="{{ isset($rate) ? $rate->getMinInterestRate() : 0 }}" step="any">
        </div>
    </div>
</div>

@once
@push('js')
	<script>
		$(document).on('change','.recalculate-interest-rate',function(){
			const parent = $(this).closest('.closest-parent') ;
			const marginRate = parent.find('.margin-rate-class').val();
			const borrowingRate = parent.find('.borrowing-rate-class').val();
			const interestRate = parseFloat(marginRate) + parseFloat(borrowingRate) ; 
			parent.find('.interest-rate-class').val(number_format(interestRate));
		})		
	</script>
@endpush
@endonce 
