<!DOCTYPE html>
<html lang="en">

	<!-- begin::Head -->
	<head>

		<!--begin::Base Path (base relative path for assets of this page) -->
		<base href="../">

		<!--end::Base Path -->
		<meta charset="utf-8" />
		<title>Metronic | Dashboard</title>
		<meta name="description" content="Updates and statistics">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

		<!--begin::Fonts -->
		<script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
		<script>
			WebFont.load({
				google: {
					"families": ["Poppins:300,400,500,600,700", "Roboto:300,400,500,600,700"]
				},
				active: function() {
					sessionStorage.fonts = true;
				}
			});
		</script>

		<!--end::Fonts -->

		<!--begin::Page Vendors Styles(used by this page) -->
		{{-- <link href="{{url('assets/vendors/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" /> --}}
        <link href="{{url('assets/css/demo1/pages/login/login-4.css')}}" rel="stylesheet" type="text/css" />
		<!--end::Page Vendors Styles -->

		<!--begin:: Global Mandatory Vendors -->
		<link href="{{url('assets/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css')}}" rel="stylesheet" type="text/css" />

		<!--end:: Global Mandatory Vendors -->

		<!--begin:: Global Optional Vendors -->
		{{-- <link href="{{url('assets/vendors/general/tether/dist/css/tether.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-switch/dist/css/bootstrap3/bootstrap-switch.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/select2/dist/css/select2.css')}}" rel="stylesheet" type="text/css" /> --}}
		{{-- <link href="{{url('assets/vendors/general/ion-rangeslider/css/ion.rangeSlider.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/nouislider/distribute/nouislider.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/owl.carousel/dist/assets/owl.carousel.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/owl.carousel/dist/assets/owl.theme.default.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/dropzone/dist/dropzone.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/summernote/dist/summernote.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/bootstrap-markdown/css/bootstrap-markdown.min.css')}}" rel="stylesheet" type="text/css" /> --}}
		{{-- <link href="{{url('assets/vendors/general/animate.css/animate.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/toastr/build/toastr.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/morris.js/morris.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/general/sweetalert2/dist/sweetalert2.css')}}" rel="stylesheet" type="text/css" /> --}}
		{{-- <link href="{{url('assets/vendors/general/socicon/css/socicon.css')}}" rel="stylesheet" type="text/css" /> --}}
		<link href="{{url('assets/vendors/custom/vendors/line-awesome/css/line-awesome.css')}}" rel="stylesheet" type="text/css" />
		{{-- <link href="{{url('assets/vendors/custom/vendors/flaticon/flaticon.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/vendors/custom/vendors/flaticon2/flaticon.css')}}" rel="stylesheet" type="text/css" /> --}}
		<link href="{{url('assets/vendors/general/@fortawesome/fontawesome-free/css/all.min.css')}}" rel="stylesheet" type="text/css" />

		<!--end:: Global Optional Vendors -->

		<!--begin::Global Theme Styles(used by all pages) -->
		<link href="{{url('assets/css/demo1/style.bundle.css')}}" rel="stylesheet" type="text/css" />

		<!--end::Global Theme Styles -->

		<!--begin::Layout Skins(used by all pages) -->
		<link href="{{url('assets/css/demo1/skins/header/base/light.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/css/demo1/skins/header/menu/light.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/css/demo1/skins/brand/dark.css')}}" rel="stylesheet" type="text/css" />
		<link href="{{url('assets/css/demo1/skins/aside/dark.css')}}" rel="stylesheet" type="text/css" />
        @yield('css')
		<!--end::Layout Skins -->
        <link rel="shortcut icon" href="{{url('assets/media/logos/favicon.ico')}}" />

	</head>
	<!-- end::Head -->


	<!-- begin::Body -->
	<body class="kt-quick-panel--right kt-demo-panel--right kt-offcanvas-panel--right kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--fixed kt-subheader--solid kt-aside--enabled kt-aside--fixed kt-page--loading">

		<!-- begin:: Page -->
            <div class="kt-grid kt-grid--ver kt-grid--root">
                <div class="kt-grid kt-grid--hor kt-grid--root  kt-login kt-login--v4 kt-login--signin" id="kt_login">
                    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor" style="background-image: url({{asset('assets/media/bg/bg-2.jpg')}});">
                        <div class="kt-grid__item kt-grid__item--fluid kt-login__wrapper">
                            <div class="kt-login__container">
                                <div class="kt-login__logo">
                                    <a href="#">
                                        <img src="{{url('assets/media/logos/logo-5.png')}}">
                                    </a>
                                </div>
                                @yield('content')
                                {{-- <div class="kt-login__signin">
                                    <div class="kt-login__head">
                                        <h3 class="kt-login__title">Sign In To Admin</h3>
                                    </div>
                                    <form class="kt-form" action="">
                                        <div class="input-group">
                                            <input class="form-control" type="text" placeholder="Email" name="email" autocomplete="off">
                                        </div>
                                        <div class="input-group">
                                            <input class="form-control" type="password" placeholder="Password" name="password">
                                        </div>
                                        <div class="row kt-login__extra">
                                            <div class="col">
                                                <label class="kt-checkbox">
                                                    <input type="checkbox" name="remember"> Remember me
                                                    <span></span>
                                                </label>
                                            </div>
                                            <div class="col kt-align-right">
                                                <a href="javascript:;" id="kt_login_forgot" class="kt-login__link">Forget Password ?</a>
                                            </div>
                                        </div>
                                        <div class="kt-login__actions">
                                            <button id="kt_login_signin_submit" class="btn btn-brand btn-pill kt-login__btn-primary">Sign In</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="kt-login__signup">
                                    <div class="kt-login__head">
                                        <h3 class="kt-login__title">Sign Up</h3>
                                        <div class="kt-login__desc">Enter your details to create your account:</div>
                                    </div>
                                    <form class="kt-form" action="">
                                        <div class="input-group">
                                            <input class="form-control" type="text" placeholder="Fullname" name="fullname">
                                        </div>
                                        <div class="input-group">
                                            <input class="form-control" type="text" placeholder="Email" name="email" autocomplete="off">
                                        </div>
                                        <div class="input-group">
                                            <input class="form-control" type="password" placeholder="Password" name="password">
                                        </div>
                                        <div class="input-group">
                                            <input class="form-control" type="password" placeholder="Confirm Password" name="rpassword">
                                        </div>
                                        <div class="row kt-login__extra">
                                            <div class="col kt-align-left">
                                                <label class="kt-checkbox">
                                                    <input type="checkbox" name="agree">I Agree the <a href="#" class="kt-link kt-login__link kt-font-bold">terms and conditions</a>.
                                                    <span></span>
                                                </label>
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                        <div class="kt-login__actions">
                                            <button id="kt_login_signup_submit" class="btn btn-brand btn-pill kt-login__btn-primary">Sign Up</button>&nbsp;&nbsp;
                                            <button id="kt_login_signup_cancel" class="btn btn-secondary btn-pill kt-login__btn-secondary">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="kt-login__forgot">
                                    <div class="kt-login__head">
                                        <h3 class="kt-login__title">Forgotten Password ?</h3>
                                        <div class="kt-login__desc">Enter your email to reset your password:</div>
                                    </div>
                                    <form class="kt-form" action="">
                                        <div class="input-group">
                                            <input class="form-control" type="text" placeholder="Email" name="email" id="kt_email" autocomplete="off">
                                        </div>
                                        <div class="kt-login__actions">
                                            <button id="kt_login_forgot_submit" class="btn btn-brand btn-pill kt-login__btn-primary">Request</button>&nbsp;&nbsp;
                                            <button id="kt_login_forgot_cancel" class="btn btn-secondary btn-pill kt-login__btn-secondary">Cancel</button>
                                        </div>
                                    </form>
                                </div> --}}
                                {{-- <div class="kt-login__account">
                                    <span class="kt-login__account-msg">
                                        Don't have an account yet ?
                                    </span>
                                    &nbsp;&nbsp;
                                    <a href="javascript:;" id="kt_login_signup" class="kt-login__account-link">Sign Up!</a>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
		<!-- end:: Page -->


        <!-- Scripts -->
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            var KTAppOptions = {
                "colors": {
                    "state": {
                        "brand": "#5d78ff",
                        "dark": "#282a3c",
                        "light": "#ffffff",
                        "primary": "#5867dd",
                        "success": "#34bfa3",
                        "info": "#36a3f7",
                        "warning": "#ffb822",
                        "danger": "#fd3995"
                    },
                    "base": {
                        "label": ["#c5cbe3", "#a1a8c3", "#3d4465", "#3e4466"],
                        "shape": ["#f0f3ff", "#d9dffa", "#afb4d4", "#646c9a"]
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
        <script src="{{url('assets/vendors/general/tooltip.js/dist/umd/tooltip.min.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/vendors/general/sticky-js/dist/sticky.min.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/vendors/general/wnumb/wNumb.js')}}" type="text/javascript"></script>
        <!--end:: Global Mandatory Vendors -->

        <!--begin:: Global Optional Vendors -->
            <script src="{{url('assets/vendors/general/owl.carousel/dist/owl.carousel.js')}}" type="text/javascript"></script>
        <!--end:: Global Optional Vendors -->

        <!--begin::Global Theme Bundle(used by all pages) -->
        <script src="{{url('assets/js/demo1/scripts.bundle.js')}}" type="text/javascript"></script>

        <!--end::Global Theme Bundle -->

        <!--begin::Page Vendors(used by this page) -->


		<!--end:: Global Mandatory Vendors -->



        <!--end::Page Vendors -->

        <!--begin::Page Scripts(used by this page) -->
        <script src="{{url('assets/js/demo1/pages/dashboard.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/js/demo1/pages/login/login-general.js')}}" type="text/javascript"></script>
        @yield('js')

	</body>

	<!-- end::Body -->
</html>
