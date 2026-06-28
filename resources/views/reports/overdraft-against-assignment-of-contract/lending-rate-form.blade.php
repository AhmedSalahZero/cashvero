@php
	$mode = isset($lendingInformationAgainstAssignmentOfContract) ? 'edit' : 'create';
@endphp
<div class="col-lg-3" data-dd="{{ isset($lendingInformationAgainstAssignmentOfContract) ? 1 : -1 }}">
    <label>{{__('Customer')}} @include('star')</label>
    <div class="input-group">
        <select data-live-search="true" name="customer_id_{{ $mode }}" class="form-control kt-bootstrap-select select2-select select2 ajax-get-contracts-for-customer-{{ $mode }}">
            @foreach($customers as $customerId => $customerName )
            <option value="{{ $customerId  }}" @if(isset($lendingInformationAgainstAssignmentOfContract) && $lendingInformationAgainstAssignmentOfContract->getCustomerId() == $customerId ) selected  @endif > {{ $customerName }}</option>
            @endforeach
        </select>
    </div>
</div>



<div class="col-md-3">
    <label>{{__('Contract')}} @include('star')</label>
    <div class="input-group">
        <select name="contract_id_{{ $mode }}" class="form-control append-contracts-{{ $mode }}">
			@if(isset($lendingInformationAgainstAssignmentOfContract))
			@foreach(\App\Models\Contract::getForParentAndCurrency($lendingInformationAgainstAssignmentOfContract->getCustomerId()   , $odAgainstAssignmentOfContract->getCurrency() ) as $contract)
			<option value="{{ $contract->id }}"> {{ $contract->getName() }} </option>
			@endforeach 
			@endif 
        </select>
    </div>
</div>

<div class="col-md-2 mb-4 ">
    <label class="form-label font-weight-bold">{{ __('Amount') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input type="text" disabled class="form-control  contract-amount-class-{{ $mode }}" value="{{ isset($lendingInformationAgainstAssignmentOfContract) ? $lendingInformationAgainstAssignmentOfContract->getContractAmountFormatted() : 0 }}" step="any">
        </div>
    </div>
</div>

<div class="col-md-2">
    <label>{{__('Start Date')}} </label>
    <div class="kt-input-icon">
        <div class="input-group date">
            <input disabled type="date" value="{{ isset($lendingInformationAgainstAssignmentOfContract) ? $lendingInformationAgainstAssignmentOfContract->getContractStartDate() : '' }}" class="form-control contract-start-date-class-{{ $mode }}" />
        </div>
    </div>
</div>
<div class="col-md-2">
    <label>{{__('End Date')}} </label>
    <div class="kt-input-icon">
        <div class="input-group date">
            <input disabled type="date" value="{{ isset($lendingInformationAgainstAssignmentOfContract) ? $lendingInformationAgainstAssignmentOfContract->getContractEndDate() : '' }}" class="form-control contract-end-date-class-{{ $mode }}" />
        </div>
    </div>
</div>

<div class="col-md-2">
    <label>{{__('Assignment Date')}} </label>
    <div class="kt-input-icon">
        <div class="input-group date">
            <input name="assignment_date_{{ $mode }}" required type="date" value="{{ isset($lendingInformationAgainstAssignmentOfContract) ? $lendingInformationAgainstAssignmentOfContract->getAssignmentEndDate() : '' }}" class="form-control contract-assignment-date-class-{{ $mode }}" />
        </div>
    </div>
</div>

<div class="col-md-1 mb-4 ">
    <label class="form-label font-weight-bold ">{{ __('Lending %') }} </label>
    <div class="kt-input-icon">
        <div class="input-group">
            <input type="number" class="form-control only-percentage-allowed" name="lending_rate_{{ $mode }}" value="{{ isset($lendingInformationAgainstAssignmentOfContract) ? $lendingInformationAgainstAssignmentOfContract->getLendingRate() : '' }}" step="any">
        </div>
    </div>
</div>
