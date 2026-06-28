@props([
'id',
'rounded'=>true ,
'borderRadius'=>'100%',
'label'=>'',
'required'=>true ,
'name',
'readUrlFunctionName'=>'readUrl',
'image'=>'image',
'editable'=>true ,
'clickToEnlarge'=>false 
])
<style>
    .avatar-edit label:hover {
        background-color: #9b8787 !important;
    }

    .edit-icon-color {
        color: white !important;
    }

    .img-label {
        font-size: 1rem;
        line-height: 1.25rem;
        letter-spacing: -0.025em;
        line-height: 1.5rem;
        color: #4B4B4B;
    }

    .avatar-upload {
        position: relative;
        max-width: 205px;
    }

    .avatar-upload .avatar-edit {
        max-width: 175px;
        position: absolute;
		right:0;
        top: 0;
        width: 100%;
    }

    .avatar-upload .avatar-edit input {
        display: none;
    }

    .avatar-upload .avatar-edit input+label {
        display: flex;
        align-items: center;
        color: black;
        justify-content: center;
        margin-bottom: 0;
        box-sizing: inherit;
        background-color: gray;
        border: 1px solid transparent;
        box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.12);
        cursor: pointer;
        font-weight: normal;
        transition: all 0.5s ease-in-out;
		
		
		
		position:relative;
		width:35px;
		height:40px;
		z-index:99999;
		border-radius:50%;
    }

    .avatar-upload .avatar-edit input+label:after {
        display: none;
        content: "\f040";
        font-family: "FontAwesome";
        color: #757575;
        position: absolute;
        top: 10px;
        left: 0;
        right: 0;
        text-align: center;
        margin: auto;
    }

    .avatar-upload .avatar-preview {
        width: 175px;
        height: 175px;
        position: relative;
    }

    .avatar-upload .avatar-preview>* {
        display:block;
		width: 100%;
        height: 100%;
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        box-sizing: border-box;
    }

</style>
<div class="file-upload-container row form-group">

    @if($label)
    <label class="img-label label-control col-md-3">{{ $label }}
	
	@if($clickToEnlarge)
	<span class="required">
		({{ __('Click To Enlarge') }})
	</span>
	@endif 
	
	@if($required)
	@include('star')
	@endif 
	 </label>
    @endif
    <div class="col-md-12">
        <div class="avatar-upload">
				@if($editable)
            <div class="avatar-edit">
                <input data-id="{{ $id }}" name="{{ $name }}" type='file' id="{{ $id .'-upload-id' }}" />
                <label for="{{ $id .'-upload-id' }}" class="label-control">
                    <i class="fa fa-pen edit-icon-color "></i>
                </label>
            </div>
				@endif
				@if($clickToEnlarge) 
            	<div class="avatar-preview">
                <a href="{{ $image }}" target="_blank"  id="{{ $id }}" style="background-image: url('{{$image}}')">
                </a>
            </div>
			@else 
			<div class="avatar-preview">
                <div  id="{{ $id }}" style="background-image: url('{{$image}}')">
                </div>
            </div>
			@endif 
			@error($name)
        <span class="text-danger">{{$message}}</span>
			@enderror()
        </div>
    </div>
</div>
@push('js')
<script>
    function readURL(input, id) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#' + id).css('background-image', 'url(' + e.target.result + ')');
                $('#' + id).hide();
                $('#' + id).fadeIn(650);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $("#{{ $id .'-upload-id' }}").change(function() {
        const id = $(this).attr('data-id');
        readURL(this, id);
    });

</script>
@endpush
