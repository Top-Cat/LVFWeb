<?php

$title = "Admin";

if (!user::checkSession()) {
	header("Location: /login");
	die();
}

$valid_actions = array("vehicle", "lists", "logs");

next($_GET);
$action = key($_GET);
if (!in_array($action, $valid_actions)) {
	$action = "logs";
}

$anav = '<div id="hpadding"></div>
<div id="help">
	<div id="anav">';
foreach ($valid_actions as $val) {
	$anav .= '
		<a' . ($action == $val ? ' class="sel"' : '') . ' href="/admin/' . $val . '">' . ucwords($val) . '</a>';
}
$anav .= '
	</div>';

include "admin/" . $action . ".php";

?>