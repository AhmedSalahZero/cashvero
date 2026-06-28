<!DOCTYPE html>
<html lang="en">

<head>
    <title>VERO ANALYSIS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="{{ asset('back_login/vendor/bootstrap/css/bootstrap.min.css') }}">
    <!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="{{ asset('back_login/vendor/animate/animate.css') }}">
    <!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="{{ asset('back_login/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('back_login/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('back_login/css/style.css') }}">
    <link rel="shortcut icon" href="{{url('assets/media/logos/logo_va.png')}}" />
    <!--===============================================================================================-->
</head>

<body>
    <!-- <section class="hover"></section> -->
    <div class="dark-overlay big-padding">



        <div class="container">
           
            <div id="headerLogin">
                <img src="{{ asset('back_login/images/logo-white.png') }}" width="300">
                <h1>VERO ANALYSIS </h1>
                <h2>Review Your Buiness Performance</h2>
                <section class="rw-wrapper">
                    <div class="clearfix"></div>
                    <span class="anim-follows">It Is</span>
                    <div class="rw-words rw-words-1">
                        <span>Easy</span>
                        <span>Simple</span>
                        <span>Powerful</span>
                        <span>Accurate</span>
                    </div>

                </section>

            </div>

            @yield('content')

        </div>

        <!-- Video Container -->
        <div id="loginBack">
            <div id="LoginBackInner">
                <div class="bg-animation bgsnow">
                    <div id="stars"></div>
                    <div id="stars2"></div>
                    <div id="stars3"></div>
                    <div id="stars4"></div>
                </div>
            </div>
        </div>


</body>

</html>
