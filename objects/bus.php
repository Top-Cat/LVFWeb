<?php

class bus {
	private $vid;
	private $reg;
	private $fnum;
	private $note = '';

	private $history = array();
	private $predictions = array();
	private $sort = array();

	private static $bus = array();
	private static $queue = array();

	public static function getFromVid($vid) {
		if (isset(self::$bus[$vid])) {
			return self::$bus[$vid];
		}
		try {
			return self::$bus[$vid] = new bus($vid);
		} catch (MongoException $e) {
			return false;
		}
	}

	public static function checkQueue() {
		asort(self::$queue);
		$l = 0;
		print date("U") . " -\r";
		while (current(self::$queue) < date("U") && count(self::$queue) > 0) {
			print date("U") . " " . ++$l . "\r";
			$bus = self::getFromVid(key(self::$queue));
			$bus->updateQueue();
		}
		print date("U") . " +\r";
	}

	function __construct($vid) {
		$this->vid = $vid;
		$row = query(
			'lvf_vehicles',
			'findOne',
			array(
				array(
					'vid' => $this->vid
				),
				array(
					'cur_reg' => 1,
					'fnum' => 1,
					'note' => 1
				)
			)
		);
		$c = query(
			'lvf_history',
			'find',
			array(
				array(
					'vid' => $this->vid
				)
			)
		);
		while ($c->hasNext()) {
			$r = $c->getNext();
			$this->history[date('Y-m-d', $r['date']->sec)][$r['route']][$r['lineid']]['last_seen'] = $r['last_seen'];
			$this->history[date('Y-m-d', $r['date']->sec)][$r['route']][$r['lineid']]['first_seen'] = $r['first_seen'];
		}
		$this->reg = $row['cur_reg'];
		$this->fnum = $row['fnum'];
		if (isset($row['note'])) {
			$this->note = $row['note'];
		}
	}

	function updateQueue() {
		array_multisort($this->sort, $this->predictions);
		while (count($this->sort) > 0 && current($this->sort) < date("U")) {
			array_shift($this->sort);
			array_shift($this->predictions);
		}
		if (count($this->sort) > 0) {
			if (!isset(self::$queue[$this->vid]) || self::$queue[$this->vid] != current($this->sort)) {
				self::$queue[$this->vid] = current($this->sort);
				query(
					'lvf_vehicles',
					'update',
					array(
						array(
							'vid' => $this->vid
						),
						array(
							'$set' => array(
								'whereseen' => current($this->predictions)
							)
						),
						array(
							'w' => 0
						)
					)
				);
			}
		} else {
			unset(self::$queue[$this->vid]);
		}
	}

	function newData($tfl) {
		self::checkQueue();
		$update = array();

		if (!isset($this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['last_seen']) || $this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['last_seen']->sec < $tfl->time) {
			$this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['last_seen'] = $tfl->mongotime;
			$update['last_seen'] = $tfl->mongotime;
			$update['route'] = $tfl->route;
		}
		if (!isset($this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['first_seen']) || $this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['first_seen']->sec > $tfl->time) {
			$this->history[$tfl->keytime][$tfl->route][$tfl->lineid]['first_seen'] = $tfl->mongotime;
			$update['first_seen'] = $tfl->mongotime;
			$update['route'] = $tfl->route;
		}
		if ($tfl->keytime != date("Y-m-d", strtotime('today', $tfl->time))) {
			print $tfl->keytime . ", " . date("Y-m-d", strtotime('today', $tfl->time));
		}
		if (count($update) > 0) {
			query(
				'lvf_history',
				'update',
				array(
					array(
						'vid' => $this->vid,
						'date' => new MongoDate(strtotime('today', $tfl->time)),
						'lineid' => $tfl->lineid
					),
					array(
						'$set' => $update
					),
					array(
						'w' => 0,
						'upsert' => 1
					)
				)
			);
		}

		$this->predictions[$tfl->stop] = array(
			'route' => $tfl->route,
			'line_id' => $tfl->lineid,
			'last_seen' => $tfl->mongotime,
			'nearest_stop' => $tfl->stop,
			'dirid' => $tfl->dirid,
			'destination' => $tfl->dest
		);
		$this->sort[$tfl->stop] = $tfl->time;

		$this->updateQueue();
	}

	function getFleetNumber() {
		return $this->fnum;
	}

	function getCurrentRegistration() {
		return $this->reg;
	}

	function getNote() {
		return $this->note;
	}
}

?>
