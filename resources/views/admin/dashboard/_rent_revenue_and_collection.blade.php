<style>
    .max-class {
        border: 1px solid red !important;
        color: red !important;

    }
input.form-control[readonly] {
    background-color: #cce2fd !important;
    font-weight: bold !important;
}
    .min-class {
        border: 1px solid green !important;
        color: green !important;
    }

    .avg-class {
        border: 1px solid blue !important;
        color: blue !important;
    }

    .outlier-class {
        border: 1px solid orange !important;
        color: black !important;
    }

    .min-avg-max-class {
        background-color: white !important;
    }

</style>
<table class="table">
    <thead>
	<tr>
		<th class="w-20-percentage">
			{{ __('Property Type') }}
		</th>
		<th class="w-20-percentage">
			{{ $currentMonth }}
		</th>
		<th class="w-20-percentage">
			{{ $nextMonth }}
		</th>
		<th class="w-20-percentage">
			{{ $twoMonthsLater }}
		</th>
		<th class="w-20-percentage">
			{{ __('Total ') }}
		</th>
	</tr>

    </thead>
    <tbody>




     
@php
	$styleClasses = [
		'avg-class',
		'min-class',
		'max-class',
		'outlier-class',
	];
@endphp
@php
	$styleIndex= 0 ;
@endphp
		@foreach($propertyTypes as $propertyType)
			@php
			if($styleIndex == count($styleClasses)){
				$styleIndex = 0;
			}
			$propertyTypeName = $propertyType['name'];
			 
			@endphp
        <tr>

            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style  {{ $styleClasses[$styleIndex] }}" value="{{$propertyTypeName}}">
                    </div>
                </div>
            </td>
			@php
				$currentTotal = 0;
 			@endphp
			@for($i = 0 ; $i < $durationToShow ; $i++)
			@php
				$currentValue = $currentRunningContractMonthRentAndCollectionsPerType[$propertyType['id']][$current_category][$i] ?? 0;
				$totalPerMonth[$i] = isset($totalPerMonth[$i]) ? $totalPerMonth[$i] + $currentValue : $currentValue;
				$currentTotal += $currentValue;
			@endphp
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ number_format($currentValue) }}">
						</div>
					</div>
				</td>
				@endfor
				{{-- <td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ number_format($currentRunningContractMonthRentAndCollectionsPerType[$propertyType['id']][$current_category]['next_month'] ?? 0) }}">
						</div>
					</div>
				</td>
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ number_format($currentRunningContractMonthRentAndCollectionsPerType[$propertyType['id']][$current_category]['two_months_later'] ?? 0) }}">
						</div>
					</div>
				</td> --}}
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ number_format($currentTotal) }}">
						</div>
					</div>
				</td>
        </tr>
		@php
			$styleIndex++;
		@endphp
@endforeach
{{-- @php
							$totalOfTotal = 0;
							$totalPerMonth = [
								'current_month'=>0,
								'next_month'=>0,
								'two_months_later'=>0,
							];
							foreach($currentRunningContractMonthRentAndCollectionsPerType as $typeId => $propertyType) {
								$totalOfTotal +=  $propertyType[$current_category]['total']??0;
								$totalPerMonth['current_month'] +=  $propertyType[$current_category]['current_month']??0;
								$totalPerMonth['next_month'] +=  $propertyType[$current_category]['next_month']??0;
								$totalPerMonth['two_months_later'] +=  $propertyType[$current_category]['two_months_later']??0;
							}
						@endphp --}}

  <tr>

            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input readonly type="text" class="form-control text-left " value="{{ __('Total') }}">
                    </div>
                </div>
            </td>
			@for($i = 0 ; $i < $durationToShow ; $i++)
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input readonly type="text" class="form-control text-center " value="{{ number_format($totalPerMonth[$i] ?? 0) }}">
						</div>
					</div>
				</td>
				@endfor
				{{-- <td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input readonly type="text" class="form-control text-center " value="{{ number_format($totalPerMonth['next_month'] ?? 0) }}">
						</div>
					</div>
				</td>
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input readonly type="text" class="form-control text-center " value="{{ number_format($totalPerMonth['two_months_later'] ?? 0) }}">
						</div>
					</div>
				</td> --}}
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input readonly type="text" class="form-control text-center " value="{{ number_format(array_sum($totalPerMonth)) }}">
						</div>
					</div>
				</td>
        </tr>
		
      



    </tbody>
</table>
