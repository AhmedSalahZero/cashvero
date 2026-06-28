@props([
	'title',
	'icon'=>'fa-layer-group'
])
<div class="kt-portlet__head kt-portlet__head--lg p-0">
                        <div class="kt-portlet__head-label ml-4">
                            <span class="kt-portlet__head-icon">
                                <i class="kt-font-secondary btn-outline-hover-danger text-main-color fa {{ $icon }}"></i>
                            </span>
                            <label class="kt-portlet__head-title  text-main-color" style="font-size:20px !important; ">
                                {{ $title }}
                            </label>

                        </div>
{{ $slot }}						
                    </div>
