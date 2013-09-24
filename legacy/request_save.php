<?php

include "connect.php";
include "getvehicleinfo.php";
include "check_vehicle.php" ;

if (!isset($_GET['reg'])) {
	die();
}

class config {
	private static $instance;
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new config();
		}
		return self::$instance;
	}
	
	public $lastbatch;
	public $utc_offset;
	public $prevtime;
	
	function __construct() {
		$result = query("SELECT TFLdataFetch, date_offset FROM lvf_config", $data);
		$row = mysql_fetch_assoc($result) ;
		$this->lastbatch = $row['TFLdataFetch'] ;
		$this->utc_offset = $row['date_offset'] ;
		$this->prevtime = date( "H:i:s ", date("U") - 2400 ) ;
	}
}

class debug {
	private static $cLevel = 2;
	
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
			query("INSERT INTO lvf_audit_audit ( Audit_text ) VALUES ('" . s($message) . "')", $data);
			if ($both) {
				query("INSERT INTO lvf_audit ( Audit_text ) VALUES ('" . s($message) . "')", $data);
			}
		}
	}
}

if ( date ("YmdHis") < config::getInstance()->lastbatch ) {
	debug::error("request.php nowdatetime = " . date ("YmdHis") . ", last batch= " . config::getInstance()->lastbatch);
	$data['success'] = false;
	$data['error'] = "LVF not available at this time, please try later";
}
debug::error("request.php nowdatetime = " . date( "Y-m-d H:i:s" ) . ", nowtime = " . date( "H:i:s" ) . ", nowdate = " . date( "Y-m-d" ) . ", prevdate = " . config::getInstance()->prevtime . ", utc offset= " . config::getInstance()->utc_offset . ", last batch= " . config::getInstance()->lastbatch);

query("INSERT INTO lvf_hits (date) VALUES ('" . date( "Y-m-d" ) . "') ON DUPLICATE KEY UPDATE lvf_hits SET requests = requests + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);

function s($v) {
	return mysql_real_escape_string($v);
}

function a($arr, $key) {
	if (isset($arr[$key])) {
		return $arr[$key];
	}
	return "";
}

function doCurl($request_str, &$data, $info_str = "") {
	$time = microtime(true);
	debug::error($info_str . $request_str);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, str_replace("%", "", $request_str) );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$response = explode("\n", curl_exec($ch));
	curl_close($ch);
	$time = microtime(true) - $time;
	$data['response']['times']['curl'] += $time;
	return $response;
}

function query($sql, &$data) {
	$time = microtime(true);
	$result = mysql_query($sql);
	$time = microtime(true) - $time;
	$data['response']['times']['query'] += $time;
	return $result;
}

function timeFunction($func, &$vidarr, &$data) {
	$time = microtime(true);
	if (empty($vidarr) && $data['success']) {
		call_user_func_array($func, array(&$vidarr, &$data));
	}
	$time = microtime(true) - $time;
	$data['response']['times'][$func] = number_format($time, 3);
}

$data = array('version' => 1, 'success' => true, 'response' => array('lines' => array(), 'extra' => array(), 'times' => array('query' => 0, 'curl' => 0)));
$vidarr = array();
$time = microtime(true);

$reg = strtoupper($_GET['reg']);
$regex = "#( (MON|TUE|WED|THU|FRI|SAT|SUN))?( ([0-9]{1,4}[\-/]{1}[0-9]{1,2}[\-/]{1}[0-9]{1,4})( [0-9]{1,4}[\-/]{1}[0-9]{1,2}[\-/]{1}[0-9]{1,4})?)$#";
$matches2 = array();
preg_match($regex, $reg, $matches2);
if (sizeOf($matches2) > 0) {
	$reg = substr($reg, 0, strpos($reg, $matches2[0]));
}

$regex = "#^((DUMPVEHICLE|HISTORY|ETA|LISTS?) )?(([A-Z]{2,3}) ([A-Z0-9*_]{2,8})(-([A-Z0-9]{1,8}))?|([A-Z0-9&/\-_,* ]+))$#";
$matches = array();
if (preg_match($regex, $reg, $matches)) {
	$data['request'] = array(
		'reg' => str_replace("*", "%", a($matches, 3)),
		'op' => a($matches, 4),
		'fnum' => str_replace("*", "%", a($matches, 5)),
		'fnum2' => str_replace("*", "%", a($matches, 7)),
		'cmd' => a($matches, 2),
		'dow' => a($matches2, 2),
		'fdate' => empty($matches2[4]) ? "" : date("Y-m-d", strtotime(a($matches2, 4))),
		'ldate' => empty($matches2[5]) ? "" : date("Y-m-d", strtotime(a($matches2, 5)))
	);

	$vidarr = array();
	
	try {
		switch ($data['request']['cmd']) {
			case 'DUMPVEHICLE':
				from_feed($data);
				break;
			case 'HISTORY':
			case 'ETA':
				from_history($data);
				break;
			case 'LIST':
			case 'LISTS':
				generate_list($vidarr, $data);
			default:
				timeFunction('searchByFnum', $vidarr, $data);
				timeFunction('searchByRegrange', $vidarr, $data);
				timeFunction('search', $vidarr, $data);
				timeFunction('searchByRoute', $vidarr, $data);
				timeFunction('searchByStop', $vidarr, $data);
				timeFunction('searchCountdown', $vidarr, $data);
				structureData($data, $vidarr);
				break;
		}
	} catch (Exception $e) {
		debug::fatal("Unhandled exception while processing request Input=" . print_r($data['request'], true) . " Error=" . $e->getMessage());
		$data['success'] = false;
		$data['error'] = "Unhandled exception while processing request";
	}
}
handleErrors($data);
$time = microtime(true) - $time;
if (true) {
	$data['response']['times']['total'] = number_format($time, 3);
	$data['response']['times']['query'] = number_format($time, 3);
	$data['response']['times']['curl'] = number_format($data['response']['times']['curl'], 3);
} else {
	unset($data['response']['times']);
}

print json_encode($data);

function from_feed(&$data) {
	query("UPDATE lvf_hits SET dumpv = dumpv + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);
	debug::error("looking for dump vehicle data in countdown data ");
	$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?RegistrationNumber=" . $data['request']['reg'] . "&ReturnList=LineName,LineId,VehicleId,directionid,destinationtext,estimatedtime,stopcode1,registrationnumber", $data, "looking for dump vehicle data in countdown data ");
	$mostRecentTime;
	foreach ($response as $row) {
		$r = json_decode($row);
		if ($r[0] == 1) {
			debug::error("found data " . implode(", ", $r));
			if ( $linecount == 0 ) {
				$data['response']['extra']['reg'] = $data['request']['reg'];
				$data['response']['extra']['id'] = $r[6];
			}
			$exp_time = ($r[8]/1000) + config::getInstance()->utc_offset ;
			$when = gmdate("Y-m-d H:i:s", $exp_time) ;
			$data['response']['lines'][] = array(
				'stop' => $r[1],
				'route' => $r[2],
				'line' => $r[3],
				'direction' => $r[4],
				'dest' => $r[5],
				'when' => $when
			);
			if (!isset($mostRecentTime) || $mostRecentTime > $r[8]) {
				$data['response']['mostRecent'] = sizeOf($data['response']['lines']) - 1;
				$mostRecentTime = $r[8];
			}
		}
	}
	if (empty($data['response']['lines'])) {
		$data['success'] = false;
		$data['error'] = "No data for vehicle " . $data['request']['reg'] . " currently available from countdown" ;
	}
}

function from_history(&$data) {
	query("UPDATE lvf_hits SET " . $data['request']['cmd'] . " = " . $data['request']['cmd'] . " + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);
	$route = $data['request']['reg'];

	$result = query("SELECT fnum, operator, uvi, vid, cdreg, note, cur_reg, orig_reg FROM lvf_vehicles WHERE cur_reg = '" . $data['request']['reg'] . "' OR cdreg = '" . $data['request']['reg'] . "'", $data);
	if ($row = mysql_fetch_assoc($result)){
		$vehicle = $row;
	} else {
		$result = query("SELECT fnum, operator, uvi, vid, note, cur_reg, orig_reg, cdreg, operator_name FROM lvf_operators, lvf_vehicles WHERE operator_code = '" . $data['request']['op'] . "' AND operator = operator_code AND fnum" . (($data['request']['op'] == 'FLN' && strlen($data['request']['fnum'] == 5)) ? " = '" : " LIKE '%") . $data['request']['fnum'] . "'", $data);
		if ($row = mysql_fetch_assoc($result)) {
			$opername = $row['operator_name'] ;
			$vehicle = $row;
		}
	}
	if ($data['request']['cmd'] == 'HISTORY') {
		if (!isset($vehicle)) {
			$data['response']['extra']['route'] = $route ;
			$result = query("SELECT lvf_route_day.lineid, lvf_route_day.route, lvf_route_day.date, lvf_route_day.last_seen, lvf_route_day.first_seen, lvf_vehicles.cur_reg, lvf_vehicles.note, lvf_vehicles.fnum, lvf_vehicles.sfnum FROM lvf_route_day, lvf_vehicles WHERE lvf_route_day.vehicle_id = lvf_vehicles.uvi AND lvf_route_day.route= '" . $route . "'" . (!empty($data['request']['fdate']) ? " AND lvf_route_day.date= '" . $data['request']['fdate'] . "'" : "") . " order by sfnum, lineid, date desc, first_seen", $data);
			while ($row = mysql_fetch_assoc($result)) {
				$rreg = $row['cur_reg'] ;
				$note = $row['note'];
				debug::error("found history record - fleetnumber= " . $data['request']['fnum'] . ", note= " . $note  . ", cur reg= " . $rreg);
				if ( $lastreg != $rreg ) {
					$timestamp = strtotime( $row['date'] ) ;
					$loadday = strtoupper ( date( "D", $timestamp )) ;
					debug::error(" Checking history day - " . $loadday . ", " . $data['request']['dow']) ;
					if (empty( $lstthr ) || ( $lstthr == $loadday )) {
						$lastreg = $rreg ;
						$data['response']['lines'][] = array(
							'first_time' => substr( $row['first_seen'], 0, 5),
							'last_time' => substr( $row['last_seen'], 0, 5),
							'line' => $row['lineid'],
							'route' => $row['route'],
							'fnum' => $row['fnum'],
							'reg' => $rreg,
							'note' => $note,
							'when' => date( "d-m-Y", $timestamp )
						);
					}
				}
			}
			if (empty($data['response']['lines'])) {
				$data['success'] = false;
				$data['error'] = "No usage data for vehicles on route " . $route . " currently available" ;
			}
			if ( sizeOf($data['response']['lines']) == 2 ) {
				$data['success'] = false;
				$data['error'] = "Vehicle/Route not recognised" ;
			}
		} else {
			$sqry = "SELECT lvf_route_day.lineid, lvf_route_day.route, lvf_route_day.date, lvf_route_day.last_seen, lvf_route_day.first_seen, lvf_vehicles.cur_reg, lvf_vehicles.note, lvf_vehicles.fnum, lvf_vehicles.operator FROM lvf_route_day, lvf_vehicles WHERE lvf_route_day.vehicle_id = lvf_vehicles.uvi AND lvf_route_day.vehicle_id = '" . $vehicle['uvi'] . "'" . (!empty($data['request']['fdate']) ? " AND lvf_route_day.date >= '" . $data['request']['fdate'] . "'" : "") . (!empty($data['request']['ldate']) ? " AND lvf_route_day.date <= '" . $data['request']['ldate'] . "'" : "") . " order by date desc, first_seen desc" ;
			$result = query( $sqry ) ;
			if (mysql_num_rows($result) > 0) {
				$data['response']['extra']['op'] = $row['operator'];
				$data['response']['extra']['fleetNumber'] = $row['fnum'];
				$data['response']['extra']['reg'] = $row['cur_reg'];
				$data['response']['extra']['note'] = $row['note'];
			}
			while ($row = mysql_fetch_assoc($result)){
				$timestamp = strtotime( $row['date'] ) ;
				$loadday = strtoupper ( date( "D", $timestamp )) ;
				debug::error(" Checking history day - " . $loadday . ", " . $data['request']['dow']);
				if (( $lstthr == '' ) || ($lstthr == $loadday )) {
					$data['response']['lines'][] = array(
						'first_time' => substr( $row['first_seen'], 0, 5),
						'last_time' => substr( $row['last_seen'], 0, 5),
						'line' => $row['lineid'],
						'route' => $row['route'],
						'fnum' => $row['fnum'],
						'note' => empty($row['note']) ? $row['cur_reg'] : $row['note'],
						'when' => date( "d-m-Y", $timestamp )
					);
				}
			}
			if (empty($data['response']['lines'])) {
				$data['success'] = false;
				$data['error'] = "No usage data for vehicle currently available" ;
			}
			if ( sizeOf($data['response']['lines']) == 2 ) {
				$data['success'] = false;
				$data['error'] = "No record of vehicle used in service" ;
			}
		}
	} else {
		debug::error("eta command , Countdown reg = " . $vehicle['cdreg'] . ", Vehicle id = " . $vehicle['vid']);
		$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?RegistrationNumber=" . $vehicle['cdreg'] . "&ReturnList=LineName,LineId,VehicleId,directionid,destinationtext,estimatedtime,stopcode1,registrationnumber", $data, "looking for eta vehicle data in countdown data ");
		$busArr = array();
		foreach ($response as $row) {
			debug::error("returned countdown eta data " . $row);
			$r = json_decode($row);
			if ($r[0] == 1) {
				$busArr[$r[8]] = $r;
			}
		}
		ksort($busArr);
		foreach ($busArr as $r) {
			debug::error("found eta data " . implode(", ", $r));
			if ($r[0] == 1) {
				if (empty($data['response']['lines'])) {
					$data['response']['extra']['operator'] = $vehicle['operator'];
					$data['response']['extra']['fleetNumber'] = $vehicle['fnum'];
					$data['response']['extra']['reg'] = $vehicle['cur_reg'];
					$data['response']['extra']['note'] = $vehicle['note'];
					$data['response']['extra']['route'] = $r[2];
				}
				$exp_time = ($r[8]/1000) + config::getInstance()->utc_offset ;
				$when = gmdate("H:i", $exp_time) ;
				$a = get_stop_info ( $r[1], $r[2] ) ;
				$rec = array($when, $r[1], $a, $r[5]);
				$data['response']['lines'][] = array(
					'when' => $rec[0],
					'stop' => $rec[1],
					'stopName' => $rec[2],
					'dest' => $rec[3]
				);
			}
		}
		if (empty($data['response']['lines'])) {
			$data['success'] = false;
			$data['error'] = "No data for vehicle currently available from countdown" ;
		}
	}
}
function generate_list(&$vidarr, &$data) {
	query("UPDATE lvf_hits SET list_req = list_req + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);
	$a = explode(" ", $data['request']['reg']);
	$listname = $a[0] ;
	debug::error("found vehicle list data, list " . $listname . " - a[0]= " . $data['request']['cmd'] . (isset($a[1]) ? " - a[1]= " . $a[1] : ""));
	if ( $listname == 'UNUSED' ) {
		if ( isset( $a[1] ) && $row = mysql_fetch_assoc(query("SELECT operator_name FROM lvf_operators WHERE operator_code = '" . $a[1] . "'"))) {
			$result = query("SELECT lvf_where_seen.vid FROM lvf_where_seen, lvf_vehicles WHERE lvf_where_seen.vid = lvf_vehicles.vid AND lvf_vehicles.operator = '" . $a[1] . "' AND lvf_where_seen.last_seen " . (!empty($data['request']['fdate']) ?  " < '" . $data['request']['fdate'] : " = '") . "' order by lvf_where_seen.last_seen desc, lvf_vehicles.operator, lvf_vehicles.sfnum") ;
		} else {
			$result = query("SELECT lvf_where_seen.vid FROM lvf_where_seen, lvf_vehicles WHERE lvf_where_seen.vid = lvf_vehicles.vid AND lvf_where_seen.last_seen " . (!empty($data['request']['fdate']) ?  " < '" . $data['request']['fdate'] : " = '") . "' order by lvf_where_seen.last_seen desc, lvf_vehicles.operator, lvf_vehicles.sfnum") ;
		}
		while (($row = mysql_fetch_assoc($result)) && (sizeOf($vidarr) < 140 )) {
			$vidarr[] = $row['vid'] ;
			debug::error("found vehicle list data, list " . $listname . " - vehicle id= " . end($vidarr));
		}
	} else {
		debug::error("found list data, list " . $listname . print_r($a, true));
		$result = query("SELECT lvf_lists.vid, lvf_vehicles.sfnum, lvf_vehicles.operator, lvf_vehicles.note  FROM lvf_lists, lvf_vehicles WHERE list_name = '" . $listname . "' AND lvf_vehicles.vid = lvf_lists.vid order by lvf_vehicles.operator, lvf_vehicles.sfnum", $data);
		if ( isset( $a[1])) { $a[1] = strtoupper( $a[1]) ; }
		while (($row = mysql_fetch_assoc($result)) && ( sizeOf($vidarr) < 140 )) {
			$note = strtoupper($row['note']) ;
			if (( !isset( $a[1])) || $a[1] == substr( $note, 0 , strlen($a[1]))) {
				$vidarr[] = $row['vid'] ;
				debug::error("found vehicle list data, list " . $listname . " - vehicle id= " . end($vidarr));
			}
		}
	}
	debug::error("found vehicle list data, list " . $listname . " - num entries= " . sizeOf($vidarr));
	if (empty($vidarr)) {
		$data['success'] = false;
		$data['error'] = "Current lists are ads, ooffs and unused" ;
	} else {
		$data['request']['cmd'] = "LIST";
	}
}
function search(&$vidarr, &$data) {
	$result = query("SELECT vid, sfnum FROM lvf_vehicles WHERE cur_reg LIKE '" . str_replace(" ", "", $data['request']['reg']) . "' order by operator, sfnum", $data);
	if (!mysql_num_rows($result)) {
		$result = query("SELECT vid, sfnum FROM lvf_vehicles WHERE orig_reg LIKE '" . str_replace(" ", "", $data['request']['reg']) . "' order by operator, sfnum", $data);
	}
	addToVidarr($result, $vidarr);
	if (!empty($vidarr)) {
		$data['request']['cmd'] = "LIST";
	}
}
function searchByFnum(&$vidarr, &$data) {
	// check first for fleetnumber - look for operator code
	$opername = '' ;
	$result = query("SELECT operator_name FROM lvf_operators WHERE operator_code = '" . $data['request']['op'] . "'", $data);
	if ($row = mysql_fetch_assoc($result)) {
		$opername = $row['operator_name'] ;
		
		$regex = "#([A-Z]{1,3})?([0-9]{1,5})#";
		$matches = array();
		preg_match($regex, $data['request']['fnum'], $matches);
		if (!empty($data['request']['fnum2'])) {
			$lhs = $matches[2];
			$rhs = $data['request']['fnum2'];
			if (strlen($rhs) < strlen($lhs)) {
				$rhs = substr($lhs, 0, strlen($lhs) - strlen($rhs)) . $rhs;
			}
			if ($rhs < $lhs) {
				list($rhs, $lhs) = array($lhs, $rhs);
			}
			$lhs = $matches[1] . $lhs;
			$rhs = $matches[1] . $rhs;
			
			$result = query("SELECT rnfnum FROM lvf_vehicles WHERE operator = '" . $data['request']['op'] . "' AND fnum = '" . $lhs . "'", $data);
			if ($row = mysql_fetch_assoc($result)) {
				$lhs = $row['rnfnum'] ;
			}
			$result = query("SELECT rnfnum FROM lvf_vehicles WHERE operator = '" . $data['request']['op'] . "' AND fnum = '" . $rhs . "'", $data);
			if ($row = mysql_fetch_assoc($result)) {
				$rhs = $row['rnfnum'] ;
			}
			debug::error("looking up operator fleetnumber range info - Oper = " . $data['request']['op'] . " - first fleetnumber = " . $lhs . ", last fleetnumber = " . $rhs);
			addToVidarr(query("SELECT vid FROM lvf_vehicles WHERE operator = '" . $data['request']['op'] . "' AND rnfnum >= '" . $lhs . "' AND rnfnum <= '" . $rhs . "' order by sfnum"), $vidarr, $data);
		} else {
			debug::error("looking up operator fleetnumber info - Oper = " . $opername . " - fleetnumber = " . $data['request']['fnum']);
			addToVidarr(query("SELECT vid FROM lvf_vehicles WHERE operator = '" . $data['request']['op'] . "' AND fnum LIKE '" . $data['request']['fnum'] . "' order by sfnum"), $vidarr, $data);
		}
		
		if (empty($vidarr)) {
			if ($data['request']['op'] == "FLN" && empty($matches[1])) {
				addToVidarr(query("SELECT vid FROM lvf_vehicles WHERE operator = '" . $data['request']['op'] . "' AND fnum LIKE '%" . $data['request']['fnum'] . "' order by sfnum"), $vidarr, $data);
			}
		}
		if (empty($vidarr)) {
			$data['success'] = false;
			$data['error'] = $data['request']['op'] . " appears to start with a valid operator code, check that the fleetnumber is valid";
		} else {
			$data['request']['cmd'] = "LIST";
		}
	}
}
function searchByRegrange(&$vidarr, &$data) {
	// check for registration range
	$ranreq = explode("-", $data['request']['reg'] );
	if (sizeOf($ranreq) == 2) {
		$ranreq[1] = substr($ranreq[0], 0, strlen($ranreq[0]) - strlen($ranreq[1])) . $ranreq[1];
		sort($ranreq);
		
		debug::error("registration range request, start range = " . $ranreq[0] . ", end range = " . $ranreq[1]);
		addToVidarr(query("SELECT vid, sfnum FROM lvf_vehicles WHERE cur_reg >= '" . $ranreq[0] . "' AND cur_reg <= '" . $ranreq[1] . "' order by sfnum"), $vidarr, $data);
		
		if (!empty($vidarr)) {
			$data['request']['cmd'] = "LIST";
		}
	}
}
function searchCountdown(&$vidarr, $data) {
	query("UPDATE lvf_hits SET veh_req = veh_req + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);
	$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?RegistrationNumber=" . $data['request']['reg'] . "&ReturnList=VehicleId", $data);
	foreach ($response as $row) {
		$r = json_decode($row);
		if ($r[0] == 1) {
			$vidarr[] = s($r[1]);
			break;
		}
	}
	if (!empty($vidarr)) {
		$data['request']['cmd'] = "LIST";
	}
}
function searchByRoute(&$vidarr, &$data) {
	if ( strlen($data['request']['reg']) < 5 ) {
		query("UPDATE lvf_hits SET route_req = route_req+1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);

		$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=" . $data['request']['reg'] . "&ReturnList=LineName,LineId,VehicleId,directionid,destinationtext,estimatedtime,stopcode1,registrationnumber", $data);
		$mostRecentTime;
		$busArr = array();
		foreach ($response as $row) {
			$r = json_decode($row);
			if ($r[0] == 1) {
				debug::error("found data " . implode(", ", $r));
				if (!isset($busArr[$r[6]]) || $busArr[$r[6]][8] > $r[8]) {
					if ( $r[1] == 'NONE' ) {
						debug::error("Stopid NONE seen in data (line ignored) = " . implode(" ", $r));
					} else {
						$busArr[$r[6]] = $r;
					}
				}
			}
		}
		foreach ($busArr as $r) {
			$stopid = s($r[1]) ;
			$lineid = s($r[2]) ;
			$route = s($r[3]) ;
			$dirn = s($r[4]) ;
			$dest = s($r[5]) ;
			$vid = s($r[6]) ;
			$creg = s($r[7]) ;
			$exp_time = (s($r[8])/1000) + config::getInstance()->utc_offset ;
			$lastseen = gmdate("Y-m-d H:i:s", $exp_time) ;
			debug::error("found vehicle on route in countdown data - " . implode(" ", $r));

			if ( $vid == 0 ) {
				debug::error("Was about to update where_seen and route day data from route request with null vid - " . implode(", ", $r));
				foreach ($response as $row) {
					debug::error("Read row - " . $row);
				}
			} else {
				check_veh_update ( $vid, $creg ) ;
				$result = query("SELECT uvi, orig_reg FROM lvf_vehicles WHERE vid = '" . $vid . "'", $data);
				if ($row = mysql_fetch_assoc($result)) {
					$uvi = $row['uvi'] ;
					$origreg = $row['orig_reg'] ;
					query("UPDATE lvf_where_seen SET route= '" . $route . "', line_id = '" . $lineid . "', last_seen = '" . $lastseen . "', nearest_stop = '" . $stopid . "', dirid = '" . $dirn . "', destination = '" . $dest . "' WHERE vid = '" . $vid . "'") ;
					query("UPDATE lvf_route_day SET last_seen = '" . date( "H:i:s" ) . "' WHERE vehicle_id = '" . $uvi . "' AND lineid = '" . $lineid . "' AND date = '" . date( "Y-m-d" ) . "' ") ;
					if ( mysql_affected_rows() == 0 ) {
						query("INSERT INTO lvf_route_day (route, lineid, last_seen, first_seen, vehicle_id, date, registration) VALUES ('" . $route . "', '" . $lineid . "', '" . date( "H:i:s" ) . "', '" . date( "H:i:s" ) . "' , '" . $uvi . "', '" . date( "Y-m-d" ) . "', '" . $origreg .  "')", $data);
						debug::error("new entry in route day data from route request " . $route . ", " .  $lineid . ", " .  date( "H:i:s" ) . ", " . $vid . ", " . date( "Y-m-d" ) . ", " . $creg);
					}
					$vidarr[] = $vid ;
				}
			}
		}
		$result = query("SELECT lvf_vehicles.vid FROM lvf_route_day, lvf_vehicles WHERE lvf_route_day.vehicle_id = lvf_vehicles.uvi AND lvf_route_day.route = '" . $data['request']['reg'] . "' AND lvf_route_day.date = '" . date( "Y-m-d" ) . "'", $data);
		$dbarr = array();
		while ($row = mysql_fetch_assoc($result)) {
			$dbarr[] = $row['vid'];
		}
		$vidarr = array_unique(array_merge($vidarr, $dbarr));
		
		if (empty($vidarr)) {
			$row = mysql_fetch_assoc(query("SELECT COUNT(route) as c FROM lvf_destinations WHERE route = '" . $data['request']['reg'] . "'"), $data);
			if ($row['c'] > 0) {
				$data['success'] = false;
				$data['error'] = "Route " . $data['request']['reg'] . " has not been active today" ;
			}
			return;
		}
		
		$multi_vids = implode(",", $vidarr);
		update_vlocation ( $multi_vids, $data ) ;
		debug::error(sizeOf($vidarr) . " items found for route " . $data['request']['reg'] . " Vehicle Ids = " . $multi_vids);
		$vidarr = array();
		
		if ( config::getInstance()->prevtime > date( "H:i:s" ) ) {
			config::getInstance()->prevtime = "00:00:00" ;
		}
		$querytext = "SELECT lvf_vehicles.sfnum, lvf_where_seen.vid, lvf_where_seen.destination, lvf_where_seen.dirid, lvf_route_day.lineid FROM" .
			" lvf_route_day, lvf_where_seen, lvf_vehicles WHERE lvf_route_day.route= " .
			"'" . $data['request']['reg'] . "'" .
			" AND lvf_route_day.date = " .
			"'" . date( "Y-m-d" ) . "'" .
			" AND lvf_route_day.last_seen >  " .
			"'" . config::getInstance()->prevtime . "'" .
			" AND lvf_vehicles.uvi = lvf_route_day.vehicle_id AND lvf_vehicles.vid = lvf_where_seen.vid AND lvf_where_seen.route = lvf_route_day.route ORDER BY lvf_where_seen.dirid, lvf_where_seen.line_id, lvf_vehicles.sfnum";
		debug::error($querytext);
		$result = query( $querytext) ;
		
		$dest = array("1" => array(), "2" => array()) ;
		$dests = array() ;
		$dcount = array();
		$lines = array();
		$did = array();
		while ($row = mysql_fetch_assoc($result)) {
			$vidarr[] = $row['vid'] ;
			$destn = $row['destination'] ;
			if ( $destn != '' ) {
				debug::error("processing dest " . $destn . ", " . $row['dirid'] . ", " . $lineid . ", " . sizeOf($dest['1']) . ", " . sizeOf($dest['2']));
				
				$unique = array($destn, $row['dirid']);
				if (!in_array($unique, $dests)) {
					$dests[] = $unique ;
					$dcount[] = 1;
				} else {
					$dcount[array_search($destn, $dests)] += 1;
				}
				if (!in_array($row['lineid'], $lines)) {
					$lines[] = $row['lineid'] ;
				}
				
				$newdata = array($destn, $lineid);
				if (!in_array($newdata, $dest[$row['dirid']])) {
					$dest[$row['dirid']][] = $newdata;
				}
			}
		}
		debug::error("found dests" . print_r($dest, true));
		$direction = "" ;
		$alt_dest = 0 ;
		$destcnt = 0 ;
		
		$result = query("SELECT direction, day, destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators FROM lvf_destinations WHERE route = '" . $data['request']['reg'] . "' order by day DESC, direction", $data);
		while ($row = mysql_fetch_assoc($result)) {
			$tdest = $row['destination'] ;
			$tddirn = $row['direction'] ;
			$lineid = empty($row['day']) ? $data['request']['reg'] : $row['day'];
			$usedest = 0 ;
			debug::error("read dest data " . $tdest . ", " . $tddirn . ", " . $row['day']);
			
			$unique = array($tdest, $tddirn);
			if (in_array($lineid, $lines)) {
				$a = get_vinfo (end($vidarr));
				if (!isset($data['response']['extra'][$lineid]['operators'])) {
					$data['response']['extra'][$lineid]['operators'] = empty($row['operators']) ? $a['opName'] : $row['operators'];
				}
				$data['response']['extra'][$lineid][($tddirn != 1) ? 'destination' : 'origin'] = $row['destination'];
			} else if (!empty($row['day']) && array_search($unique, $dests) !== false && $lines[array_search($unique, $dests)] == $data['request']['reg'] && !in_array($lineid, array_diff(array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"), array(gmdate("D")))) && (in_array($row['day'], array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun")) || !isset($data['response']['extra']['extra'][md5($row['direction'].$row['destination'])]))) {
				$data['response']['extra']['extra'][$row['direction'].$row['destination']] = array(
					'direction' => $row['direction'],
					'type' => $row['day'],
					'destination' => $row['destination']
				);
			} else {
				continue;
			}
			
			if (in_array($unique, $dests)) {
				query("UPDATE lvf_destinations SET dest_cnt = dest_cnt + '" . $dcount[array_search($unique, $dests)] . "' WHERE route = '" . $data['request']['reg'] . "' AND direction = '" . $tddirn . "' AND day = '" . $row['day'] . "'", $data);
				$dcount[array_search($unique, $dests)] = 0;
			}
		}
		
		foreach ($dcount as $key => $count) {
			if ($count > 0) {
				query("UPDATE lvf_destinations SET sd_cnt = sd_cnt + '" . $count . "' WHERE route = '" . $data['request']['reg'] . "' AND short_dest = '" . $dests[$key][0] . "'", $data);
				if (mysql_affected_rows() == 0) {
					query("UPDATE lvf_destinations SET sd_cnt = '" . $count . "', short_dest = '" . $dests[$key][0] . "' WHERE route = '" . $data['request']['reg'] . "' AND direction ='" . $dests[$key][1] . "' AND day = '' AND short_dest = ''", $data);
					if (mysql_affected_rows() == 0) {
						query("UPDATE lvf_destinations SET other_sd_cnt = other_sd_count + '" . $count . "' WHERE route = '" . $data['request']['reg'] . "' AND direction ='" . $dests[$key][1] . "' AND day = ''", $data);
					}
				}
				$result = query("SELECT route, direction, short_dest, sd_cnt FROM lvf_destinations WHERE dest_cnt * 2 < sd_cnt", $data);
				while ($row = mysql_fetch_assoc($result)) {
					$result2 = query("SELECT day FROM lvf_destinations WHERE route = '" . $row['route'] . "' AND direction = '" . $row['direction'] . "' AND day LIKE 'SD%'", $data);
					$sd = array();
					while ($row2 = mysql_fetch_assoc($result2)) {
						$sd[] = $row2['day'];
					}
					$i = 0;
					while (in_array("SD".++$i, $sd));
					query("INSERT INTO lvf_destinations (route, direction, day, destination, dest_cnt) VALUES ('" . $row['route'] . "', '" . $row['direction'] . "', 'SD" . $i . "', '" . $row['short_dest'] . "', '" . $row['sd_cnt'] . "')", $data);
					debug::info("Added new destination record for route='" . $row['route'] . "', direction='" . $row['direction'] . "', day='SD" . $i . "', destination='" . $row['short_dest'] . "', count='" . $row['sd_cnt'] . "'");
					if (mysql_affected_rows() > 0) {
						query("UPDATE lvf_destinations SET short_dest = '', sd_cnt = 0 WHERE route = '" . $row['route'] . "' AND direction ='" . $row['direction'] . "' AND day = ''", $data);
					}
					if ($row['route'] == $data['request']['reg']) {
						$data['response']['extra']['extra'][$row['direction'].$row['short_dest']] = array(
							'direction' => $row['direction'],
							'type' => 'SD' . $i,
							'destination' => $row['short_dest']
						);
					}
				}
			}
		}
		
		// add historic entries - if no current entries get everything in route day table
		if (empty($vidarr)) {
			config::getInstance()->prevtime = date( "H:i:s" ) ;
		}
		$querytext = "SELECT lvf_route_day.last_seen, lvf_vehicles.fnum, lvf_route_day.first_seen, lvf_vehicles.note, lvf_vehicles.cur_reg, lvf_route_day.route" .
			", lvf_vehicles.operator, lvf_route_day.lineid FROM lvf_route_day, lvf_vehicles WHERE lvf_route_day.route= " .
			"'" . $data['request']['reg'] . "'" .
			" AND lvf_route_day.date = " .
			"'" . date( "Y-m-d" ) . "'" .
			" AND lvf_vehicles.uvi = lvf_route_day.vehicle_id ORDER BY lvf_vehicles.sfnum" ;
		$result = query($querytext) ;
		while ($row = mysql_fetch_assoc($result)) {
			if (( $row['last_seen'] < config::getInstance()->prevtime ) || ( $row['route'] != $data['request']['reg'] )) {
				debug::error("found historic route info, route " . $data['request']['reg'] . " fleetnumber = " . $row['fnum'] . " last seen " . $row['last_seen'] . " now route = " . $row['route']);
				if (empty($row['note'])) {
					$row['note'] = $row['cur_reg'];
				}
				$row['direction'] = "3";
				$data['response']['lines'][] = array(
					'route' => $row['route'],
					'first_time' => substr($row['first_seen'], 0, 5),
					'last_time' => substr($row['last_seen'], 0, 5),
					'note' => $row['note'],
					'fnum' => $row['fnum'],
					'op' => $row['operator'],
					'line' => $row['lineid'],
					'direction' => 3
				);
			}
		}
		
		if (empty($vidarr)) {
			$data['success'] = false;
			$data['error'] = $data['request']['reg'] . " is not a valid route, for a vehicle add the operator code for a stop enter more than 5 characters" ;
			query("UPDATE lvf_hits SET error_req = error_req + 1 WHERE date = '" . date( "Y-m-d" ) . "'", $data);
		} else {
			$data['request']['cmd'] = "ROUTE";
		}
	}
}
function searchByStop(&$vidarr, &$data) {
	$result = query("SELECT StopId, StopName FROM lvf_stops WHERE StopName LIKE '" . $data['request']['reg'] . "' OR StopId IN (" . $data['request']['reg'] . ") order by StopId", $data);
	$stops = array();
	while ($row = mysql_fetch_assoc($result)) {
		$stops[] = $row['StopId'] ;
	}
	if (empty($stops)) {
		$result = query("SELECT StopId, StopName FROM lvf_stops WHERE StopName LIKE '%" . $data['request']['reg'] . "%' order by StopId", $data);
		while ($row = mysql_fetch_assoc($result)) {
			$stops[] = $row['StopId'] ;
		}
	}
	
	$usr = explode(",", $data['request']['reg']);
	foreach ($usr as $k => $v) {;
		if (!is_numeric($v)) {
			unset($usr[$k]);
		}
	}
	$stopLookup = array_unique(array_merge($stops, $usr));
	$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?StopCode1=" . implode(",", $stopLookup) . "&ReturnList=LineId,StopCode1,VehicleId,linename,directionid,destinationtext,estimatedtime,StopPointName", $data);
	$busArr = array();
	$names = array();
	foreach ($response as $row) {
		debug::error("returned countdown stop data " . $row);
		$r = json_decode($row);
		if ($r[0] == 1) {
			$busArr[$r[8]] = $r;
			$names[] = $r[1];
			
			if (!in_array($r[2], $stops)) {
				query("INSERT INTO lvf_stops ( StopId, StopName ) VALUES ('" . s($r[2]) . "', '" . s($r[1]) . "')", $data);
			}
		}
	}
	ksort($busArr);
	
	$names = array_values(array_unique($names));
	if (sizeOf($names) > 1) {
		$data['success'] = false;
		$data['error'] = "'" . $data['request']['reg'] . "' has matched multiple stops names eg '" . $names[0] . "' and '" . $names[1] . "'" ;
		return;
	} else if (!empty($names)) {
		$stopname = $names[0];
		$data['response']['extra']['name'] = $names[0];
		$data['response']['extra']['ids'] = implode(",", $stopLookup);
		
		$lastroute = '' ;
		$lastvid = '' ;
		$lastdest = '' ;
		$lastwhentime = '';
		foreach ($busArr as $r) {
			$vehicleid = s($r[7]) ;
			$route = s($r[4]) ;
			$line = array('dest' => $r[6], 'route' => $route, 'line' => s($r[3]));
			
			$result = query("SELECT cur_reg, operator, fnum, note FROM lvf_vehicles WHERE vid = '" . $vehicleid . "'", $data);
			if ($row = mysql_fetch_assoc($result)) {
				$oper = $row['operator'] ;
				$line['note'] = $row['note'] ;
				$line['reg'] = $row['cur_reg'];
				$line['fnum'] = $row['fnum'] ;
				debug::error("found reg and oper code = " . $row['cur_reg'] . "  " . $oper);
				$line['op'] = $oper;
				$result = query("SELECT operator_name FROM lvf_operators WHERE operator_code = '" . $oper . "'", $data);
				if ($row = mysql_fetch_assoc($result)) {
					$line['opName'] = $row['operator_name'] ;
				}
			}
			$expect_time = (s($r[8])/1000) + config::getInstance()->utc_offset ;
			$when = gmdate("H:i Y-m-d", $expect_time) ;
			$line['when'] = substr( $when, 0, 5) ;
			if (( $lastroute != $route ) || ($lastvid != $vehicleid ) || ( $lastdest != $line['dest'] ) || ( $lastwhentime != $line['whentime'] )) {
				$data['response']['lines'][] = $line;
			}
			$lastroute = $route ;
			$lastvid = $vehicleid ;
			$lastdest = $line['dest'] ;
			$lastwhentime = $line['when'] ;

			debug::error("returned countdown stop data " . $route . " "  . s($r[2]) . " "  . s($r[3]) . " "  . $vehicleid . " "  . s($r[5]));
		}
	}
	if (!empty($data['response']['lines'])) {
		$data['request']['cmd'] = "STOP";
	}
}

function handleErrors(&$data) {
	if (empty($data['response']['lines']) && $data['success']) {
		$data['success'] = false;
		$a = explode(" ", $data['request']['reg']);
		if (substr_count($data['request']['reg'], "_") > 1) {
			$data['error'] = $data['request']['reg'] . "appears to contain multiple single character wildcards '_'. It is better to use the * char which wildcards multiple characters.";
		} else if (substr_count($data['request']['reg'], "-") > 1) {
			$data['error'] = $data['request']['reg'] . "appears to contain multiple '-' characters. This character is used to specify ranges of which only one can be specified at a time.";
		} else if (strpos($data['request']['reg'], "/") !== false) {
			$data['error'] = $data['request']['reg'] . "appears to contain a '/' character. Cannot be used except as part of a stop name.";
		} else if (strpos($data['request']['reg'], "IPOD") !== false || strpos($data['request']['reg'], "VOD") !== false) {
			$data['error'] = "If you were trying to get a list of iPod or Vodafone advert buses you need to put the command 'lists ads' before the type of advert you want";
		} else if (substr($data['request']['reg'], 0, 3) == "SMK") {
			$data['error'] = "No routemasters with SMK registrations are entered into the database";
		} else if ((substr($data['request']['reg'], 0, 7) == "HISTORY" || substr($data['request']['reg'], 0, 7) == "ETA") && empty($data['request']['cmd'])) {
			$data['error'] = "Due to new features being introduced, the history and eta commands need to be followed by a space before the route number or vehicle identifier";
		} else if (strlen($data['request']['reg']) > 10) {
			$data['error'] = "You appear to have entered a stop name. Since stop names have to exactly match the TFL stop name use wildcards to increase your chance of getting the name right";
		} else if ($row = mysql_fetch_assoc(query("SELECT real_code, operator_name FROM lvf_op_err, lvf_operators WHERE lvf_op_err.real_code = lvf_operators.operator_code AND err_code = '" . $data['request']['op'] . "'"))) {
			$data['error'] = $data['request']['op'] . " is not the correct operator code for " .  $row['operator_name'] . ". Please use the operator code " . $row['real_code'];
		} else if (!empty($data['request']['fnum']) && $row = mysql_fetch_assoc(query("SELECT operator FROM lvf_vehicles WHERE fnum LIKE '%" . $data['request']['fnum'] . "%'"))) {
			$data['error'] = "You appear to have entered a valid fleetnumber for operator " . $row['operator'] . ". To look up vehicles by fleetnumber, an operator code must be entered before the fleetnumber";
		} else if (!empty($data['request']['fnum']) && $row = mysql_fetch_assoc(query("SELECT vid FROM lvf_vehicles WHERE cur_reg LIKE '%" . $data['request']['reg'] . "%'"))) {
			$data['error'] = "Your input appears to be part of a registration. To enter only part of a registration you must use a wildcard character, eg " . $data['request']['reg'] . "* to tell the program this is what you want" ;
		} else if (preg_match("#^([A-Z]{1}|[A-Z]{3})([0-9]+)(.+)$#", $data['request']['reg'])) {
			$data['error'] = "Your input appears to be part of a pre 2001 registration. Are you sure this is an active vehicle as the LVF database does not hold data on withdrawn vehicles";
		} else {
			$data['error'] = "Your input does not match any vehicle or command in our database";
			debug::fatal("Unhandled error, Input=" . print_r($data['request'], true));
		}
		query("UPDATE lvf_hits SET error_req = error_req + 1 WHERE date = CURDATE()", $data);
	}
}

/*
 * Vid Funtions
 */

function addToVidarr($result, &$vidarr, $max = 120) {
	while (($row = mysql_fetch_assoc($result)) && ( sizeOf($vidarr) < $max )) {
		$vidarr[] = $row['vid'] ;
	}
}
function structureData(&$data, $vidarr) {
	$multi_vids = implode(",", $vidarr);
	debug::error(sizeOf($vidarr) . " items found for route " . $data['request']['reg'] . " Vehicle Ids = " . $multi_vids);
	update_vlocation ( $multi_vids, $data ) ;
	$structure = array();
	$linecount = 0 ;
	foreach ($vidarr as $vid) {
		debug::error("get vehicle data " . $vid);
		if ($a = get_vinfo ($vid)) {
			$structure[] = $a;
		}
	}
	$data['response']['lines'] = array_merge($structure, $data['response']['lines']);
}
?>
