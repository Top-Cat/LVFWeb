<?php

include "includes/connect.php";
include "getvehicleinfo.php";
include "check_vehicle.php";

if (isset($argv)) {
	$_GET['reg'] = $argv[1];
} else if (!isset($_GET['reg'])) {
	die();
}

debug::error("request.php nowdatetime = " . date( "Y-m-d H:i:s" ) . ", nowtime = " . date( "H:i:s" ) . ", nowdate = " . date( "Y-m-d" ) . ", prevdate = " . config::getInstance()->prevtime . ", utc offset= " . config::getInstance()->utc_offset . ", last batch= " . config::getInstance()->lastbatch);
event('request');

$vidarr = array();
$time = microtime(true);

$reg = strtoupper($_GET['reg']);
$row = query('lvf_scuts', 'findOne', array(array('key' => $reg)));
if (!is_null($row)){
	$reg =  strtoupper($row['text']);
}
		
$regex = "#( (MON|TUE|WED|THU|FRI|SAT|SUN))?( ([0-9]{1,4}[\-/]{1}[0-9]{1,2}[\-/]{1}[0-9]{1,4})( [0-9]{1,4}[\-/]{1}[0-9]{1,2}[\-/]{1}[0-9]{1,4})?)?$#";
$matches2 = array();
preg_match($regex, $reg, $matches2);
if (!empty($matches2[0])) {
	$reg = substr($reg, 0, strpos($reg, $matches2[0]));
}

$regex = "#^((DUMPVEHICLE|HISTORY|ETA|LISTS?) )?(([A-Z]{2,3}) ([A-Z0-9*_]{2,8})(-([A-Z0-9]{1,8}))?|([A-Z0-9&/\-_,* ]+))$#";
$matches = array();
if (preg_match($regex, $reg, $matches)) {
	output::setRequest(array(
		'reg' => transformWildcards(a($matches, 3)),
		'op' => a($matches, 4),
		'fnum' => transformWildcards(a($matches, 5)),
		'fnum2' => transformWildcards(a($matches, 7)),
		'cmd' => a($matches, 2),
		'dow' => a($matches2, 2),
		'fdate' => empty($matches2[4]) ? "" : date("Y-m-d", strtotime(a($matches2, 4))),
		'ldate' => empty($matches2[5]) ? "" : date("Y-m-d", strtotime(a($matches2, 5)))
	));

	$vidarr = array();
	
	try {
		switch (output::getRequest()['cmd']) {
			case 'DUMPVEHICLE':
				from_feed();
				break;
			case 'HISTORY':
			case 'ETA':
				from_history();
				break;
			case 'LIST':
			case 'LISTS':
				generate_list($vidarr);
			default:
				timeFunction('searchByFnum', $vidarr);
				timeFunction('searchByRegrange', $vidarr);
				timeFunction('search', $vidarr);
				timeFunction('searchByRoute', $vidarr);
				timeFunction('searchByStop', $vidarr);
				timeFunction('searchCountdown', $vidarr);
				structureData($vidarr);
				break;
		}
	} catch (MongoCursorTimeoutException $e) {
		error_log($e->getMessage());
		output::setError("Database didn't respond in a timely maner, please try again later");
	} catch (Exception $e) {
		error_log($e->getMessage());
		debug::fatal("Unhandled exception while processing request Input=" . print_r(output::getRequest(), true) . " Error=" . $e->getMessage());
		output::setError("Unhandled exception while processing request");
	}
} else {
	output::setRequest(array('reg' => $reg, 'op' => ''));
}
handleErrors();

output::addTime('total', microtime(true) - $time);
output::printOutput();
stats::finalise();

function from_feed() {
	event('dumpv');
	debug::error("looking for dump vehicle data in countdown data ");

	$row = query('lvf_vehicles', 'findOne', array(array('cdreg' => output::getRequest()['reg']), array('vid' => 1)));
	$c = query('lvf_predictions', 'find', array(array('vid' => $row['vid'])))->sort(array('prediction' => 1));

	while ($c->hasNext()) {
		$r = $c->getNext();
		if (empty(output::getResponse()['lines'])) {
			output::getResponse()['extra']['reg'] = output::getRequest()['reg'];
			output::getResponse()['extra']['id'] = $r['vid'];
		}
		$when = date("Y-m-d H:i:s", $r['prediction']->sec) ;
		output::getResponse()['lines'][] = array(
			'stop' => $r['stopid'],
			'route' => $r['route'],
			'line' => $r['line_id'],
			'direction' => $r['dirid'],
			'dest' => $r['destination'],
			'when' => $when
		);
	}
	if (empty(output::getResponse()['lines'])) {
		output::setError("No data for vehicle " . output::getRequest()['reg'] . " currently available from countdown" );
	}
}

function from_history() {
	event(output::getRequest()['cmd']);

	$vehicle = array() ;
	$cdreg = '' ;
	$route = output::getRequest()['reg'];
	$regs = output::getRequest()['reg'] ;

	$fnum = output::getRequest()['fnum'];
	if ((output::getRequest()['op'] == 'FLN' || output::getRequest()['op'] == 'TT') && strlen(output::getRequest()['fnum']) == 5) {
		$fnum = array('$regex' => $fnum . '$');
	}
	$row = query('lvf_vehicles', 'findOne', array(array('operator' => output::getRequest()['op'], 'fnum' => $fnum), array('cur_reg' => 1, 'vid' => 1, 'fnum' => 1, '_id' => 0)));
	if (!is_null($row)) {
		$regs = $row['cur_reg'] ;
	}
	$c = query('lvf_vehicles', 'find', array(array('cur_reg' => $regs), array('uvi' => 1, 'note' => 1, 'operator' => 1, 'fnum' => 1, 'cdreg' => 1, 'vid' => 1)))->sort(array('uvi' => -1));
	$wsFound = false;
	while ($c->hasNext()) {
		$row = $c->getNext();
		if ( count($vehicle) == 0 || (!$wsFound && isset($row['vid']))) {
			if (isset($row['whereseen'])) {
				$wsFound = true;
			}
			$vids = $row['vid'];
			$note = isset($row['note']) ? $row['note'] : "" ;
			$oper = $row['operator'] ;
			$fnum = $row['fnum'] ;
			$cdreg = $row['cdreg'] ;
		}
		$vehicle[] = $row['uvi'] ;
	}
	if (output::getRequest()['cmd'] == 'HISTORY') {
		$lstthr = output::getRequest()['dow'] ;

		$match = array();
		if (count($vehicle) > 0) {
			$match['vid'] = array(
				'$in' => $vehicle
			);
		}
		$dmatch = array();
		if (!empty(output::getRequest()['ldate']) && !empty(output::getRequest()['fdate'])) {
			$lt = new MongoDate(strtotime(output::getRequest()['ldate']));
			$gt = new MongoDate(strtotime(output::getRequest()['fdate']));
			if ($lt < $gt) {
				list($lt, $gt) = array($gt, $lt);
			}
			$dmatch['$lte'] = $lt;
			$dmatch['$gte'] = $gt;
		} else if (!empty(output::getRequest()['fdate'])) {
			$dmatch = new MongoDate(strtotime(output::getRequest()['fdate']));
		}
		if (count($vehicle) == 0) {
			$match['route'] = $route;
		}
		if (count($dmatch) > 0) {
			$match['date'] = $dmatch;
		}

		$c = query(
			'lvf_history',
			'find',
			array(
				$match
			)
		)->sort(array('first_seen' => -1))->limit(200);

		if (count($vehicle) == 0) {
			output::getResponse()['extra']['route'] = $route;
		} else if ($c->hasNext()) {
			output::getResponse()['extra']['op'] = $oper ;
			output::getResponse()['extra']['fleetNumber'] = $fnum ;
			output::getResponse()['extra']['reg'] = $regs ;
			output::getResponse()['extra']['note'] = $note ;
		}

		$rows = array();
		$vids = array();
		while ($c->hasNext()) {
			$r = $c->getNext();
			$rows[] = $r;
			$vids[] = $r['vid'];
		}
		$c = query(
			'lvf_vehicles',
			'find',
			array(
				array(
					'uvi' => array(
						'$in' => $vids
					)
				),
				array(
					'_id' => 0,
					'uvi' => 1,
					'note' => 1,
					'cur_reg' => 1,
					'fnum' => 1
				)
			)
		);
		$buses = array();
		while ($c->hasNext()) {
			$bus = $c->getNext();
			$buses[$bus['uvi']] = $bus;
		}
		foreach ($rows as $row) {
			$bus = $buses[$row['vid']];

			$loadday = date("D", $row['date']->sec);
			debug::error(" Checking history day - " . $loadday . ", " . output::getRequest()['dow']);

			if (( $lstthr == '' ) || ($lstthr == $loadday )) {
				output::getResponse()['lines'][] = array(
					'first_time' => date("H:i", $row['first_seen']->sec),
					'last_time' => date("H:i", $row['last_seen']->sec),
					'line' => $row['lineid'],
					'route' => $row['route'],
					'fnum' => $bus['fnum'],
					'reg' => $bus['cur_reg'],
					'note' => isset($bus['note']) ? $bus['note'] : "",
					'when' => date("d-m-Y", $row['date']->sec)
				);
			}
		}
		if (empty(output::getResponse()['lines'])) {
			output::setError(count($vehicle) > 0 ? "No usage data for vehicle currently available" : "No usage data for vehicles on route " . $route . " currently available" );
		}
	} else if (!empty($vehicle)) {
		$c = query(
			'lvf_predictions',
			'find',
			array(
				array(
					'vid' => $vids
				)
			)
		)->sort(array('prediction' => 1));

		while ($c->hasNext()) {
			$r = $c->getNext();
			if (empty(output::getResponse()['lines'])) {
				output::getResponse()['extra']['operator'] = $oper ;
				output::getResponse()['extra']['fleetNumber'] = $fnum ;
				output::getResponse()['extra']['reg'] = $regs ;
				output::getResponse()['extra']['note'] = $note;
				output::getResponse()['extra']['route'] = $r['route'];
			}
			$when = date("H:i", $r['prediction']->sec) ;
			$a = stop::getFromId($r['stopid']) ;
			output::getResponse()['lines'][] = array(
				'when' => $when,
				'stop' => $r['stopid'],
				'stopName' => $a,
				'dest' => $r['destination']
			);
		}
		if (empty(output::getResponse()['lines'])) {
			output::setError("No data for vehicle currently available from countdown" );
		}
	}
}
function generate_list(&$vidarr) {
	event('list_req');
	$a = explode(" ", output::getRequest()['reg']);
	$listname = $a[0] ;
	debug::error("found vehicle list data, list " . $listname . " - a[0]= " . output::getRequest()['cmd'] . (isset($a[1]) ? " - a[1]= " . $a[1] : ""));
	if ( $listname == 'UNUSED' ) {
		$op = operators::getOperatorName($a[1]);

		$query = array('whereseen.last_seen' => (!empty(output::getRequest()['fdate']) ? array('$lt' => new MongoDate(strtotime(output::getRequest()['fdate']))) : array('$exists' => false)));
		if ( isset( $a[1] ) && $op != "Unknown") {
			$query['operator'] = $a[1];
		}
		$c = query('lvf_vehicles', 'find', array($query, array('uvi' => 1, '_id' => 0)))->sort(array('whereseen.last_seen' => -1, 'operator' => 1, 'sfnum' => 1));
		addToVidarr($c, $vidarr);
	} else {
		debug::error("found list data, list " . $listname . print_r($a, true));
		$c = query('lvf_vehicles', 'find', array(array('lists' => strtolower($listname)), array('uvi' => 1, 'note' => 1)))->sort(array('operator' => 1, 'sfnum' => 1));
		if ( isset( $a[1])) { $a[1] = strtoupper( $a[1]) ; }
		while (($c->hasNext()) && ( sizeOf($vidarr) < 180 )) {
			$row = $c->getNext();
			$note = strtoupper(isset($row['note']) ? $row['note'] : "") ;
			if (( !isset( $a[1])) || $a[1] == substr( $note, 0 , strlen($a[1]))) {
				$vidarr[] = $row['uvi'] ;
				debug::error("found vehicle list data, list " . $listname . " - vehicle id= " . end($vidarr));
			}
		}
	}
	debug::error("found vehicle list data, list " . $listname . " - num entries= " . sizeOf($vidarr));
	if (empty($vidarr)) {
		output::setError("Current lists are ads, new, ooffs and unused" );
	} else {
		output::getRequest()['cmd'] = "LIST";
	}
}
function search(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		$c = query('lvf_vehicles', 'find', array(array('cur_reg' => array('$regex' => '^' . str_replace(" ", "", output::getRequest()['reg']) . '$')), array('uvi' => 1, '_id' => 0)))->sort(array('operator' => 1, 'sfnum' => 1));
		if (!$c->hasNext()) {
			$c = query('lvf_vehicles', 'find', array(array('orig_reg' => array('$regex' => '^' . str_replace(" ", "", output::getRequest()['reg']) . '$')), array('uvi' => 1, '_id' => 0)))->sort(array('operator' => 1, 'sfnum' => 1));
		}

		addToVidarr($c, $vidarr);
		if (!empty($vidarr)) {
			output::getRequest()['cmd'] = "LIST";
		}
	}
}
function searchByFnum(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		// check first for fleetnumber - look for operator code
		$opername = operators::getOperatorName(output::getRequest()['op']);
		if ($opername != "Unknown") {
			$regex = "#([A-Z]{1,3})?([0-9]{1,5})#";
			$matches = array();
			preg_match($regex, output::getRequest()['fnum'], $matches);
			if (!empty(output::getRequest()['fnum2'])) {
				$lhs = $matches[2];
				preg_match($regex, output::getRequest()['fnum2'], $matches2);
				$rhs = $matches2[2];
				if (strlen($rhs) < strlen($lhs)) {
					$rhs = substr($lhs, 0, strlen($lhs) - strlen($rhs)) . $rhs;
				}
				if ($rhs < $lhs) {
					list($rhs, $lhs) = array($lhs, $rhs);
				}
				$range = array($matches[1] . $lhs, $matches[1] . $rhs);

				foreach ($range as $k => $v) {
					$row = query('lvf_vehicles', 'findOne', array(array('operator' => output::getRequest()['op'], 'fnum' => $v), array('rnfnum' => 1, '_id' => 0)));
					if (!is_null($row)) {
						$range[$k] = $row['rnfnum'];
					}
				}

				debug::error("looking up operator fleetnumber range info - Oper = " . output::getRequest()['op'] . " - first fleetnumber = " . $lhs . ", last fleetnumber = " . $rhs);
				addToVidarr(query('lvf_vehicles', 'find', array(array('operator' => output::getRequest()['op'], 'rnfnum' => array('$gte' => $range[0], '$lte' => $range[1])), array('uvi' => 1, '_id' => 0)))->sort(array('rnfnum' => 1)), $vidarr);
			} else {
				debug::error("looking up operator fleetnumber info - Oper = " . $opername . " - fleetnumber = " . output::getRequest()['fnum']);
				addToVidarr(query('lvf_vehicles', 'find', array(array('operator' => output::getRequest()['op'], 'fnum' => array('$regex' => '^' . output::getRequest()['fnum'] . '$', '$options' => 'i')), array('uvi' => 1, '_id' => 0)))->sort(array('rnfnum' => 1)), $vidarr);
			}
			
			if (empty($vidarr)) {
				if ((( output::getRequest()['op'] == "FLN" ) || ( output::getRequest()['op'] == "TT" )) && empty($matches[1])) {
					addToVidarr(query('lvf_vehicles', 'find', array(array('operator' => output::getRequest()['op'], 'fnum' => array('$regex' => '(.*)' . output::getRequest()['fnum'] . '$')), array('uvi' => 1, '_id' => 0)))->sort(array('rnfnum' => 1)), $vidarr);
				}
			}
			if (empty($vidarr)) {
				output::setError(output::getRequest()['op'] . " appears to start with a valid operator code, check that the fleetnumber is valid");
			} else {
				output::getRequest()['cmd'] = "LIST";
			}
		}
	}
}
function searchByRegrange(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		// check for registration range
		$ranreq = explode("-", output::getRequest()['reg'] );
		if (sizeOf($ranreq) == 2) {
			$ranreq[1] = substr($ranreq[0], 0, strlen($ranreq[0]) - strlen($ranreq[1])) . $ranreq[1];
			sort($ranreq);
			
			debug::error("registration range request, start range = " . $ranreq[0] . ", end range = " . $ranreq[1]);
			addToVidarr(query('lvf_vehicles', 'find', array(array('cur_reg' => array('$gte' => $ranreq[0], '$lte' => $ranreq[1])), array('uvi' => 1, 'sfnum' => 1)))->sort(array('sfnum' => 1)), $vidarr);
			
			if (!empty($vidarr)) {
				output::getRequest()['cmd'] = "LIST";
			}
		}
	}
}
function searchCountdown(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		event('veh_req');
		$c = query('lvf_vehicles', 'find', array(array('cdreg' => output::getRequest()['reg']), array('uvi' => 1)));
		addToVidarr($c, $vidarr);
		if (!empty($vidarr)) {
			output::getRequest()['cmd'] = "LIST";
		}
	}
}
function searchByRoute(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		if ( strlen(output::getRequest()['reg']) < 5 ) {
			event('route_req');
			if ( config::getInstance()->prevtime > date( "U" ) ) {
				config::getInstance()->prevtime = strtotime("today") ;
			}
			
			output::getRequest()['cmd'] = "ROUTE";
			$route = output::getRequest()['reg'] ;
			$c = query('lvf_destinations', 'find', array(array('route' => $route, 'lineid' => $route, 'day' => array('$exists' => false))));
			while ($c->hasNext()) {
				$row = $c->getNext();
				output::getResponse()['extra'][$route][($row['direction'] != 1) ? 'destination' : 'origin'] = $row['destination'] ;
				if ( $row['direction'] == 1 ) {
					$origin = $row['destination'] ;
				} else {
					$towards = $row['destination'] ;
				}
			}
			
			$originx = "" ;
			$towardsx = "" ;
			$xlines = array();
			$xsort = array();

			$c = query(
				'lvf_history',
				'find',
				array(
					array(
						'route' => $route,
						'date' => new MongoDate(strtotime("today"))
					)
				)
			);

			$vehicles = array();
			$history = array();
			while ($c->hasNext()) {
				$r = $c->getNext();
				$history[$r['vid']] = $r;
				$vehicles[] = $r['vid'];
			}

			$c = query(
				'lvf_vehicles',
				'find',
				array(
					array(
						'uvi' => array(
							'$in' => $vehicles
						)
					)
				)
			)->sort(array('whereseen.line_id' => 1, 'sfnum' => 1));

			$found_data = $c->hasNext();
			$checkdate = date( "d-m-Y" );

			$rows = array();
			while ($c->hasNext()) {
				$row = $c->getNext();
				$seen = $history[$row['uvi']];
				$lineid = $seen['lineid'];

				if (( $route != $lineid ) && ( $originx == "" )){
					$d = query('lvf_destinations', 'find', array(array('route' => $route, 'lineid' => $lineid, 'day' => array('$exists' => false))));
					while ($d->hasNext()) {
						$rowa = $d->getNext();
						if ( $rowa['direction'] == '1' ) {
								$originx = $rowa['destination'] ;
						} else {
								$towardsx = $rowa['destination'] ;
						}
						if ((( $rowa['direction'] == '1' ) && ($origin != $originx)) || (( $rowa['direction'] == '2' ) && ($towards != $towardsx))) {
							output::getResponse()['extra'][$lineid][($rowa['direction'] != 1) ? 'destination' : 'origin'] = $rowa['destination'] ;
						}
					}
				}

				$operator = operators::getOperatorName($row['operator']);

				if (( $row['whereseen']['last_seen']->sec < config::getInstance()->prevtime ) || ( $row['whereseen']['route'] != $route )) {
					if (!is_null($seen)) {
						$row['direction'] = "3";
						$xlines[] = array(
							'route' => $route,
							'first_time' => date("H:i", $seen['first_seen']->sec),
							'last_time' => date("H:i", $seen['last_seen']->sec),
							'reg' => $row['cur_reg'],
							'note' => isset($row['note']) ? $row['note'] : "",
							'fnum' => $row['fnum'],
							'op' => $row['operator'],
							'line' => $seen['lineid'],
							'direction' => 3
						);
						$xsort[] = $row['sfnum'];
					}
				} else {		
					$stop_info = stop::getFromId($row['whereseen']['nearest_stop']) ;

					$last_seen = $row['whereseen']['last_seen']->sec ;
					$when = date("H:i", $last_seen);

					$sdflag = 1 ;
					if ( $route != $lineid ){
						if ((( $row['whereseen']['dirid'] == '1' ) && ( $row['whereseen']['destination'] == $originx )) || (( $row['whereseen']['dirid'] == '2' ) && ( $row['whereseen']['destination'] == $towardsx ))) { $sdflag = 0 ; }
					} else {
						if ((( $row['whereseen']['dirid'] == '1' ) && ( $row['whereseen']['destination'] == $origin )) || (( $row['whereseen']['dirid'] == '2' ) && ( $row['whereseen']['destination'] == $towards ))) { $sdflag = 0 ; }
					}

					$out = array(
						'route' => $route,
						'dest' => $row['whereseen']['destination'],
						'stop' => $row['whereseen']['nearest_stop'],
						'stopName' => $stop_info,
						'when' => $when,
						'reg' => $row['cur_reg'],
						'note' => isset($row['note']) ? $row['note'] : "",
						'opName' => $operator,
						'fnum' => $row['fnum'],
						'direction' => $row['whereseen']['dirid'],
						'op' => $row['operator'],
						'line' => $lineid,
						'sdflag' => $sdflag
					) ;
					if ( $row['whereseen']['dirid'] == 1 ) {
						output::getResponse()['lines'][] = $out;
					} else {
						$blines[] = $out;
					}
					debug::error("found vehicle on route" . print_r(output::getResponse()['lines'], true));
				}
				if ( $route == $lineid ) {
					$opername1 = $operator ;
				} else {
					$opername2 = $operator ;
					$lineidx = $lineid ;
				}
			};

			if ( isset($opername1) ) {
				output::getResponse()['extra'][$route]['operators'] = $opername1 ;
			}
			if ( isset($opername2) ) {
				if (($origin != $originx) || ($towards != $towardsx)) {
					output::getResponse()['extra'][$lineidx]['operators'] = $opername2 ;
				} else {
					// I don't know if this is right, but it's better than undefined
					output::getResponse()['extra'][$route]['operators'] = $opername2 ;
				}
			}
			$blcount = 1 ;
			foreach ($blines as $bl) {
				output::getResponse()['lines'][] = $bl ;
				$blcount++ ;
			}
			$xlcount = 1 ;
			array_multisort($xsort, $xlines);
			foreach ($xlines as $xl) {
				output::getResponse()['lines'][] = $xl ;
				$xlcount++ ;
			}

			if ( !$found_data ) {
				$count = query(
					'lvf_history',
					'find',
					array(
						array(
							'route' => $route
						)
					)
				)->limit(1)->count(true);
				if ($count == 0) {
					output::setError(output::getRequest()['reg'] . " is not a valid route, for a vehicle add the operator code for a stop enter more than 5 characters" );
				} else {
					output::setError("Route " . output::getRequest()['reg'] . " has not been active today" );
				}
				event('error_req');
			} else {
				output::getRequest()['cmd'] = "ROUTE";
			}
		}
	}
}
function searchByStop(&$vidarr) {
	if ( empty(output::getRequest()['cmd'])) {
		$stops = array();
		$names = array();

		if (preg_match("#^([0-9]{5})(,[0-9]{5})*$#", output::getRequest()['reg'])) {
			$tempstops = explode(",", output::getRequest()['reg']);
			$c = query('lvf_stops', 'find', array(array('_id' => array('$in' => $tempstops))))->sort(array('_id' => 1));
			while ($c->hasNext()) {
				$stop = $c->getNext();
				$stops[] = $stop['_id'] ;
				$names[] = $stop['name'];
			}
		} else {
			$query = array('name' => array('$regex' => '^' . output::getRequest()['reg'] . '$', '$options' => 'i'));
			$c = query('lvf_stops', 'find', array($query))->sort(array('_id' => 1));
			while ($c->hasNext()) {
				$stop = $c->getNext();
				$stops[] = $stop['_id'] ;
				$names[] = $stop['name'];
			}
		}

		if (empty($stops)) {
			$query = array('name' => array('$regex' => output::getRequest()['reg'], '$options' => 'i'));
			$c = query('lvf_stops', 'find', array($query))->sort(array('_id' => 1));
			while ($c->hasNext()) {
				$stop = $c->getNext();
				$stops[] = $stop['_id'] ;
				$names[] = $stop['name'];
			}
		}

		$names = array_values(array_unique($names));
		if (sizeOf($names) > 1) {
			output::setError("'" . output::getRequest()['reg'] . "' has matched multiple stops names eg '" . $names[0] . "' and '" . $names[1] . "'" );
			return;
		} else if (!empty($names)) {
			event('stop_req');

			$c = query(
				'lvf_predictions',
				'find',
				array(
					array(
						'stopid' => array(
							'$in' => $stops
						)
					)
				)
			)->hint('stopid_1')->sort(array('prediction' => 1));

			$stopname = $names[0];
			output::getResponse()['extra']['name'] = $names[0];
			output::getResponse()['extra']['ids'] = $stops;
			
			$lastroute = '' ;
			$lastvid = '' ;
			$lastdest = '' ;
			$lastwhentime = '';
			$rows = array();
			$vids = array();

			while ($c->hasNext()) {
				$r = $c->getNext();
				$rows[$r['vid']] = $r;
				$vids[] = $r['vid'];
			}

			$c = query(
				'lvf_vehicles',
				'find',
				array(
					array(
						'vid' => array(
							'$in' => $vids
						)
					),
					array(
						'_id' => 0,
						'vid' => 1,
						'operator' => 1,
						'uvi' => 1,
						'orig_reg' => 1,
						'note' => 1,
						'cur_reg' => 1,
						'fnum' => 1
					)
				)
			);
			$vehicles = array();
			while ($c->hasNext()) {
				$row = $c->getNext();
				$vehicles[$row['vid']] = $row;
			}
			foreach ($rows as $vehicleid => $r) {
				$vehicleid = $r['vid'];
				$row = $vehicles[$vehicleid];

				$route = $r['route'];
				$lineid = $r['line_id'];
				$line = array('dest' => $r['destination'], 'route' => $route, 'line' => $lineid);
				
				if (!is_null($row)) {
					$oper = $row['operator'] ;
					$uvi = $row['uvi'] ;
					$origreg = $row['orig_reg'] ;
					$line['note'] = isset($row['note']) ? $row['note'] : "" ;
					$line['reg'] = $row['cur_reg'];
					$line['fnum'] = $row['fnum'] ;
					debug::error("found reg and oper code = " . $row['cur_reg'] . "  " . $oper);
					$line['op'] = $oper;
					$line['opName'] = operators::getOperatorName($oper);
				}

				$line['when'] = date("H:i", $r['prediction']->sec);
				if (( $lastroute != $route ) || ($lastvid != $vehicleid ) || ( $lastdest != $line['dest'] ) || ( $lastwhentime != $line['when'] )) {
					output::getResponse()['lines'][] = $line;
				}
				$lastroute = $route ;
				$lastvid = $vehicleid ;
				$lastdest = $line['dest'] ;
				$lastwhentime = $line['when'] ;
			}
		}
		if (!empty(output::getResponse()['lines'])) {
			output::getRequest()['cmd'] = "STOP";
		}
	}
}

function handleErrors() {
	if (empty(output::getResponse()['lines']) && !output::isError()) {
		$a = explode(" ", output::getRequest()['reg']);
		if (substr_count(output::getRequest()['reg'], "_") > 1) {
			output::setError(output::getRequest()['reg'] . " appears to contain multiple single character wildcards '_'. It is better to use the * char which wildcards multiple characters.");
		} else if (substr_count(output::getRequest()['reg'], "-") > 1) {
			output::setError(output::getRequest()['reg'] . " appears to contain multiple '-' characters. This character is used to specify ranges of which only one can be specified at a time.");
		} else if (strpos(output::getRequest()['reg'], "/") !== false) {
			output::setError(output::getRequest()['reg'] . " appears to contain a '/' character. Cannot be used except as part of a stop name.");
		} else if (strpos(output::getRequest()['reg'], "IPOD") !== false || strpos(output::getRequest()['reg'], "VOD") !== false) {
			output::setError("If you were trying to get a list of iPod or Vodafone advert buses you need to put the command 'lists ads' before the type of advert you want");
		} else if (substr(output::getRequest()['reg'], 0, 3) == "SMK") {
			output::setError("No routemasters with SMK registrations are entered into the database");
		} else if ((substr(output::getRequest()['reg'], 0, 7) == "HISTORY" || substr(output::getRequest()['reg'], 0, 7) == "ETA") && empty(output::getRequest()['cmd'])) {
			output::setError("Due to new features being introduced, the history and eta commands need to be followed by a space before the route number or vehicle identifier");
		} else if (strlen(output::getRequest()['reg']) > 10) {
			output::setError("You appear to have entered a stop name. Since stop names have to exactly match the TFL stop name use wildcards to increase your chance of getting the name right");
		} else if ($row = query('lvf_operators', 'findOne', array(array('err_code' => output::getRequest()['op']), array('_id' => 0, 'operator_code' => 1, 'operator_name' => 1)))) {
			output::setError(output::getRequest()['op'] . " is not the correct operator code for " .  $row['operator_name'] . ". Please use the operator code " . $row['operator_code']);
		} else if (!empty(output::getRequest()['fnum']) && $row = query('lvf_vehicles', 'findOne', array(array('fnum' => array('$regex' => output::getRequest()['fnum'])), array('operator' => 1, '_id' => 0)))) {
			output::setError("You appear to have entered a valid fleetnumber for operator " . $row['operator'] . ". To look up vehicles by fleetnumber, an operator code must be entered before the fleetnumber");
		} else if (!empty(output::getRequest()['reg']) && !is_null(query('lvf_vehicles', 'findOne', array(array('cur_reg' => array('$regex' => output::getRequest()['reg'])), array('_id' => 0))))) {
			output::setError("Your input appears to be part of a registration. To enter only part of a registration you must use a wildcard character, eg " . output::getRequest()['reg'] . "* to tell the program this is what you want" );
		} else if (preg_match("#^([A-Z]{1}|[A-Z]{3})([0-9]+)(.+)$#", output::getRequest()['reg'])) {
			output::setError("Your input appears to be part of a pre 2001 registration. Are you sure this is an active vehicle as the LVF database does not hold data on withdrawn vehicles");
		} else {
			output::setError("Your input does not match any vehicle or command in our database");
			debug::fatal("Unhandled error, Input=" . print_r(output::getRequest(), true));
		}
		event('error_req');
	}
}

/*
 * Vid Funtions
 */

function addToVidarr($cursor, &$vidarr, $max = 200) {
	while (($cursor->hasNext()) && ( sizeOf($vidarr) < $max )) {
		$vidarr[] = $cursor->getNext()['uvi'] ;
	}
}
function structureData($vidarr) {
	if (!empty($vidarr)) {
		$multi_vids = implode(",", $vidarr);
		debug::error(sizeOf($vidarr) . " items found for route " . output::getRequest()['reg'] . " Vehicle Ids = " . $multi_vids);
		$structure = array();
		$linecount = 0 ;

		$pos = array_flip($vidarr);
		$structure = array();

		$c = query('lvf_vehicles', 'find', array(array('uvi' => array('$in' => $vidarr)), array('_id' => 0, 'vid' => 1, 'pre' => 1, 'uvi' => 1, 'note' => 1, 'operator' => 1, 'fnum' => 1, 'cur_reg' => 1, 'whereseen' => 1)));
		while ($c->hasNext()) {
			$row = $c->getNext();
			$vid = $row['uvi'];

			$out = array();
			$out['note'] = empty($row['note']) ? "" : $row['note'];
			$out['reg'] = $row['cur_reg'];
			$out['op'] = $row['operator'] ;
			$out['fnum'] = $row['fnum'];
			$out['opName'] = operators::getOperatorName($out['op']);

			if (isset($row['whereseen'])) {
				$out['today'] = $row['whereseen']['last_seen']->sec > strtotime("today");
				$out['when'] = ($out['today'] ? date("H:i", $row['whereseen']['last_seen']->sec) : date("H:i d-m-Y", $row['whereseen']['last_seen']->sec));
				$out['route'] = $row['whereseen']['route'] ;
				//$out['direction'] = $row['whereseen']['dirid'] ;
				if (isset($row['whereseen']['destination'])) {
					$out['dest'] = $row['whereseen']['destination'] ;
					$out['stop'] = $row['whereseen']['nearest_stop'] ;
				}
				$out['line'] = $row['whereseen']['line_id'] ;
				$out['stopName'] = stop::getFromId($out['stop']) ;
			} else {
				debug::error("failed to find where seen record in get_vinfo, vid = " . $vehicleId);
				if ( !isset($row['vid']) && (!isset($row['pre']) || !$row['pre']) ) {
					$out['dest'] = 'withdrawn' ;
				} else {
					$out['dest'] = 'unknown' ;
				}
			}
			$structure[$pos[$vid]] = $out;
		}
		ksort($structure);
		output::getResponse()['lines'] = array_merge($structure, output::getResponse()['lines']);
	}
}
?>
