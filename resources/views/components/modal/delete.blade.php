@props([
	'title'=>$title ?? __('Do You Want To Delete This Item ?'),
	'hasCloseBtn'=>$hasCloseBtn ?? true ,
	'deleteRoute'=>$deleteRoute ,
	'method'=>$method ?? 'delete',
	'id'=>$id ?? 'exampleModalCenter'
])


<div class="modal fade" id="{{ $id  }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">{{ $title }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      {{-- <div class="modal-body">
	  	<div class="modal__content">
		</div>
      </div> --}}
	  <div class="modal-footer">
    
	 @csrf 
	  	@if($hasCloseBtn)
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
		@endif 
        <button href="{{ $deleteRoute }}" class="btn btn-danger">{{ __('Delete') }}</button>
      </div>
    </div>
  </div>
</div>
