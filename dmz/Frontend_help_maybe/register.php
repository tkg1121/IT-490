<?php
require(__DIR__ . "/../../partials/nav.php");
require(__DIR__ . "/../../lib/render_functions.php");
reset_session();
?>
<div class="container-fluid">
<form onsubmit="return validate(this)" method="POST">
    <?php render_input(["type"=>"email", "id"=>"email", "name"=>"email", "label"=>"Email", "rules"=>["required"=>true]]);?>
    <?php render_input(["type"=>"text", "id"=>"username", "name"=>"username", "label"=>"Username", "rules"=>["required"=>true, "maxlength"=>30]]);?>
    <?php render_input(["type"=>"password", "id"=>"password", "name"=>"password", "label"=>"Password", "rules"=>["required"=>true, "minlength"=>8]]);?>
    <?php render_input(["type"=>"password", "id"=>"confirm", "name"=>"confirm", "label"=>"Confirm Password", "rules"=>["required"=>true,"minlength"=>8]]);?>
    <?php render_button(["text"=>"Register", "type"=>"submit"]);?>
</form>
</div>
<script>
/*Open dev tools on the page you want to disable (works for login, register, profile) 
go to the console tab and paste the below code, press enter, it'll disable the html validation until the page reloads.
This is a self executing function so it runs immediately */
(function disableHTML()
    {
        //debugger;
        let form = document.forms[0];
        if(form.email){
            form.email.removeAttribute("required");
            form.email.type="text"
        }
        if (form.username){
            form.username.removeAttribute("required");
            form.username.removeAttribute("minlength");
            form.username.removeAttribute("maxlength");
        }
        let ps = form.querySelectorAll("[type=password]");
        for(let p of ps){
            p.removeAttribute("required");
            p.removeAttribute("minlength");
            p.removeAttribute("maxlength");
        }
    })();

    function validate(form) {
        //TODO 1: implement JavaScript validation
        //ensure it returns false for an error and true for success

        let email = form.email.value;
        let username = form.username.value;
        let password = form.password.value;
        let con = form.confirm.value;
        let isValid = true;

        //email
        if (!validateEmail(email))
        {
            flash("Invalid email address", "danger");
            isValid = false;
        }

        //username
        if (!validateUsername(username))
        {
            flash("Username must only contain 3-16 characters a-z, 0-9, _, or -", "danger");
            isValid = false;
        }

        //password
        if (!validatePassword(password))
        {
            flash("Password must be at least 8 characters long", "danger");
            isValid = false;
        }

        //confirm
        if (password !== con)
        {
            flash("Passwords must match", "danger");
            isValid = false;
        }

        return true;
    }
</script>
<?php
//TODO 2: add PHP Code
if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["confirm"]) && isset($_POST["username"])) {
    $email = se($_POST, "email", "", false);
    $password = se($_POST, "password", "", false);
    $confirm = se($_POST, "confirm", "", false);
    $username = se($_POST, "username", "", false);
    //TODO 3
    $hasError = false;
    if (empty($email)) {
        flash("Email must not be empty", "danger");
        $hasError = true;
    }
    //sanitize
    $email = sanitize_email($email);
    //validate
    if (!is_valid_email($email)) {
        flash("Invalid email address", "danger");
        $hasError = true;
    }
    if (!is_valid_username($username)) {
        flash("Username must only contain 3-16 characters a-z, 0-9, _, or -", "danger");
        $hasError = true;
    }
    if (empty($password)) {
        flash("password must not be empty", "danger");
        $hasError = true;
    }
    if (empty($confirm)) {
        flash("Confirm password must not be empty", "danger");
        $hasError = true;
    }
    if (!is_valid_password($password)) {
        flash("Password too short", "danger");
        $hasError = true;
    }
    if (
        strlen($password) > 0 && $password !== $confirm
    ) {
        flash("Passwords must match", "danger");
        $hasError = true;
    }
    if (!$hasError) {
        //TODO 4
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO Users (email, password, username) VALUES(:email, :password, :username)");
        try {
            $stmt->execute([":email" => $email, ":password" => $hash, ":username" => $username]);
            flash("Successfully registered!", "success");
        } catch (PDOException $e) {
            users_check_duplicate($e->errorInfo);
        }
    }
}
?>
<?php
require(__DIR__ . "/../../partials/flash.php");
?>