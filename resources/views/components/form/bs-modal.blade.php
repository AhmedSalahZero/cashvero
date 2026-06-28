@props([
    'id','modalTitle','hasSaveBtn'=>true , 'saveBtnTitle'=>__('Save'),'submitBtnClass'=>'','modelBodyId'=>'','modalTitleId'=>''
])
<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{$modalTitleId ?:'exampleModalLongTitle' }}">
            {{ $modalTitle??'' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body " @if($modelBodyId) id="{{ $modelBodyId }}" @endif>
        {{ $slot }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
        @if($hasSaveBtn)
        <button type="button" class="btn btn-primary {{ $submitBtnClass }} ">{{ $saveBtnTitle }}</button>
        @endif 
      </div>
    </div>
  </div>
</div>