@props([
'navigators'=>[]
])
<style>
    .kt-header-menu-wrapper {
        margin-left: 0 !important;
    }

</style>
<div class="d-flex mt-4">
    <div class="col-md-12">
        <div class="kt-header-menu-wrapper">
            <div class="kt-header-menu kt-header-menu-mobile  kt-header-menu--layout-tab ">
                <ul class="kt-menu__nav">
                    @foreach($navigators as $name=>$nagivatorOptions)

                    @if(isset($nagivatorOptions['hasSubItems']) &&$nagivatorOptions['hasSubItems'])


                    @else
                    <li class="kt-menu__item  kt-menu__item" aria-haspopup="true">
                        <a href="{{ $nagivatorOptions['link'] }}" class="kt-menu__link active-button">
                            <span class="kt-menu__link-text active-text">{{ $nagivatorOptions['name'] }}</span>
                        </a>
                    </li>
                    @endif
                    @endforeach


                </ul>

            </div>
        </div>

    </div>
</div>
