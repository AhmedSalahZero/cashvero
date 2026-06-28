<style>
    .max-class {
        border: 1px solid red !important;
        color: red !important;

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
			{{ __('Total Count') }}
		</th>
		<th class="w-20-percentage">
			{{ __('Occupied ') }}
		</th><th class="w-20-percentage">
			{{ __('Vacant ') }}
		</th>
		<th class="w-20-percentage">
			{{ __('Not Delivered ') }}
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
                        <input disabled type="text" class="form-control text-left ignore-global-style  {{ $styleClasses[$styleIndex] }}" value="{{ $propertyTypeName }}">
                    </div>
                </div>
            </td>
			
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ $propertiesGroupedByType[$propertyType['id']] ?? 0 }}">
						</div>
					</div>
				</td>
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ $occupiedPropertiesGroupedByType[$propertyType['id']] ?? 0 }}">
						</div>
					</div>
				</td>
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ $vacantPropertiesGroupedByType[$propertyType['id']] ?? 0 }}">
						</div>
					</div>
				</td>
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							<input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ $notDeliveredPropertiesGroupedByType[$propertyType['id']] ?? 0 }}">
						</div>
					</div>
				</td>
				
				<td class="w-20-percentage">
					<div class="kt-input-icon">
						<div class="input-group">
							{{-- <input disabled type="text" class="form-control text-center ignore-global-style {{ $styleClasses[$styleIndex] }}" value="{{ $notDeliveredPropertiesGroupedByType[$propertyType['id']] ?? 0 }}"> --}}
						</div>
					</div>
				</td>
        </tr>
		@php
			$styleIndex++;
		@endphp
@endforeach
      


<style>

</style>
    </tbody>
</table>
