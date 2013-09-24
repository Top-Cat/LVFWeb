<?php

function __autoload($class_name) {
	include '/media/raid/web/londonvf.co.uk/mongo/objects/' . $class_name . '.php';
}

include "passwords.php";
include "functions.php";
include "session.php";

MongoCursor::$timeout = 500;
$m = new MongoClient("mongodb://localhost:27017", array("connectTimeoutMS" => 500, "replicaSet" => "TC_HA", "username" => $mongoUser, "password" => $mongoPass, "db" => "buspics"));
$m->setReadPreference(MongoClient::RP_NEAREST);
$db = $m->buspics;
new session($db->userSessions, 604800);

function getDB() {
	global $db;
	return $db;
}

?>
