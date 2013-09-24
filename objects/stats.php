<?php

class stats {
	private static $events = array();
	private static $done = false;

	public static function event($type) {
		if (isset($events[$type])) {
			self::$events[$type]++;
		} else {
			self::$events[$type] = 1;
		}
	}

	public static function finalise() {
		if (!self::$done) {
			query(
				'lvf_suggest',
				'update',
				array(
					array(
						'query' => strtoupper($_GET['reg'])
					),
					array(
						'$inc' => array(
							'count' => 1
						),
						'$set' => array(
							'error' => output::isError()
						)
					),
					array(
						'w' => 0,
						'upsert' => true
					)
				)
			);
			//self::$events['country.' . $_SERVER['GEOIP_COUNTRY'] . '.' . $_SERVER['GEOIP_CITY']] = 1;
			query(
				'lvf_stats',
				'update',
				array(
					array(
						'date' => new MongoDate(strtotime("today"))
					),
					array(
						'$inc' => self::$events
					),
					array(
						'w' => 1,
						'upsert' => true
					)
				)
			);
			self::$done = true;
		}
	}
}

?>