<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title>{{ $module === 'tradinghub' ? 'TradingHub' : ($module === 'dealership' ? 'Dealership Ecommerce' : 'HC Gaming') }} Sign In | HC Gaming</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Themesdesign" name="author" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('backend/assets/images/hclogo_white.png')}}">

    <!-- Bootstrap Css -->
    <link href="{{ asset('backend/assets/css/bootstrap.min.css')}}" id="bootstrap-style" rel="stylesheet"
        type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('backend/assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{ asset('backend/assets/css/app.min.css')}}" id="app-style" rel="stylesheet" type="text/css" />
    <link href="{{ asset('backend/assets/css/module-auth.css') }}" rel="stylesheet" />


    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">

    <!-- PWA  -->
    <meta name="theme-color" content="#6777ef" />
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="manifest" href="{{ asset('/manifest.json') }}">

</head>

<style>
    /* Style for the password visibility toggle icon */
    .password-toggle-icon {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
    }
</style>
@if (session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

<body class="auth-body-bg module-auth-page">
    <div class="bg-overlay"></div>
    <div class="wrapper-page">
        <div class="container-fluid p-0">
            <div class="card">
                <div class="card-body">
                    @if(session('message'))
                    <div class="alert alert-danger">{{ session('message') }}
                    </div>
                    @endif

                    <div class="text-center mt-4">
                        <div class="mb-3">
                            <a href="{{ route('login') }}" class="auth-logo">
                                <img src="{{ asset('backend/assets/images/hclogo_black.png')}}" height="120"
                                    class="logo-dark mx-auto" alt="">
                                <img src="{{ asset('backend/assets/images/hclogo_white.png')}}" height="120"
                                    class="logo-light mx-auto" alt="">
                            </a>
                        </div>
                    </div>

                    <h4 class="text-muted text-center font-size-18"><b>{{ $module === 'tradinghub' ? 'TradingHub' : ($module === 'dealership' ? 'Dealership Ecommerce' : 'Account') }} Sign In</b></h4>

                    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

                    <div class="p-3">

                        <form class="form-horizontal mt-3" method="POST" action="{{ $module === 'tradinghub' ? route('tradinghub.authenticate') : ($module === 'dealership' ? route('dealership.authenticate') : route('login')) }}" id="login">
                            @csrf

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <label for="username" class="form-label">Username or email</label>
                                    <input class="form-control" id="username" name="username" type="text" required="" value="{{ old('username') }}"
                                        placeholder="Username or email" autocomplete="username">
                                </div>
                            </div>

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="position-relative">
                                    <input class="form-control" id="password" name="password" type="password"
                                        required="" placeholder="Password" autocomplete="current-password">
                                    <button type="button" class="password-toggle-icon" onclick="togglePasswordVisibility()" aria-label="Show password" aria-pressed="false" aria-controls="password"><i
                                            class="fa fa-eye" aria-hidden="true"></i></button>
                                    </div>

                                </div>
                            </div>

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <div class="custom-control custom-checkbox">

                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3 text-center row mt-3 pt-1">
                                <div class="col-12">
                                    <button class="btn btn-info w-100 waves-effect waves-light" id="loginbutton"
                                        type="submit">Log In</button>
                                </div>
                            </div>

                            <div class="form-group mb-0 row mt-2">
                                {{-- <div class="col-sm-7 mt-3">
                                    <a href="{{ route('password.request') }}" class="text-muted"><i
                                            class="mdi mdi-lock"></i> Forgot your password?</a>
                                </div> --}}
                                @if(!$module || \App\Support\ModuleAccess::registrationAllowed($module))<div class="col-sm-5 mt-3">
                                    <a href="{{ route('register') }}" class="auth-register"><i
                                            class="mdi mdi-account-circle"></i> Create an account</a>
                                </div>@endif
                            </div>
                        </form>
                        @include('auth.partials.module-navigation', ['currentModule' => $module])
                    </div>
                    <!-- end -->
                </div>
                <!-- end cardbody -->
            </div>
            <!-- end card -->
        </div>
        <!-- end container -->
    </div>
    <!-- end -->

    <!-- JAVASCRIPT -->
    <script src="{{ asset('backend/assets/libs/jquery/jquery.min.js')}}"></script>
    <script src="{{ asset('backend/assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{ asset('backend/assets/libs/metismenu/metisMenu.min.js')}}"></script>
    <script src="{{ asset('backend/assets/libs/simplebar/simplebar.min.js')}}"></script>
    <script src="{{ asset('backend/assets/libs/node-waves/waves.min.js')}}"></script>

    <script src="{{ asset('backend/assets/js/app.js')}}"></script>

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
        @if(Session::has('message'))
         var type = "{{ Session::get('alert-type','info') }}"
         switch(type){
            case 'info':
            toastr.info(" {{ Session::get('message') }} ");
            break;

            case 'success':
            toastr.success(" {{ Session::get('message') }} ");
            break;

            case 'warning':
            toastr.warning(" {{ Session::get('message') }} ");
            break;

            case 'error':
            toastr.error(" {{ Session::get('message') }} ");
            break;
         }
         @endif


         function togglePasswordVisibility() {
    var passwordInput = document.getElementById("password");
    var icon = document.querySelector(".password-toggle-icon i");
    var toggle = document.querySelector('.password-toggle-icon');
    var visible = passwordInput.type === 'password';
    toggle.setAttribute('aria-pressed', String(visible));
    toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

document.getElementById('login').addEventListener('submit', function () {
    var submitButton = document.getElementById('loginbutton');
    submitButton.disabled = true;
    submitButton.textContent = 'Signing in...';
});
window.addEventListener('pageshow', function () {
    var submitButton = document.getElementById('loginbutton');
    submitButton.disabled = false;
    submitButton.textContent = 'Log In';
});
    </script>
</body>

</html>
