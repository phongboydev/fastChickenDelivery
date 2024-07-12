<!DOCTYPE html>
<html lang="en">
<head>
	<title>Đặt lại mật khẩu</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png"href="{{ asset('fe/images/icons/favicon.ico') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/bootstrap/css/bootstrap.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/fonts/font-awesome-4.7.0/css/font-awesome.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/fonts/iconic/css/material-design-iconic-font.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/animate/animate.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/css-hamburgers/hamburgers.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/animsition/css/animsition.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/select2/select2.min.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/vendor/daterangepicker/daterangepicker.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/css/util.css') }}">
	<link rel="stylesheet" type="text/css" href="{{ asset('fe/css/main.css') }}">
</head>
<body>
	<div class="limiter">
		<div class="container-login100" style="background-image: url({{ asset('fe/images/bg-01.jpg')}});">
			<div class="wrap-login100 p-l-55 p-r-55 p-t-65 p-b-54">
				<form class="login100-form validate-form" method="POST" action="{{ URL::to(route('update_password')) }}">
                    {!! csrf_field() !!}
                    <input type="hidden" name="token" value="{{ $request->remember_token }}">
                    <input type="hidden" name="email" value="{{ $request->email }}">
					<span class="login100-form-title p-b-49">Đặt lại mật khẩu </span>
					<div class="wrap-input100 validate-input m-b-23" data-validate = "Password is required">
						<span class="label-input100">Mật khẩu</span>
						<input class="input100" type="password" name="password" placeholder="Nhập mật khẩu">
						<span class="focus-input100" data-symbol="&#xf190;"></span>
					</div>
                    <div class="wrap-input100 validate-input m-b-23" data-validate = "Comfirm password is required">
						<span class="label-input100">Nhập lại mật khẩu</span>
						<input class="input100" type="password" name="confirm_password" placeholder="Nhập xác nhận mật khẩu">
						<span class="focus-input100" data-symbol="&#xf190;"></span>
					</div>
                    @if (session('message'))
                        <p>{{ session('message') }}</p>
                    @endif
					<div class="container-login100-form-btn">
						<div class="wrap-login100-form-btn">
							<div class="login100-form-bgbtn"></div>
							<button class="login100-form-btn"> Xác nhận </button>
						</div>
					</div>
					<div class="flex-col-c p-t-155">
                        <a href="{{ URL::to(route('screen_register')) }}">Đăng nhập</a>
					</div>
				</form>
			</div>
		</div>
	</div>
	<div id="dropDownSelect1"></div>
	<script src="{{ asset('fe/vendor/jquery/jquery-3.2.1.min.js') }}"></script>
	<script src="{{ asset('fe/vendor/animsition/js/animsition.min.js') }}"></script>
	<script src="{{ asset('fe/vendor/bootstrap/js/popper.js') }}"></script>
	<script src="{{ asset('fe/vendor/bootstrap/js/bootstrap.min.js') }}"></script>
	<script src="{{ asset('fe/vendor/select2/select2.min.js') }}"></script>
	<script src="{{ asset('fe/vendor/daterangepicker/moment.min.js') }}"></script>
	<script src="{{ asset('fe/vendor/daterangepicker/daterangepicker.js') }}"></script>
	<script src="{{ asset('fe/vendor/countdowntime/countdowntime.js') }}"></script>
	<script src="{{ asset('fe/js/main.js') }}"></script>
</body>
</html>