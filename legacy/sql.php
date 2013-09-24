<?php

$mysql_host = "localhost";
$database_name = "brian_buspics";
$database_user = $sqlUser;
$user_password = $sqlPass;

$connection = mysql_connect ($mysql_host,$database_user, $user_password) or die ("Cannot make the connection");
$db = @mysql_select_db ($database_name,$connection) or die ("Cannot connect to database");

?>
