<?php


if (isset($_SESSION[PLATFORM_IDENTIFIER]["login_verified"]) && $_SESSION[PLATFORM_IDENTIFIER]["login_verified"] == "1") {
    //
} else {
?>

        <style>
            .headline {
                background-image:linear-gradient(90deg,yellow,red,purple);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                font-weight: 400;
                /*font-size: 4em;*/
            }
        </style>

<main class="form-signin col-10 col-lg-4 m-auto mt-5">
    <form method="post" class="classid_form">


        <h1 class="h1 mb-4 fw-light text-center headline">Welcome to <?php echo PLATFORM_NAME; ?></h1>

        <div class="form-floating mb-2">
            <input name="login" type="text" class="form-control" id="floatingInput" placeholder="Enter your personal or work email">
            <label for="floatingInput">Enter your personal or work email</label>
        </div>

        <div class="form-floating classid_input_password" style="display: none;">
            <input name="password" type="password" class="form-control" id="floatingPassword"
                   placeholder="Password">
            <label for="floatingPassword">Password</label>
        </div>


        <div id="container_login" class=" col-12 p-3 text-center text-danger" style="height: 60px;">
            &nbsp;
        </div>

        <button class="classid_form_action btn btn-primary btn-lg w-100 py-2" data-action="doLogin">Continue with email</button>
        <br>

        <div class="w-100 text-center mt-3 small">
            <small>By continuing, you agree to cybob's <a href="https://www.cybob.com/datenschutz">Commercial Terms</a> and <a href="https://www.cybob.com/datenschutz">Usage Policy</a>, and acknowledge their <a href="https://www.cybob.com/datenschutz">Privacy Policy</a>. <a href="https://www.cybob.com/impressum">Imprint</a></small>
        </div>

    </form>
</main>

<script>

    $(document).on('click', '.classid_form_action', function () {

//
        var action = $(this).attr("data-action");

//
        if (action == "doLogin") {

            $("#container_login").html("");


            var tmp = $('.classid_form').serializeArray();

            var url = BASEURL + "controller/console/doLogin/";

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'success': function (result) {

                    //alert(result);

                    if (result.response == "success") {
                        window.location.reload(true);
                    } else  if (result.response == "showPassword") {
                        //alert("showPassword");
                        $(".classid_input_password").css("display", "block");
                        $("#floatingPassword").focus();
                        $(".classid_form_action").html("Sign in");
                    } else {
                        $("#container_login").html(result.description);
                    }

                }
            });

            return false; // no submit of form

        }

    });

</script>

<?php
}
?>