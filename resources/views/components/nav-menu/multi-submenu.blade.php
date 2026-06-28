@props([
'subItems'
])


@foreach($subItems as $menuArr)

@if(isset($menuArr['submenu'])&&count($menuArr['submenu']))
@if($menuArr['show'])

<li

 class="kt-menu__item  kt-menu__item--submenu " data-ktmenu-submenu-toggle="hover" aria-haspopup="true"><a href="#" class="kt-menu__link kt-menu__toggle"><i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i><span class="kt-menu__link-text">
 {{ $menuArr['title'] }}
	@if(isset($menuArr['count']))
	<div class="ml-3">
@include('red-notification',['count'=>$menuArr['count']])	
	
	</div>
	@endif
 
 </span><i class="kt-menu__hor-arrow la la-angle-right"></i><i class="kt-menu__ver-arrow la la-angle-right"></i></a>
    <div class="kt-menu__submenu kt-menu__submenu--classic kt-menu__submenu--right">
        <ul class="kt-menu__subnav">
            <x-nav-menu.multi-submenu :subItems="$menuArr['submenu']"></x-nav-menu.multi-submenu>
        </ul>
    </div>
</li>
@endif 
@else

<x-nav-menu.single-submenu :menuArr="$menuArr"></x-nav-menu.single-submenu>
@endif
@endforeach
