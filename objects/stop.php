<?php

class stop {

	private static $stops = array();

	public static function getFromId($stopid) {
		if (!isset(self::$stops[$stopid])) {
			$row = query('lvf_stops', 'findOne', array(array('_id' => $stopid)));
			if (!is_null($row)) {
				self::$stops[$stopid] = $row['name'];
			} else {
				self::$stops[$stopid] = "unknown";
			}
		}
		return self::$stops[$stopid];
	}

}

?>