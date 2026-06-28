
@push('js')
	<script>
		$(document).on('click','[data-show-notification-modal="{{ $notificationMainType }}"]',function(e){
			e.preventDefault();
			$('.{{ $notificationMainType }}-modal').modal('show');
		})
	</script>
	

	
@endpush

@php
	$customerPastDues = $company->notifications->where('data.type',$notificationMainType);
	
	$notificationHeaders = $customerPastDues->first() ? array_keys($customerPastDues->first()->data['data_array']) : [];
@endphp


<div class="modal fade notification-modal {{ $notificationMainType }}-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-90 modal-dialog-centered" role="document">
        <form action="#" class="modal-content" method="post">


            @csrf
            <div class="modal-header">
                <h5 class="modal-title"  id="exampleModalLongTitle">{{ $notificationMainTitle }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
			
                <div class="customize-elements">
                    <table class="table   table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
                        <thead>
                            <tr class="header-tr">
                                <th class="view-table-th   bg-lighter header-th  align-middle text-center"> # </th>
								@foreach($notificationHeaders as $notificationHeader)
                                <th class="view-table-th   bg-lighter header-th  align-middle text-center"> {!! __($notificationHeader) !!} </th>
								@endforeach
                            </tr>
                        </thead>
                        <tbody>
							@php
								$popupSerial = 1 ;
							@endphp

                            @foreach($customerPastDues  as $customerPastDue)
                            <tr>
								<td>
									{{ $popupSerial }}
									@php
										$popupSerial++;
									@endphp
								</td>
								@foreach($notificationHeaders as $notificationHeader)
                                <td>
                                    <div class="kt-input-icon">
                                        <div class="input-group">
                                            <input disabled type="text" class="form-control text-center ignore-global-style" value="{{ $customerPastDue['data']['data_array'][$notificationHeader] ??'---' }}">
                                        </div>
                                    </div>
                                </td>
								@endforeach 




                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </form>
    </div>
</div>
