@php
	$actionType = isset($submodel) ? 'update' :'create';
@endphp
 <div class="col-md-3 mb-4">
     <label> {!! __('Expense <br> Name') !!} </label>
     <div class="kt-input-icon">
         <input name="expense_name[{{ $actionType }}]" value="{{ isset($submodel) ? $submodel->getName() : '' }}" type="text" class="form-control ">
     </div>
 </div>

 <div class="col-md-2 mb-4">
     <label>{!! __(' <br> Date') !!}</label>
     <div class="kt-input-icon">
         <div class="input-group date">
             <input required type="text" name="date[{{ $actionType }}]" value="{{ isset($submodel) ? formatDateForDatePicker($submodel->getDate()) :formatDateForDatePicker(now()->format('Y-m-d')) }}" class="form-control " readonly placeholder="Select date" id="kt_datepicker_2" />
             <div class="input-group-append">
                 <span class="input-group-text">
                     <i class="la la-calendar-check-o"></i>
                 </span>
             </div>
         </div>
     </div>
 </div>


 <div class="col-md-2 mb-4">
     <label> {!! __('<br> Amount') !!} </label>
     <div class="kt-input-icon">

         <input name="amount[{{ $actionType }}]" value="{{ isset($submodel) ? $submodel->getAmount() : 0 }}" type="text" class="form-control trigger-change-after-page-open recalculate-amount-in-main-currency amount-js only-greater-than-or-equal-zero-allowed">
     </div>
 </div>



 <div class="col-md-2 mb-4">
     <label> {!! __('Select <br> Currency') !!} </label>
     <div class="kt-input-icon">
         <div class="input-group date">
             <select data-live-search="true" data-actions-box="true" name="currency[{{ $actionType }}]" required class="form-control currency-js kt-bootstrap-select select2-select kt_bootstrap_select ajax-currency-name ajax-refresh-customers">
                 @foreach(getBanksCurrencies() as $currencyName)
                 <option @if(isset($submodel) && $submodel->getCurrency() == $currencyName ) selected @endif value="{{ $currencyName }}">{{ touppercase($currencyName) }}</option>
                 @endforeach
             </select>
         </div>
     </div>
 </div>

 <div class="col-md-1 mb-4">
     <label> {!! __('Exchange <br> Rate') !!} </label>
     <div class="kt-input-icon">
         <input name="exchange_rate[{{ $actionType }}]" value="{{ isset($submodel) ? $submodel->getExchangeRate() :1 }}" type="text" class="form-control recalculate-amount-in-main-currency exchange-rate-js only-greater-than-or-equal-zero-allowed">
     </div>
 </div>


 <div class="col-md-2 mb-4">
     <label> {!! __('Amount In <br> Main Currency') !!} </label>
     <div class="kt-input-icon">
         <input type="hidden" name="amount_in_main_currency[{{ $actionType }}]" class="amount-in-main-currency-js-hidden" value="0" type="text">
         <input disabled value="0" type="text" class="form-control amount-in-main-currency-js only-greater-than-or-equal-zero-allowed">
     </div>
 </div>
