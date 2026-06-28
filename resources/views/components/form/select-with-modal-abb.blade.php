@props([
'selectedValue'=>'',
'label'=>'',
'all'=>false ,
'options'=>[],
'addNew'=>false ,
'isRequired'=>false ,
'isSelect2'=>true,
'addNewText'=>'',
'disabled'=>false ,
'addWithPopup'=>false,
'addNewWithFormPopupClass'=>'',
'addNewFormPath'=>'',
'addModelName'=>'',
'addModalTitle'=>'',
'appendNewOptionToSelectSelector'=>'',
'multiple'=>$multiple ??false ,
'pleaseSelect'=>$pleaseSelect ?? false ,
'addNewModal'=>false,
'addNewModalModalName'=>'',
'addNewModalModalType'=>'',
'addNewModalModalTitle'=>'',
'previousSelectMustBeSelected'=>false ,
'previousSelectSelector'=>'' ,
'previousSelectTitle'=>'',
'previousSelectNameInDB'=>'',
'labelClass'=>''
])
@if($label)
<label class="form-label  font-weight-bold @if($addNewModal) d-flex @endif "> 

<span class="{{ $labelClass }}">{{$label}}</span>


    @if($isRequired)
    @include('star')
    @endif
    @if($addNewModal && isset($company->id))
    <i @if($previousSelectMustBeSelected) data-previous-must-be-opened="1" data-previous-select-selector="{{ $previousSelectSelector }}" data-previous-select-title="{{ $previousSelectTitle }}" @endif title="{{ __('Add New') }}" data-company-id="{{ $company->id ?? 0 }}" data-modal-name="{{ $addNewModalModalName }}" data-modal-type="{{ $addNewModalModalType }}" data-modal-title="{{ $addNewModalModalTitle }}" class="fa fa-plus cursor-pointer block ml-auto trigger-add-new-modal"></i>
    @endif
</label>
@endif
@if($disabled)
@php
$isSelect2 = false ;
@endphp
@endif

@php
$basicClasses = $isSelect2 ? "form-control repeater-select mb-1 select select2-select" :"form-control mb-1 select ";
@endphp

<select @if($addNewModalModalName) data-modal-name="{{ $addNewModalModalName }}" data-modal-type="{{ $addNewModalModalType }}" @endif {{-- data-add-modal-name="{{ $addNewModalModalName }}" --}} @if($disabled) disabled @endif {{ $attributes->merge(['class'=>$basicClasses]) }} data-live-search="true" data-add-new="{{ $addNew ? 1 : 0 }}" data-all="{{ $all ? 1 :0 }}" @if($multiple) multiple @endif>

    @if($pleaseSelect)
    <option selected>{{ __('Please Select') }}</option>
    @endif
    @if($all)

    <option value="">{{ __('All') }}</option>
    @endif
    @if($addNew)
    <option class="add-new-item 
                @if($addWithPopup)
                add-with-popup
                @endif 
                " data-add-new-form="{{ $addNewWithFormPopupClass ?: '' }}" data-add-model-name="{{ $addModelName }}" data-add-modal-title="{{ $addModalTitle }}">{{ $addNewText ?: __('Add New') }}</option>
    @endif
    @foreach($options as $value=>$option)
    <option title="{{ $option['title']  }}" @foreach($option as $name=>$val)
        {{ $name .'='.$val }}
        @if($name == 'value' && $val == $selectedValue )
        selected
        @endif



        @endforeach


        >

        {{ $option['title'] }}</option>
    @endforeach
</select>
<div class="modal fade" id="modal-for-{{ $attributes->get('name') }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title modal-title-add-new-modal-{{ $addNewModalModalName }}"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="label">{{ __('Name') }}</label>
                            <input type="text" class="form-control name-class-js">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="label">{{ __('Abbrivation') }}</label>
                            <input type="text" class="form-control name-class-js">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="label">{{ __('Code') }}</label>
                            <input type="text" class="form-control name-class-js">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                <button @if($previousSelectMustBeSelected) data-previous-select-selector="{{ $previousSelectSelector }}" data-previous-select-title="{{ $previousSelectTitle }}" data-previous-select-name-in-db="{{ $previousSelectNameInDB }}" @endif data-company-id="{{ $company->id ?? 0 }}" data-modal-type="{{ $addNewModalModalType }}" data-modal-name="{{ $addNewModalModalName }}" data-modal-title="{{ $addNewModalModalTitle }}" type="button" class="btn btn-primary store-new-add-modal">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>


{{ $slot }}

@push('js')

@endpush
