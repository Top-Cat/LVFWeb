<?php

class tfl {

	public $type;
	public $stop;
	public $visit;
	public $lineid;
	public $route;
	public $dirid;
	public $dest;
	public $vid;
	public $reg;
	public $mongotime;
	public $keytime;
	public $time;

	public $bus;

	function __construct($json) {
		$this->type = $json[0];
		if ($this->type == 1) {
			list( , $tempstop, $this->visit, $this->lineid, $this->route, $this->dirid, $this->dest, $this->vid, $this->reg, $temptime) = $json;
			$this->bus = bus::getFromVid($this->vid);
			$this->stop = intval($tempstop);
			$this->time = $temptime / 1000;
			$this->mongotime = new MongoDate($this->time);
			$this->keytime = date("Y-m-d", $this->time);
		}
	}

}

?>