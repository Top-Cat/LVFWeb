<?php

function s($v) {
	return $v;
}

function transformWildcards($v) {
	return str_replace(array("*", "_"), array("(.*)", "(.)"), $v);
}

function a($arr, $key) {
	if (isset($arr[$key])) {
		return $arr[$key];
	}
	return "";
}

function doCurl($request_str, $info_str = "", $explode = true) {
	$time = microtime(true);
	debug::warn($info_str . $request_str);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, str_replace("%", "", $request_str) );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$response = curl_exec($ch);
	if ($explode) {
		$response = explode("\n", $response);
	}
	curl_close($ch);
	output::addTime('curl', microtime(true) - $time);
	return $response;
}

function query($collection, $action, $data = array(), $debug = false, $slaveOk = true) {
	global $m;

	$time = microtime(true);
	$newType = $slaveOk ? MongoClient::RP_NEAREST : MongoClient::RP_PRIMARY;
	if ($m->getReadPreference()['type'] != $newType) {
		$m->setReadPreference($newType);
	}

	$col = getDB()->{$collection};
	$res = call_user_func_array(array($col, $action), $data);
	$m->setReadPreference(MongoClient::RP_NEAREST);

	if ($debug) {
		debug::trace($action . " of " . $collection . " with data " . json_encode($data));
	}
	output::addTime('query', microtime(true) - $time);
	//output::addTime('query_' . $collection . '_' . $action, microtime(true) - $time);
	return $res;
}

function event($ev) {
	stats::event($ev);
}

function timeFunction($func, &$vidarr) {
	$time = microtime(true);
	if (empty($vidarr) && !output::isError()) {
		call_user_func_array($func, array(&$vidarr));
	}
	output::addTime($func, microtime(true) - $time);
}

?>
