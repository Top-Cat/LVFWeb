<?php

if (user::checkSession()) {
	header("Location: /admin");
	die();
}

if (isset($_POST['pass'])) {
	if (user::login($_POST['user'], $_POST['pass'])) {
		header('Location: /admin');
		die();
	}
}

$title = "Login"; ?>
<div id="hpadding"></div>
<div id="help">
	<form action="" method="POST">
		<label>Username: <input type="text" name="user" /></label>
		<label>Password: <input type="password" name="pass" /></label>
		<button type="submit"><span><i><b></b><u>Login</u></i></span></button>
	</form>
</div>