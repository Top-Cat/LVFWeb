<?php

class debug {
	private static $cLevel = 1;
	
	public static function info($message) {
		self::dodebug($message, 0, true);
	}
	
	public static function trace($message) {
		self::dodebug($message, 4, false);
	}
	
	public static function warn($message) {
		self::dodebug($message, 3, false);
	}
	
	public static function error($message) {
		self::dodebug($message, 2, false);
	}
	
	public static function fatal($message) {
		self::dodebug($message, 1, false);
	}
	
	private static function dodebug($message, $level, $both) {
		if ($level < self::$cLevel) {
			query(
				'lvf_audit',
				'insert',
				array(
					array(
						'text' => $message,
						'level' => $both
					)
				)
			);
		}
	}
}

?>