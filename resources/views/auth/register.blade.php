<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title>Registration | HC Gaming </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
        integrity="sha512-QrgB7I4Z0ng2+9qTjaaNRe+YrO1tCkHt7vKGnaLQQ5Gv56XU72hj0v3vR3HP0+9AkloFP3LjtVlf/QesJ8v7w=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <meta content="Themesdesign" name="author" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('backend/assets/images/hclogo_white.png')}}">
    <script src="{{ asset('backend/assets/libs/jquery/jquery.min.js')}}"></script>

    <!-- Bootstrap Css -->
    <link href="{{ asset('backend/assets/css/bootstrap.min.css')}}" id="bootstrap-style" rel="stylesheet"
        type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('backend/assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{ asset('backend/assets/css/app.min.css')}}" id="app-style" rel="stylesheet" type="text/css" />
    <style>
        .password-toggle-icon {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }

        .password_confirmation-toggle-icon {
            position: absolute;
            right: 30px;
            top: 60%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>

    <!-- PWA  -->
    <meta name="theme-color" content="#6777ef" />
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="manifest" href="{{ asset('/manifest.json') }}">

    <script src="{{ asset('/sw.js') }}"></script>
    <script>
        if ("serviceWorker" in navigator) {
            // Register a service worker hosted at the root of the
            // site using the default scope.
            navigator.serviceWorker.register("/sw.js").then((reg) => {
                console.log("Service worker has been registered for scope: " + reg.scope);
            }, (error) => {
                console.error(`Service worker registration failed: ${error}`);
            });
        } else {
            console.error("Service workers are not supported.");
        }
    </script>
</head>

@if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<body class="auth-body-bg">
    <div class="bg-overlay"></div>
    <div class="wrapper-page">
        <div class="container-fluid p-0">
            <div class="card">
                <div class="card-body ">
                    @if(session('message'))
                    <div class="alert alert-danger">{{ session('message') }}
                    </div>
                    @endif


                    <div class="text-center mt-4">
                        <div class="mb-3">
                            <a href="{{ route('register') }}" class="auth-logo">
                                <img src="{{ asset('backend/assets/images/hclogo_black.png')}}" height="120"
                                    class="logo-dark mx-auto" alt="">
                                <img src="{{ asset('backend/assets/images/hclogo_white.png')}}" height="120"
                                    class="logo-light mx-auto" alt="">
                            </a>
                        </div>
                    </div>

                    <h4 class="text-muted text-center font-size-18"><b>Register</b></h4>

                    <div class="p-3">
                        <form id="register" onsubmit="return disableButton();" class="form-horizontal mt-3"
                            id="registrationForm" method="POST" action="{{ route('register') }}">
                            @csrf
                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <input class="form-control" id="name" type="text" name="name" required=""
                                        placeholder="Name">
                                </div>
                            </div>

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <input class="form-control" id="username" type="text" name="username" required=""
                                        placeholder="Username">
                                </div>
                                <div id="usernameValidationMessage" class="alert alert-danger" style="display: none;">
                                </div>
                                <div id="usernameValidationDuplicateMessage" class="alert alert-danger"
                                    style="display: none;"></div>
                                <span class="text-muted">Username: No uppercase letters, no spaces. </span>


                            </div>

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <input class="form-control" id="email" type="email" name="email" required=""
                                        placeholder="Email">
                                </div>
                                <div id="emailValidationMessage" class="alert alert-danger" style="display: none;">
                                </div>
                            </div>



                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <input class="form-control" id="password" type="password" name="password"
                                        required="" placeholder="Password">
                                    <span class="password-toggle-icon" onclick="togglePasswordVisibility()"><i
                                            class="fa fa-eye"></i></span>

                                </div>
                                <div id="passwordValidationMessage" class="alert alert-danger" style="display: none;">
                                </div>
                                <span class="text-muted">Create an 8+ character password: 1 lowercase, 1 uppercase, 1
                                    digit.</span>

                            </div>



                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <input class="form-control" id="password_confirmation" type="password"
                                        name="password_confirmation" required placeholder="Password Confirmation">
                                    <span class="password_confirmation-toggle-icon"
                                        onclick="toggleConfirmPasswordVisibility()"><i class="fa fa-eye"></i></span>
                                </div>
                                <div id="passwordConfirmationValidationMessage" class="alert alert-danger"
                                    style="display: none;"></div>

                            </div>
                                                        <div class="form-group mb-3 row">
                                <div class="col-12">

  <select id="role" name="user_role" class="form-control" required>
        <option value="">-- Select Role --</option>
        @foreach($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </select>

</div>
</div>

                            <div class="form-group mb-3 row">
                                <div class="col-12">
                                    <div id="referralCodeContainer">
                                        <input type="hidden" name="referral_code" id="referral_code_hidden"
                                            value="{{ isset($referralCodeParam) ? $referralCodeParam : '' }}">

                                        <input class="form-control" id="referral_code" type="text" name="referral_code"
                                            placeholder="Referral Code (Optional)">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group text-center row mt-3 pt-1">
                                <div class="col-12">
                                    <button id="submitButton" class="btn btn-info w-100 waves-effect waves-light"
                                        type="submit">Register</button>
                                </div>
                            </div>

                            <div class="form-group mt-2 mb-0 row">
                                <div class="col-12 mt-3 text-center">
                                    <a href="{{ route('login') }}" class="text-muted">Already have account?</a>
                                </div>
                            </div>
                        </form>
                        <!-- end form -->
                    </div>
                </div>
                <!-- end cardbody -->
            </div>
            <!-- end card -->
        </div>
        <!-- end container -->
    </div>
    <!-- end -->
</body>


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
// Function to update the password validation message
   // Function to update the password validation message
   function updatePasswordValidationMessage() {
        const passwordInput = document.getElementById('password');
        const passwordValidationMessage = document.getElementById('passwordValidationMessage');
        const passwordValue = passwordInput.value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;

        if (!regex.test(passwordValue) || passwordValue.length < 8) {
            // Show an error message if the password does not meet the requirements
            if (!regex.test(passwordValue)) {
                passwordValidationMessage.innerText = 'Password should contain at least one lowercase letter, one uppercase letter, and one digit.';
            } else {
                passwordValidationMessage.innerText = 'The password must be at least 8 characters.';
            }
            passwordValidationMessage.style.display = 'block'; // Show the message
        } else {
            // Clear the error message if the password is valid
            passwordValidationMessage.innerText = '';
            passwordValidationMessage.style.display = 'none'; // Hide the message
        }
    }

    // Attach the updatePasswordValidationMessage function to the input event of the password field
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        passwordInput.addEventListener('input', updatePasswordValidationMessage);
    });



         // Function to update the username validation message
         function updateUsernameValidationMessage() {
    const usernameInput = document.getElementById('username');
    const usernameValidationMessage = document.getElementById('usernameValidationMessage');
    const usernameValue = usernameInput.value.trim(); // Remove leading and trailing spaces
    const regex = /^[a-z0-9_]+$/; // Allow only lowercase letters, digits, and underscores

    if (usernameValue === '') {
        // Clear the error message if the field is empty
        usernameValidationMessage.innerText = '';
        usernameValidationMessage.style.display = 'none'; // Hide the message
    } else if (!regex.test(usernameValue)) {
        // Show an error message if the username does not meet the requirements
        usernameValidationMessage.innerText = 'The username should not include uppercase letters or spaces.';
        usernameValidationMessage.style.display = 'block'; // Show the message
    } else {
        // Clear the error message if the username is valid
        usernameValidationMessage.innerText = '';
        usernameValidationMessage.style.display = 'none'; // Hide the message
    }
}

// Attach the updateUsernameValidationMessage function to the "input" event of the username field
const usernameInput = document.getElementById('username');
usernameInput.addEventListener('input', updateUsernameValidationMessage);
   // JavaScript code to check for duplicate email
   function checkDuplicateEmail() {
            const emailInput = document.getElementById('email');
            const emailValidationMessage = document.getElementById('emailValidationMessage');
            const emailValue = emailInput.value;

            // Perform AJAX request to check if the email is already taken
            fetch('/check-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ email: emailValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    emailValidationMessage.innerText = 'The email address is already taken.';
                    emailValidationMessage.style.display = 'block'; // Show the message
                } else {
                    emailValidationMessage.innerText = '';
                    emailValidationMessage.style.display = 'none'; // Hide the message
                }
            });
        }

        // Attach the checkDuplicateEmail function to the input event of the email field
        document.addEventListener('DOMContentLoaded', function () {
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('input', checkDuplicateEmail);
        });


           // JavaScript code to check for duplicate username
 // JavaScript code to check for duplicate username
 function checkDuplicateUsername() {
            const usernameInput = document.getElementById('username');
            const usernameValidationDuplicateMessage = document.getElementById('usernameValidationDuplicateMessage');
            const usernameValue = usernameInput.value;

            // Perform AJAX request to check if the username is already taken
            fetch('/check-username', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ username: usernameValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    usernameValidationDuplicateMessage.innerText = 'The username is already taken.';
                    usernameValidationDuplicateMessage.style.display = 'block'; // Show the message
                } else {
                    usernameValidationDuplicateMessage.innerText = '';
                    usernameValidationDuplicateMessage.style.display = 'none'; // Hide the message
                }
            });
        }

        // Attach the checkDuplicateUsername function to the input event of the username field
        document.addEventListener('DOMContentLoaded', function () {
            const usernameInput = document.getElementById('username');
            usernameInput.addEventListener('input', checkDuplicateUsername);
        });

// Function to update the password confirmation validation message
function updatePasswordConfirmationValidationMessage() {
    const passwordInput = document.getElementById('password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const passwordConfirmationValidationMessage = document.getElementById('passwordConfirmationValidationMessage');
    const passwordValue = passwordInput.value;
    const passwordConfirmationValue = passwordConfirmationInput.value;

    if (passwordConfirmationValue === '') {
        // Clear the error message if the field is empty
        passwordConfirmationValidationMessage.innerText = '';
        passwordConfirmationValidationMessage.style.display = 'none'; // Hide the message
    } else if (passwordValue !== passwordConfirmationValue) {
        // Show an error message if the password confirmation does not match the password
        passwordConfirmationValidationMessage.innerText = 'Password confirmation does not match the password.';
        passwordConfirmationValidationMessage.style.display = 'block'; // Show the message
    } else {
        // Clear the error message if the password confirmation matches the password
        passwordConfirmationValidationMessage.innerText = '';
        passwordConfirmationValidationMessage.style.display = 'none'; // Hide the message
    }
}

// Attach the updatePasswordConfirmationValidationMessage function to the "input" event of the password confirmation field
const passwordConfirmationInput = document.getElementById('password_confirmation');
passwordConfirmationInput.addEventListener('input', updatePasswordConfirmationValidationMessage);

function togglePasswordVisibility() {
    var passwordInput = document.getElementById("password");
    var icon = document.querySelector(".password-toggle-icon i");

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

function toggleConfirmPasswordVisibility() {
    var confirmPasswordInput = document.getElementById("password_confirmation");
    var icon = document.querySelector(".password_confirmation-toggle-icon i");

    if (confirmPasswordInput.type === "password") {
        confirmPasswordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        confirmPasswordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

var formSubmitted = false;

function disableButton() {
    if (formSubmitted) {
        return false;
    }

    var submitButton = document.getElementById('submitButton');
    submitButton.disabled = true;
    submitButton.innerHTML = 'Processing...'; // Change the text of the button to show a loading effect

    // Submit the form after a short delay (e.g., 0.5 seconds) to give the disabled visual effect
    setTimeout(function () {
        document.getElementById('submitproduct').submit();
    }, 500);

    formSubmitted = true;
    return true;
}


$(document).ready(function() {
        // Function to get URL parameters by name
        function getURLParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Check if referral_code parameter exists in the URL
        const referralCodeParam = getURLParameter('referral_code');

        if (referralCodeParam) {
            // If referral_code parameter is present, pre-fill the referral code field with its value
            const referralCodeInput = document.getElementById('referral_code');
            referralCodeInput.value = referralCodeParam;

            // Disable the referral code field since it's pre-filled
            referralCodeInput.disabled = true;

            // Hide the Referral Code input field container
            document.getElementById('referralCodeContainer').style.display = 'none';

            // Set the value of the hidden input field to the referral code
            const referralCodeHiddenInput = document.getElementById('referral_code_hidden');
            referralCodeHiddenInput.value = referralCodeParam;
        }
    });

</script>
