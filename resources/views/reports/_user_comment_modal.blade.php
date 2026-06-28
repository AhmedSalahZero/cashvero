 @if($model->hasComment() )
 <a data-toggle="modal" data-target="#user-comment-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-success btn-icon" title="{{ __('User Comment') }}" href="#"><i class="fa fa-comment"></i></a>
 <div class="modal fade" id="user-comment-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="#" method="post">
                 @csrf
	
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('User Comment') }}</h5>
                     <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
				 <div class="modal-body">
				 	<h2 class="text-wrap {{ \App\Helpers\HStr::isArabic($model->getUserComment()) ? 'text-right' : 'text-left' }}">{{ $model->getUserComment()  }}</h2>
				 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                     {{-- <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button> --}}
                 </div>

             </form>
         </div>
     </div>
 </div>
@endif 
