<?php
if (isset($_SESSION[PLATFORM_IDENTIFIER]["login_verified"]) && $_SESSION[PLATFORM_IDENTIFIER]["login_verified"] == "1") {

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

<main class="form-signin col-6 col-lg-4 m-auto mt-5">
    <form method="post" class="classid_form text-center">


        <h1 class="h1 mb-3 fw-light headline">Welcome to <?php echo PLATFORM_NAME; ?></h1>

        <div class="form-floating mb-2">
            <input name="login" type="text" class="form-control" id="floatingInput" placeholder="Username">
            <label for="floatingInput">Username</label>
        </div>
        <div class="form-floating">
            <input name="password" type="password" class="form-control" id="floatingPassword"
                   placeholder="Password">
            <label for="floatingPassword">Password</label>
        </div>

        <!-- <div class="form-check text-start my-3">
          <input class="form-check-input" type="checkbox" value="remember-me" id="flexCheckDefault">
          <label class="form-check-label" for="flexCheckDefault">
            Remember me
          </label>
        </div> -->

        <div id="container_login" class="col-12 p-3 text-danger">
            &nbsp;
        </div>

        <button class="classid_form_action btn btn-lg btn-primary w-100 py-2" data-action="doLogin">Sign in</button>
        <br>
        <br>
        <small>By continuing, you agree to cybob's <a href="https://www.cybob.com/datenschutz">Commercial Terms</a> and <a href="https://www.cybob.com/datenschutz">Usage Policy</a>, and acknowledge their <a href="https://www.cybob.com/datenschutz">Privacy Policy</a>. <a href="https://www.cybob.com/impressum">Imprint</a></small>
    </form>
</main>

<script>

    //
    var BASEURL = '<?php echo BASEURL; ?>';


    $(document).on('click', '.classid_form_action', function () {

        //
        var action = $(this).attr("data-action");

        //
        if (action == "doLogin") {

            var tmp = $('.classid_form').serializeArray();

            var url = BASEURL + "controller/address/doLogin/";
            //alert(url);

            $.ajax({
                'url': url,
                'type': 'POST',
                'data': tmp,
                'success': function (result) {

                    alert(result);

                    if (result.response == "success") {
                        window.location.reload(true);
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