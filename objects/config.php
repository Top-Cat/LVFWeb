<?php

class config {
	private static $instance;
	public static function &getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new config();
		}
		return self::$instance;
	}
	
	public $lastbatch;
	public $purgeday;
	public $utc_offset;
	public $prevtime;
	
	function __construct() {
		$row = query(
			'lvf_config',
			'findOne',
			array(
				array(),
				array(
					'TFLdataFetch' => 1,
					'ArchiveDate' => 1
				)
			)
		);
		$this->lastbatch = $row['TFLdataFetch'] ;
		//$this->purgeday = $row['ArchiveDate'];
		$this->utc_offset = date('Z') ;
		$this->prevtime = date("U") - 1800;
	}

	private function setNow($field) {
		query(
			'lvf_config',
			'update',
			array(
				array(),
				array(
					'$set' => array(
						$field => new MongoDate()
					)
				)
			)
		);
	}

	function cronStart() {
		setNow('cronstart');
	}

	function updateWhereSeen() {
		setNow('UpdateWhereSeen');
	}

	function dataFetched() {
		$this->lastbatch = new MongoDate();
		setNow('TFLdataFetch');
	}
}

?>