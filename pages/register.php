<?php

if (user::checkSession()) {
	header("Location: /admin");
	die();
}

include "includes/recaptchalib.php";
$publickey = "6Lc9FOgSAAAAAJW1KKbI79cyC2Plvh6r4o0fZwOM";
$privatekey = "6Lc9FOgSAAAAADtSN1mt1VzGQOfCH8Kh4UKTvRpK";

if (isset($_POST['pass'])) {
	$resp = recaptcha_check_answer($privatekey, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
	if ($resp->is_valid) {
		if ($_POST['pass'] == $_POST['pass2']) {
			user::create($_POST['user'], $_POST['pass']);
			header("Location: /success");
			die();
		} else {
			$err = "Password missmatch";
		}
	} else {
		$err = "Invalid Captcha";
	}
}

$title = "Login"; ?>
<div id="hpadding"></div>
<div id="help">
	<form action="" method="POST">
		<label>Username: <input type="text" name="user" /></label>
		<hr style="display: block" />
		<label>Password: <input type="password" name="pass" /></label>
		<label>Repeat Password: <input type="password" name="pass2" /></label>
		<hr style="display: block" />
		<?php print recaptcha_get_html($publickey); ?>
		<hr style="display: block" />
		<button type="submit"><span><i><b></b><u>Register</u></i></span></button>
	</form>
</div>