    @props([
        'bodyClass'=>''
    ])
       <div {{ $attributes->merge(['class'=>'kt-portlet']) }}>
          
                
                <div class="{{ 'kt-portlet__body ' . $bodyClass }}" >

                    {{ $slot }}
                    </div>
                    </div>