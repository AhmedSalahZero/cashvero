@props([
'instanceNo','groupName',
'itemClasses'=>'',
'repeaterWithSelect2'=>false
])
{{-- instanceNo up to 6 [hard coded]  --}}
<div id="m_repeater_{{ $instanceNo }}" {{ $attributes->merge(['class'=>'d-block w-full repeater-class']) }}>
    <div class="form-group w-100 m-form__group row d-inline-flex">
        <div data-repeater-list="{{ $groupName }}" class="col-lg-12 d-flex flex-wrap  align-items-center mx-auto">
            <div @if($repeaterWithSelect2) style="display: none; !important" @endif data-repeater-item class="form-group m-form__group row align-items-center repeater_item {{ $itemClasses ? $itemClasses : 'w-48' }}">


                {{ $slot }}


                <div class="">
                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">

                    </i>
                </div>

            </div>
        </div>
    </div>


    <div class="m-form__group form-group row">


        <div class="col-lg-6">
            <div data-repeater-create="" class="btn btn btn-sm btn-success add-row m-btn m-btn--icon m-btn--pill m-btn--wide {{__('right')}}">
                <span>
                    <i class="fa fa-plus"> </i>
                    <span>
                        {{ __('Add') }}
                    </span>
                </span>
            </div>
        </div>
    </div>


</div>

@push('js')
<script>
    $(function() {
        $('.repeater-with-select2').closest('.repeater-class').find('[data-repeater-delete]').trigger('click');
        $('.repeater-with-select2').closest('.repeater-class').find('[data-repeater-create]').trigger('click');
    });

</script>
@endpush
