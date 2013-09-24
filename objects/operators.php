<?php

class operators {
	private static $ops = array();

	public static function getOperatorName($code) {
		if (isset(self::$ops[$code])) {
			return self::$ops[$code];
		}
		$row = query('lvf_operators', 'findOne', array(array('operator_code' => $code), array('_id' => 0, 'operator_name' => 1)));
		self::$ops[$code] = is_null($row) ? "Unknown" : $row['operator_name'];
		return self::$ops[$code];
	}
}

?>