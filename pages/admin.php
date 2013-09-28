<?php

$title = "Admin";

if (!user::checkSession()) {
	header("Location: /login");
	die();
}

$valid_actions = array("logs", "vehicle", "lists", "destinations", "stops", "history", "stats");
next($_GET);
$action = key($_GET);
if (!in_array($action, $valid_actions)) {
	$action = "logs";
}

if (isset($_GET['ajax'])) {
	$template = false;
	include "admin/" . $action . ".php";
} else {
$header = '<script src="content/admin.js"></script><script>cur = "' . $action . '";</script>';
?><div id="hpadding"></div>
<div id="help">
	<div id="anav"><?php
foreach ($valid_actions as $val) {
	?>
		<a<?php print ($action == $val ? ' class="sel"' : ''); ?> onclick="return show('<?php print $val; ?>')" id="nav_<?php print $val; ?>" href="/admin?<?php print $val; ?>"><?php print ucwords($val); ?></a><?php
}
?></div><?php
foreach ($valid_actions as $val) {
	?><div style="text-align: center<?php print ($action == $val ? '' : '; display: none'); ?>" id="<?php print $val; ?>"><?php
	include "admin/" . $val . ".php";
	?></div><?php
}
?></div><?php
}

?>