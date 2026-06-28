@extends('layouts.dashboard')
@section('css')
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="{{url('assets/css/demo1/pages/error/error-1.css')}}">


@endsection
@section('content')


		<!-- begin:: Page -->
		<div class="kt-grid kt-grid--ver kt-grid--root" style="height: 100%">
			<div class="kt-grid__item kt-grid__item--fluid kt-grid  kt-error-v1" style="background-image: url(./assets/media//error/bg1.jpg);">
				<div class="kt-error-v1__container">
					<h1 class="kt-error-v1__number">No Data</h1>
					<p class="kt-error-v1__desc">
						{{$message['msg']}}
					</p>
				</div>
			</div>
		</div>

		<!-- end:: Page -->


@endsection

