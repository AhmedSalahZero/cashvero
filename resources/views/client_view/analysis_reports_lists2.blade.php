@extends('layouts.dashboard')
@section('css')
    <style>
        table {
            white-space: nowrap;
        }
        
    </style>

    <link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('sub-header')
    {{__($section->name[lang()])}}
@endsection
@section('content')
@php
	$user = auth()->user();
@endphp
<div class="col-md-12">

    <!--begin:: Widgets/Tasks -->
    <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
        <div class="kt-portlet__head">

            <div class="kt-portlet__head-toolbar">
                <ul class="nav nav-tabs nav-tabs-line nav-tabs-bold nav-tabs-line-brand" role="tablist">
                   @foreach ($reports as   $mainName=>$options)
                       
                       
                       <li class="nav-item">
							@php
							
								$mainIsActive = $loop->first ;
								$mainNameAsId=convertStringToClass($mainName);
							@endphp
                                    <a class="nav-link {{ $mainIsActive ?'active':'' }}" onclick="return false" data-toggle="tab" href="#kt_widget2_tab1_content_{{$mainNameAsId}}" role="tab">
                                        <i
                                        class="kt-menu__ver-arrow {{ $options['icon'] }}"></i><span class="kt-menu__link-text">
											{{ $options['view_name'] }}											
                                            </span>
                                    </a>
                                </li>
           @endforeach
                </ul>
            </div>
        </div>
        <div class="kt-portlet__body">
            <div class="tab-content">
                  @foreach ($reports as   $mainName=>$options)
                        @php
							$subIsActive = $loop->first ;
							$mainNameAsId=convertStringToClass($mainName);
						@endphp
                        <div class="tab-pane {{ $subIsActive ? 'active':'' }}" id="kt_widget2_tab1_content_{{$mainNameAsId}}">
                            <div class="kt-widget2">
                                <div class="row">
                                    

                                    @foreach ($options['subTabs'] as $subTabArr)
									@php
										$route = $subTabArr['route'];
										$subName = $subTabArr['view_name'];
									@endphp


                                                <div class="col-md-4">
                                                    <div class="kt-widget2__item kt-widget2__item--primary">
                                                        <div class="kt-widget2__checkbox">
                                                        </div>
                                                      
														
                                                        <div class="kt-widget2__info">
                                                            <a href="{{ $route }}" class="kt-widget2__title">
															{{ $subName }}
                                                            </a>

                                                        </div>
                                                        <div class="kt-widget2__actions">

                                                        </div>
                                                    </div>
                                                </div>
											

                                    @endforeach
                                </div>
                            </div>
                        </div>
                @endforeach
            </div>
        </div>
    </div>

    <!--end:: Widgets/Tasks -->
</div>


@endsection

@section('js')
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
@endsection
