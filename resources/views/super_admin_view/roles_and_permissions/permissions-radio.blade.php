
<style>
.kt-portlet__body{
	padding-top:0 !important;
}
.hover-color-black:hover i{
	color:black !important;
}
    input[type="checkbox"] {
        cursor: pointer;
    }

    th {
        background-color: #0742A6;
        color: white;
    }

    .bank-max-width {
        max-width: 200px !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style) {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>

<div class="form-group-marginless">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-lg-12 " >
                                        <label class="kt-option bg-secondary">
                                            <span class="kt-option__control">
                                                <span
                                                    class="kt-checkbox kt-checkbox--bold kt-checkbox--brand kt-checkbox--check-bold"
                                                    checked>
                                                    <input type="checkbox" id="select_all"  >
                                                    <span></span>
                                                </span>
                                            </span>
                                            <span class="kt-option__label">
                                                <span class="kt-option__head">
                                                    <span class="kt-option__title"><b> {{ __('Select All') }} </b> </span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
							
							 <table class="table  table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
							 	<thead>
							 	<tr>
									<th class="text-left">
										<b>{{ __('Name') }}</b>
									</th>
									<th class="text-left">
										<b>{{ __('Actions') }}</b>
									</th>
								</tr>
								
								</thead>
								
								<tbody>
							 @php
								$groupIndex = 0 ;
							 @endphp
							 @foreach (formatArrayAsGroup(\App\Helpers\HAuth::getPermissions($user->getSystemsNames())) as $groupName=>$permissionArrays)
							 <tr>
							 	<td class="text-left text-capitalize">
								{{-- {{ $groupIndex+1 }} --}}
								 <div class="kt-checkbox-inline d-flex  ">
                                                    <label class="kt-checkbox kt-checkbox--bold kt-checkbox--success text-capitalize" cheched="">
                                                        <input data-group-index="{{ $groupIndex }}" type="checkbox" class="checkbox-for-row" value="1" 
                                                        checked
                                                        > 
                                                        <span></span>
													<b>{{$groupName}}</b>
                                                    </label>
                                                    
                                                </div>     
												
									
								</td>
								<td>
									<div class="row pt-5 pl-4">
									
									@foreach($permissionArrays as $permissionArray)
                                    <div class="kt-checkbox-inline d-flex justify-content-between mr-4 mb-5 ">
                                                    <label class="kt-checkbox kt-checkbox--bold kt-checkbox--success text-capitalize" cheched="">
                                                        <input type="checkbox" class="view checkbox-for-permission" value="1" name="permissions[{{$permissionArray['name']}}]"
                                                        {{ $user->can($permissionArray['name']) ? 'checked' : ''}}
                                                        > {{ $permissionArray['view-name'] }}
                                                        <span></span>
                                                    </label>
                                                    
                                                </div>    
												
												@php
								$groupIndex++;
							 @endphp        
												@endforeach 
									</div>
								</td>
							 </tr>
{{-- 							
                                    <div class="form-group kt-checkbox-list">
                                        <div class="row col-md-12">
                                            <label class="col-3 col-form-label text-left text-capitalize"><b> {{$groupName}} </b></label>
                                            <div class="col-9">
											@foreach($permissionArrays as $permissionArray)
                                                <div class="kt-checkbox-inline d-flex justify-content-between">
                                                    <label class="kt-checkbox kt-checkbox--bold kt-checkbox--success " cheched="">
                                                        <input type="checkbox" class="view" value="1" name="permissions[{{$permissionArray['name']}}]"
                                                        {{ $user->can($permissionArray['name']) ? 'checked' : ''}}
                                                        > {{ $permissionArray['name'] }}
                                                        <span></span>
                                                    </label>
                                                    
                                                </div>
												@endforeach 

                                            </div>
                                        </div>
                                    </div> --}}
                        
                            @endforeach
							</tbody>
							 </table>
							
                        </div>
