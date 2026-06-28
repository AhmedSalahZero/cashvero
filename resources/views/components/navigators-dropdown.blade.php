@props([
	'navigators'=>[]
])
<div id="second_kt_header" class="kt-header  kt-header--fixed fh-fixedHeader" data-ktheader-minimize="on">

    <div class="kt-container ">

        <!-- begin:: Brand -->
        <div class="kt-header__brand   kt-grid__item" id="kt_header_brand">

            <div class="kt-header-menu-wrapper kt-grid__item kt-grid__item--fluid ml-0" id="second_kt_header_menu_wrapper">
                <div id="second_kt_header_menu" class="kt-header-menu kt-header-menu-mobile ">
                    <ul class="kt-menu__nav ">
						@foreach($navigators as $navigatorItem)
						@include('nav-item',['navItem'=>$navigatorItem])
						@endforeach 
                    </ul>



                </div>
            </div>

        </div>
    </div>
</div>
