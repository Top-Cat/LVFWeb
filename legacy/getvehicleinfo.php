<?php

function new_get_dest ( $route, $dir, $extra ) {

	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$nowdate = date( "Y-m-d" ) ;
	$day = date( "D"  ) ;
	$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $day . "'");
	if ($row = mysql_fetch_assoc($result)) {
//		$audit_text = "Found Destination for Day - Route number = " . $route . ", Direction = " . $dir . ", Day= " . $day	;
//		mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
		$dest = $row['destination'] ;
	}
	else {
		if ( $extra == 'xxx' ) {
			$result = mysql_query("SELECT destination, day FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day != '' AND day NOT LIKE '%" . $route . "%'");
		} else {
			$result = mysql_query("SELECT destination, day  FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $extra . "'");
		}
		if ($row = mysql_fetch_assoc($result)) {
			$dest = $row['destination'] ;
			$extra = $row['day'] ;
		}
		else {
//			$result = mysql_query("SELECT destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators  FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '' ");
			$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '' ");
			if ($row = mysql_fetch_assoc($result)) {
				$dest = $row['destination'] ;
			}
			else {
				if (( $route != '' ) && ( $dir != '' )) {
					mysql_query("INSERT INTO lvf_stats_events (evname, evdate, evtime) VALUES ( 'dest_req', '" . date( "Y-m-d" ) . "', '" . date( "H:i:s" ) . "') ");
					$ch = curl_init();
					$request_str = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=" . $route . "&ReturnList=directionid,destinationtext" ;
//					$audit_text = "Destination lookup - Route number = " . $route . ", Direction = " . $dir . ", TFL enquiry= " . $request_str ;
//					mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
					curl_setopt($ch, CURLOPT_URL, $request_str );
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					$data = explode("\n", curl_exec($ch));
					curl_close($ch);
					$dirs = array() ;
					$dirs[1] = "" ;
					$dirs[2] = "" ;
					foreach ($data as $row) {
						$r = json_decode($row);
						if ($r[0] == 1) {
							if ( $r[1] == 1 ) {
								$dirs[1] = s($r[2]) ;
							}
							else {
								$dirs[2] = s($r[2]) ;
							}
						}
					}
					if ( $dir == 1 ) {
						$dest = $dirs[1] ;
					}
					else {
						$dest = $dirs[2] ;
					}
				}
				else {
					$dest = 'unknown' ;
				}
			}
		}
	}
	return Array( $dest, $extra ) ;
}
//107
function get_stop_info ( $stop_id, $route ) {
	error_log("Help!");
	return "fix me";
	date_default_timezone_set('Europe/London');
	$nowdate = date( "Y-m-d" ) ;
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$stop_info = "no stop info" ;
	$row = query('lvf_stops', 'findOne', array(array('_id' => $stop_id)));
	if (!is_null($row)) {
		$stop_info = $row['name'] ;
	} else {
		if ( strlen( $stop_id ) == 5 ) {
			event('stxt_req');

			$data = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=" . $route . "&ReturnList=StopCode1,StopPointName", "Stop lookup - Route number = " . $route . ", Stop Id = " . $stop_id . ", TFL enquiry= ");
			foreach ($data as $row) {
				$r = json_decode($row);
				if ($r[0] == 0) {
					if ($r[2] == $stop_id) {
						$stop_info = $r[1];
					}
					query(
						'lvf_stops',
						'update',
						array(
							array(
								'_id' => intval($r[2])
							),
							array(
								'$set' => array(
									'name' => $r[1]
								)
							),
							array(
								'upsert' => true
							)
						)
					);
				}
			}
		}
	}
	return $stop_info ;
}
//144
function update_vlocation($vids) {
	event('veh_req');
	$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?VehicleId=" . $vids . "&ReturnList=LineName,lineId,DirectionId,DestinationText,StopCode1,EstimatedTime,RegistrationNumber,VehicleId");
	$busArr = array();
	foreach ($response as $row) {
		$r = json_decode($row);
		if ($r[0] == 1) {
			if (!isset($busArr[$r[6]]) || $busArr[$r[6]][8] > $r[8]) {
				if ( $r[1] == 'NONE' ) {
					debug::error("Stopid NONE seen in data (line ignored) = " . implode(" ", $r));
				} else {
					$busArr[$r[6]] = $r;
				}
			}
		}
	}
	$vlcount = 0 ;
	$updatews = 0 ;
	foreach ($busArr as $r) {
		$vlcount++ ;
		$stopid = s($r[1]) ;
		$lineid = s($r[2]) ;
		$route = s($r[3]) ;
		$dirn = s($r[4]) ;
		$dest = s($r[5]) ;
		$vid = s($r[6]) ;
		$creg = s($r[7]) ;
		$expect_time = (s($r[8])/1000);
		if ( $vid == 0 ) {
			debug::error("update_vlocation zero vid in where seen data = " . implode(" ", $r));
		} else {
			check_veh_update ( $vid, $creg ) ;

			$mdate = new MongoDate();
			$ldate = new MongoDate($expect_time);
			query(
				'lvf_vehicles',
				'update',
				array(
					array(
						'vid' => $vid,
						'$or' => array(
							array('last_seen' => array('$lt' => $mdate)),
							array('last_seen' => array('$exists' => false)),
						)
					),
					array(
						'$set' => array(
							'last_seen' => $mdate,
							'whereseen' => array(
								'route' => $route,
								'line_id' => $lineid,
								'last_seen' => $ldate,
								'nearest_stop' => intval($stopid),
								'dirid' => $dirn,
								'destination' => $dest
							)
						),
						'$push' => array(
							'route_event' => array(
								'date' => $mdate,
								'lineid' => $lineid,
								'route' => $route
							)
						)
					),
					array(
						'w' => 0
					)
				)
			);

			debug::error("about to update route event record, last_seen = " . date("Y-m-d H:i") . ", vid=  " . $vid . ", lineid = " . $lineid);
		}
	}
	debug::error($updatews . " where seen records updated");
}

function get_vinfo ($vehicleId) {
	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$checkdate = date( "d-m-Y" ) ;

	$row = query('lvf_vehicles', 'findOne', array(array('vid' => $vehicleId)));
	if (!is_null($row)) {
		$rreg = $row['cur_reg'] ;
		$note = $row['note'] ;
		$oper = $row['operator'] ;
		$fnumber = $row['fnum'] ;
		if ( $note == '' ) {
			$note = $rreg ;
		}
		$when = '' ;
		$routenum = '' ;
		$dest = '' ;
		$dirn = '' ;
		$stop_info = '' ;
		$stop_id = '' ;
		$lineid = '' ;
		$opername = " " ;
		$_oper = query('lvf_operators', 'findOne', array(array('operator_code' => $oper)));
		if (!is_null($_oper)) {
			$opername = $_oper['operator_name'] ;
		}

		if (isset($row['whereseen'])) {
			$last_seen = $row['whereseen']['last_seen'] ;
			$routenum = $row['whereseen']['route'] ;
			$dirn = $row['whereseen']['dirid'] ;
			$dest = $row['whereseen']['destination'] ;
			$stop_id = $row['whereseen']['nearest_stop'] ;
			$lineid = $row['whereseen']['line_id'] ;
			if ( strlen ( $dest) < 2 ) {
				$desta = array() ;
				$desta = new_get_dest ( $routenum, $dirn, $lineid ) ;
				$dest = $desta[0] ;
			}
			$when = date("H:i", $last_seen);
			$stop_info = get_stop_info ( $stop_id, $routenum ) ;
		} else {
			debug::error("failed to find where seen record in get_vinfo, vid = " . $vehicleId);
			if ( $vehicleId > 98000 ) {
				$dest = 'withdrawn' ;
			} else {
				$dest = 'unknown' ;
			}
		}
	} else {
		debug::error("failed to find vehicle record in get_vinfo, vid = " . $vehicleId);
		return false;
	}
	return array(
		'route' => $routenum,
		'dest' => $dest,
		'stop' => $stop_id,
		'stopName' => $stop_info,
		'when' => $when,
		'note' => $note,
		'opName' => $opername,
		'fnum' => $fnumber,
		'direction' => $dirn,
		'op' => $oper,
		'line' => $lineid
	);
}
?>