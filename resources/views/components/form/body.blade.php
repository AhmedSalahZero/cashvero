        
        <form {{ $attributes->merge(['class'=>'kt-form kt-form--label-right']) }}  enctype="multipart/form-data" >
        @csrf 
        {!! $slot !!}
        
        </form>

        