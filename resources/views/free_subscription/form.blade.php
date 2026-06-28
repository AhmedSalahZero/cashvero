<!DOCTYPE html>

<!--
Template Name: Metronic - Responsive Admin Dashboard Template build with Twitter Bootstrap 4 & Angular 8
Author: KeenThemes
Website: http://www.keenthemes.com/
Contact: support@keenthemes.com
Follow: www.twitter.com/keenthemes
Dribbble: www.dribbble.com/keenthemes
Like: www.facebook.com/keenthemes
Purchase: http://themeforest.net/item/metronic-responsive-admin-dashboard-template/4021469?ref=keenthemes
Renew Support: http://themeforest.net/item/metronic-responsive-admin-dashboard-template/4021469?ref=keenthemes
License: You must have a valid license purchased only from themeforest(the above link) in order to legally use the theme for your project.
-->
<html lang="en">

<!-- begin::Head -->

<head>

    <!--begin::Base Path (base relative path for assets of this page) -->
    <base href="../">

    <!--end::Base Path -->
    <meta charset="utf-8" />
    <title>VERO ANALYSIS</title>
    <meta name="description" content="Latest updates and statistic charts">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!--begin::Fonts -->
    <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
    <script>
        WebFont.load({
            google: {
                "families": ["Poppins:300,400,500,600,700"]
            }
            , active: function() {
                sessionStorage.fonts = true;
            }
        });

    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lobster&display=swap" rel="stylesheet">
    <!--end::Fonts -->

    <!--begin::Page Vendors Styles(used by this page) -->
    <!--end::Page Vendors Styles -->

    <!--begin:: Global Mandatory Vendors -->
    {{-- <link href="{{url('assets/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet" type="text/css" /> --}}

    <!--end:: Global Mandatory Vendors -->

    <!--begin:: Global Optional Vendors -->
    <link href="{{url('assets/vendors/general/tether/dist/css/tether.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/select2/dist/css/select2.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/ion-rangeslider/css/ion.rangeSlider.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/nouislider/distribute/nouislider.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/owl.carousel/dist/assets/owl.carousel.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/owl.carousel/dist/assets/owl.theme.default.css')}}" rel="stylesheet" type="text/css" />
    {{-- <link href="{{url('assets/vendors/general/animate.css/animate.css')}}" rel="stylesheet" type="text/css" /> --}}
    {{-- <link href="{{url('assets/vendors/general/socicon/css/socicon.css')}}" rel="stylesheet" type="text/css" /> --}}
    <link href="{{url('assets/vendors/custom/vendors/line-awesome/css/line-awesome.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/custom/vendors/flaticon/flaticon.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/custom/vendors/flaticon2/flaticon.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/@fortawesome/fontawesome-free/css/all.min.css')}}" rel="stylesheet" type="text/css" />

    <!--end:: Global Optional Vendors -->

    <!--begin::Global Theme Styles(used by all pages) -->
    <link href="{{url('assets/css/demo4/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/css/custom.css')}}" rel="stylesheet" type="text/css" />

    <!--end::Global Theme Styles -->
    <style>
        /* .dataTables_wrapper{max-width: 100%;  padding-bottom: 50px !important;overflow-x: overlay;max-height: 4000px;} */
        /* .dataTables_wrapper{max-width: 100%;  padding-bottom: 50px !important;overflow-x: overlay;max-height: 4000px;} */

    </style>
    <!--begin::Layout Skins(used by all pages) -->

    <!--end::Layout Skins -->
    <link rel="shortcut icon" href="{{url('assets/media/logos/logo_va.png')}}" />
    @toastr_css
 
    @yield('css')
</head>

<!-- end::Head -->

<!-- begin::Body -->

<body style="background-image: url({{url('assets/media/demos/demo4/header.jpg')}}); background-position: center top; background-size: 100% 350px;" class="kt-page--loading-enabled kt-page--loading kt-quick-panel--right kt-demo-panel--right kt-offcanvas-panel--right kt-header--fixed kt-header--minimize-menu kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-page--loading">

    <!-- begin::Page loader -->

    <!-- end::Page Loader -->

    <!-- begin:: Page -->

    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile  kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a href="#">
                <img height="65px" alt="CashVero" src="{{ url('assets/media/logos/cashvero-logo.png') }}" style="object-fit: contain; max-width: 200px; height: auto; max-height: 65px;" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more-1"></i></button>
        </div>
    </div>

    <!-- end:: Header Mobile -->
    <div class="kt-grid kt-grid--hor kt-grid--root">
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor kt-wrapper" id="kt_wrapper">

                <!-- begin:: Header -->

                <!-- end:: Header -->
                <div class="kt-body kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor kt-grid--stretch" id="kt_body">
                    <div class="kt-content kt-content--fit-top  kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor" id="kt_content">

                        <!-- begin:: Subheader -->
                        <div class="kt-subheader   kt-grid__item" id="kt_subheader">
                            <div class="kt-container ">
                                <div class="kt-subheader__main">
                                    <h3 class="kt-subheader__title" style="font-variant: small-caps;">
                                        @yield('sub-header')
                                    </h3>
                                    <button class="kt-header-menu-wrapper-close" id="kt_header_menu_mobile_close_btn"><i class="la la-close"></i></button>
                                    <div class="kt-header-menu-wrapper" id="kt_header_menu_wrapper">
                                        <div id="kt_header_menu" class="kt-header-menu kt-header-menu-mobile  kt-header-menu--layout-tab ">

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- end:: Subheader -->

                        <!-- begin:: Content -->
                        <div class="kt-container  kt-grid__item kt-grid__item--fluid">

                            <!--Begin::Dashboard 4-->
                            @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                            <div class="row">
                                <div class="col-12">
                                    <div class="kt-portlet">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title head-title text-primary">
                                                    {{__('Free Subscription')}}
                                                </h3>
                                            </div>
                                        </div>
                                    </div>

                                    <form class="kt-form kt-form--label-right" method="POST" action={{route('free.user.subscription')}} enctype="multipart/form-data">
                                        @csrf
                                        <div class="kt-portlet">
                                            <div class="kt-portlet__head">
                                                <div class="kt-portlet__head-label">
                                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                                        {{__('User Information')}}
                                                    </h3>
                                                </div>
                                            </div>
                                            <div class="kt-portlet__body">
                                                <div class="form-group row col-12 col-12">
                                                    <div class="col-6">
                                                        <label>{{__('Name')}} @include('star')</label>
                                                        <div class="kt-input-icon">
                                                            <input type="text" name="name" value="" class="form-control" placeholder="{{__('Name')}}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label>{{__('Email')}} @include('star')</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"><span class="input-group-text">@</span></div>
                                                            <input type="email" name="email" value="" class="form-control" placeholder="{{__('Email')}}" aria-describedby="basic-addon1">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group row col-12 col-12">
                                                    <div class="col-6">
                                                        <label>{{__('User Image')}} @include('star')</label>
                                                        <div class="kt-input-icon">
                                                            <input type="file" class="form-control" name="avatar">
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label>{{__('Phone Number')}} @include('star')</label>
                                                        <div class="kt-input-icon">
                                                            <input type="number" name="phone" class="form-control" placeholder="{{__('Phone Number')}}" value="" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group row col-12">
                                                    <div class="col-6">
                                                        <label>{{__('Password')}} @include('star')</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-key"></i></span></div>
                                                            <input id="password" type="password" name="password" value="{{@$user_row['email']}}" class="form-control" placeholder="{{__('Password')}}" aria-describedby="basic-addon1">
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label>{{__('Confirm Password')}} @include('star')</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-key"></i></span></div>
                                                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{__('Password')}}" aria-describedby="basic-addon1">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="kt-portlet">
                                            <div class="kt-portlet__head">
                                                <div class="kt-portlet__head-label">
                                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                                        {{__('Company Information')}}
                                                    </h3>
                                                </div>
                                            </div>
                                            <div class="kt-portlet__body">
                                                <div class="form-group row col-12">
                                                    <div class="col-6">
                                                        <label>{{__('Company Name En')}} @include('star')</label>
                                                        <div class="kt-input-icon">
                                                            <input type="text" name="company_name[en]" class="form-control" placeholder="{{__('Company Name')}}" value="" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label>{{__('Company Image')}} @include('star')</label>
                                                        <div class="kt-input-icon">
                                                            <input type="file" class="form-control" name="company_avatar">
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>


                                        <x-submitting />
                                    </form>
                                </div>
                            </div>


                            <!--End::Row-->

                            <!--End::Dashboard 4-->
                        </div>

                        <!-- end:: Content -->
                    </div>
                </div>

                <!-- begin:: Footer -->
                @include('layouts.footer')

                <!-- end:: Footer -->
            </div>






        </div>
    </div>

    <!-- end:: Page -->




    <!-- begin::Global Config(global config for global JS sciprts) -->
    <script>
        var KTAppOptions = {
            "colors": {
                "state": {
                    "brand": "#366cf3"
                    , "light": "#ffffff"
                    , "dark": "#282a3c"
                    , "primary": "#5867dd"
                    , "success": "#34bfa3"
                    , "info": "#36a3f7"
                    , "warning": "#ffb822"
                    , "danger": "#fd3995"
                }
                , "base": {
                    "label": ["#c5cbe3", "#a1a8c3", "#3d4465", "#3e4466"]
                    , "shape": ["#f0f3ff", "#d9dffa", "#afb4d4", "#646c9a"]
                }
            }
        };

    </script>

    <!-- end::Global Config -->

    <!--begin:: Global Mandatory Vendors -->
    <script src="{{url('assets/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/js-cookie/src/js.cookie.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/moment/min/moment.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/sticky-js/dist/sticky.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/wnumb/wNumb.js')}}" type="text/javascript"></script>

    <!--end:: Global Mandatory Vendors -->
    <!--begin:: Global Optional Vendors -->
    <script src="{{url('assets/vendors/general/jquery-form/dist/jquery.form.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/block-ui/jquery.blockUI.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/owl.carousel/dist/owl.carousel.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/bootstrap-switch/dist/js/bootstrap-switch.js')}}" type="text/javascript"></script>


    <!--end:: Global Optional Vendors -->


    <!--end::Global Theme Bundle -->



    <!--end::Page Vendors -->

    <!--begin::Global Theme Bundle(used by all pages) -->
    <script src="{{url('assets/js/demo4/scripts.bundle.js')}}" type="text/javascript"></script>
    <!--begin::Page Scripts(used by this page) -->
    <script src="{{url('assets/js/demo4/pages/dashboard.js')}}" type="text/javascript"></script>
    {{-- @jquery --}}
    @toastr_js
    @toastr_render
    @yield('js')
    <!--end::Page Scripts -->
    <!--end::Page Scripts -->
</body>

<!-- end::Body -->

</html>
