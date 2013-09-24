<?php

$minify = false;
$template = true;
include "includes/connect.php";
ob_start();

$action = key($_GET);
$valid_actions = array("help", "stats", "faq", "login", "admin");
if (!in_array($action, $valid_actions)) {
	$action = "home";
}

include "pages/" . $action . ".php";

$body = ob_get_clean();
if ($template) {
	include "content/template.php";
} else {
	print $body;
}
?>
