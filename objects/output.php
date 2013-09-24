<?php

class output {
	private static $instance;
	private static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new output();
		}
		return self::$instance;
	}

	private $version = 1;
	
	private $success = true;
	private $error = "";
	
	private $response = array('lines' => array(), 'extra' => array());
	private $request = array();
	
	private $doTiming = true;
	private $times = array('query' => 0, 'curl' => 0);
	
	public static function setError($msg) {
		self::getInstance()->success = false;
		self::getInstance()->error = $msg;
	}
	
	public static function &getRequest() {
		return self::getInstance()->request;
	}
	
	public static function setRequest($request) {
		return self::getInstance()->request = $request;
	}
	
	public static function &getResponse() {
		return self::getInstance()->response;
	}
	
	public static function isError() {
		return !self::getInstance()->success;
	}
	
	public static function addTime($name, $time) {
		$inst = self::getInstance();
		if ($inst->doTiming) {
			if (!isset($inst->times[$name])) {
				$inst->times[$name] = 0;
			}
			$inst->times[$name] += $time;
		}
	}
	
	public static function printOutput() {
		$inst = self::getInstance();
		$final = array(
			'version' => $inst->version,
			'success' => $inst->success,
			'error' => $inst->error,
			'response' => $inst->response,
			'request' => $inst->request
		);
		if ($inst->doTiming) {
			$final['response']['times'] = array();
			ksort($inst->times);
			foreach ($inst->times as $key => $time) {
				$final['response']['times'][$key] = number_format($time, 3);
			}
		}
		print json_encode($final);
	}
}

?>