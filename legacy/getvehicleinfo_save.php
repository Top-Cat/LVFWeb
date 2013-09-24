<?php

function get_dest ( $route, $dir, $extra ) {

	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$nowdate = date( "Y-m-d" ) ;
	$day = date( "D"  ) ;
	$result = mysql_query("SELECT destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $day . "'");
	if ($row = mysql_fetch_assoc($result)) {
//		$audit_text = "Found Destination for Day - Route number = " . $route . ", Direction = " . $dir . ", Day= " . $day	;
//		mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
		$dest = $row['destination'] ;
		$sht_dest = $row['short_dest'] ;
		$dest_cnt = $row['dest_cnt'] ;
		$sht_dest_cnt = $row['sd_cnt'] ;
		$other_dest_cnt = $row['other_sd_cnt'] ;
		$opers = $row['operators'] ;
	}
	else {
		if ( $extra == 'xxx' ) {
			$result = mysql_query("SELECT destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators, day  FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day != '' AND day NOT LIKE '%" . $route . "%'");
		} else {
			$result = mysql_query("SELECT destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators, day  FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $extra . "'");
		}
		if ($row = mysql_fetch_assoc($result)) {
			$dest = $row['destination'] ;
			$sht_dest = $row['short_dest'] ;
			$dest_cnt = $row['dest_cnt'] ;
			$sht_dest_cnt = $row['sd_cnt'] ;
			$other_dest_cnt = $row['other_sd_cnt'] ;
			$opers = $row['operators'] ;
			$extra = $row['day'] ;
		}
		else {
			$result = mysql_query("SELECT destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators  FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '' ");
			if ($row = mysql_fetch_assoc($result)) {
				$dest = $row['destination'] ;
				$sht_dest = $row['short_dest'] ;
				$dest_cnt = $row['dest_cnt'] ;
				$sht_dest_cnt = $row['sd_cnt'] ;
				$other_dest_cnt = $row['other_sd_cnt'] ;
				$opers = $row['operators'] ;
			}
			else {
				if (( $route != '' ) && ( $dir != '' )) {
					mysql_query("UPDATE lvf_hits SET dest_req = dest_req + 1 WHERE date = '" . $nowdate . "'");
					$ch = curl_init();
					$request_str = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=" . $route . "&ReturnList=directionid,destinationtext" ;
					$audit_text = "Destination lookup - Route number = " . $route . ", Direction = " . $dir . ", TFL enquiry= " . $request_str ;
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
//					$audit_text = "found destinations, route= " . $route . " direction 1 = " . $dirs[1] . " direction 2 = " . $dirs[2] ;
//					mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
					if ( $dirs[1] != "" ) {
						mysql_query("INSERT INTO lvf_destinations ( route, direction, destination, short_dest, day, dest_cnt, sd_cnt, other_sd_cnt ) VALUES ('" . $route . "', 1, '" . $dirs[1] . "', '', '', 0, 0, 0 )");
						$audit_text = "Added destination to destination table, route= '" . $route . "' direction = 1 Destination = " . $dirs[1] . ", Extra = " ;
						mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
					}
					if ( $dirs[2] != "" ) {
						mysql_query("INSERT INTO lvf_destinations ( route, direction, destination, short_dest, day, dest_cnt, sd_cnt, other_sd_cnt ) VALUES ('" . $route. "', 2, '" . $dirs[2] . "', '', '', 0, 0, 0 )");
						$audit_text = "Added destination to destination table, route= '" . $route . "' direction = 2 Destination = " . $dirs[2] . ", Extra = " ;
						mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
					}
					if ( $dir == 1 ) {
						$dest = $dirs[1] ;
					}
					else {
						$dest = $dirs[2] ;
					}
					$sht_dest = '' ;
					$dest_cnt = 0 ;
					$sht_dest_cnt = 0 ;
					$other_dest_cnt = 0 ;
					$opers = '' ;
				}
				else {
					$dest = 'Unknown' ;
					$sht_dest = '' ;
					$dest_cnt = 0 ;
					$sht_dest_cnt = 0 ;
					$other_dest_cnt = 0 ;
					$opers = '' ;
				}
			}
		}
	}
	return Array( $dest, $sht_dest, $dest_cnt, $sht_dest_cnt, $other_dest_cnt, $opers, $extra ) ;
}
//107
function get_stop_info ( $stop_id, $route ) {
	date_default_timezone_set('Europe/London');
	$nowdate = date( "Y-m-d" ) ;
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$stop_info = "no stop info" ;
	$result = mysql_query("SELECT StopName FROM lvf_stops WHERE StopId = '" . $stop_id . "'");
	if ($row = mysql_fetch_assoc($result)) {
		$stop_info = $row['StopName'] ;
	}
	else {
		if ( strlen( $stop_id ) == 5 ) {
			mysql_query("UPDATE lvf_hits SET stxt_req = stxt_req + 1 WHERE date = '" . $nowdate . "'");

			$ch = curl_init();
			$request_str = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=" . $route . "&ReturnList=StopCode1,StopPointName" ;
			$audit_text = "Stop lookup - Route number = " . $route . ", Stop Id = " . $stop_id . ", TFL enquiry= " . $request_str ;
			mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
			mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "')");
			curl_setopt($ch, CURLOPT_URL, $request_str );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$data = explode("\n", curl_exec($ch));
			curl_close($ch);
			foreach ($data as $row) {
				$r = json_decode($row);
				if ($r[0] == 0) {
					mysql_query("INSERT INTO lvf_stops ( StopId, StopName ) VALUES ('" . s($r[2]) . "', '" . s($r[1]) . "')");
				}
			}
			$result = mysql_query("SELECT StopName FROM lvf_stops WHERE StopId = '" . $stop_id . "'");
			if ($row = mysql_fetch_assoc($result)) {
				$stop_info = $row['StopName'] ;
			}
		}
	}
	return $stop_info ;
}
//144
function upd_dest ( $route, $dir, $extra, $dest_cnt, $sht_dest, $sht_dest_cnt, $odest_cnt ) {
	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$day = date( "D"  ) ;
	$audit_text = "destination counters route= " . $route . ", dir= " . $dir . ", extra= " . $extra . " sht_dest= " .  $sht_dest . ", dest_cnt= " .  $dest_cnt . ", sht_dest_cnt= " . $sht_dest_cnt . ", other dest cnt " . $odest_cnt ;
//	mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "' , '" . $nowdatetime . "')");
	$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $day . "'");
	if ($row = mysql_fetch_assoc($result)) {
		mysql_query("UPDATE lvf_destinations SET dest_cnt= '" . $dest_cnt . "', short_dest= '" . $sht_dest . "', sd_cnt= '" . $sht_dest_cnt . "', other_sd_cnt= '" . $odest_cnt . "' WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day= '" . $day . "'");
		$extra = $day ;
	}
	else {
		$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $extra . "'");
		if ($row = mysql_fetch_assoc($result)) {
			mysql_query("UPDATE lvf_destinations SET dest_cnt= '" . $dest_cnt . "', short_dest= '" . $sht_dest . "', sd_cnt= '" . $sht_dest_cnt . "', other_sd_cnt= '" . $odest_cnt . "' WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day= '" . $extra . "'");
		}
		else {
			mysql_query("UPDATE lvf_destinations SET dest_cnt= '" . $dest_cnt . "', short_dest= '" . $sht_dest . "', sd_cnt= '" . $sht_dest_cnt . "', other_sd_cnt= '" . $odest_cnt . "' WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day= ''");
			$extra = '' ;
		}
	}

	if ( $sht_dest_cnt > $dest_cnt ) {
		$audit_text = "short destination more often than set destination, route= " . $route . ", dir= " . $dir . ", extra= " . $extra . " sht_dest= " .  $sht_dest . ", dest_cnt= " .  $dest_cnt . ", sht_dest_cnt= " . $sht_dest_cnt . ", other dest cnt " . $odest_cnt ;
		mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "' , '" . $nowdatetime . "')");
		$destdiff = $sht_dest_cnt - $dest_cnt ;
		$destlmt = $dest_cnt/2 ;
		if ( $destdiff > $destlmt ) {
			$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = '" . $extra . "'");
			$odest = $row['destination'] ;
			$audit_text = "changing destination, adding extra SD - route= " . $route . ", dir= " . $dir . ", extra= " . $extra . ", dest= " .  $odest .  ", sht_dest= " .  $sht_dest . ", dest_cnt= " .  $dest_cnt . ", sht_dest_cnt= " . $sht_dest_cnt . ", other dest cnt " . $odest_cnt ;
			mysql_query("INSERT INTO lvf_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "' , '" . $nowdatetime . "')");
			$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = 'SD'");
			if ($row = mysql_fetch_assoc($result)) {
				$result = mysql_query("SELECT destination FROM lvf_destinations WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day = 'SD1'");
				if ($row = mysql_fetch_assoc($result)) {
					mysql_query("INSERT INTO lvf_destinations ( route, direction, day, destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators ) VALUES ( '" . $route . "', '" . $dir . "', 'SD2', '" .  $sht_dest . "', 0, '', 0, 0, '' )");
				} else {
					mysql_query("INSERT INTO lvf_destinations ( route, direction, day, destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators ) VALUES ( '" . $route . "', '" . $dir . "', 'SD1', '" .  $sht_dest . "', 0, '', 0, 0, '' )");
				}
			} else {
				mysql_query("INSERT INTO lvf_destinations ( route, direction, day, destination, dest_cnt, short_dest, sd_cnt, other_sd_cnt, operators ) VALUES ( '" . $route . "', '" . $dir . "', 'SD', '" .  $sht_dest . "', 0, '', 0, 0, '' )");
			}
			mysql_query("UPDATE lvf_destinations SET short_dest= '', sd_cnt= '0' WHERE route = '" . $route . "' AND direction = '" . $dir . "' AND day= ''");
		}		
	}
}

function update_vlocation($vids, &$data) {
	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$day = date( "D"  ) ;
	$nowdate = date( "Y-m-d" ) ;
	$nowtime = date( "H:i:s" ) ;

	$result = mysql_query("SELECT date_offset FROM lvf_config");
	$row = mysql_fetch_assoc($result) ;
	$utc_offset = $row['date_offset'] ;

	mysql_query("UPDATE lvf_hits SET veh_req = veh_req + 1 WHERE date = '" . $nowdate . "'");
	$response = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?VehicleId=" . $vids . "&ReturnList=LineName,lineId,DirectionId,DestinationText,StopCode1,EstimatedTime,RegistrationNumber,VehicleId", $data);
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
		$expect_time = (s($r[8])/1000) + $utc_offset ;
		$when = gmdate("Y-m-d H:i:s", $expect_time) ;
		if ( $vid == 0 ) {
			debug::error("update_vlocation zero vid in where seen data = " . implode(" ", $r));
		} else {
			check_veh_update ( $vid, $creg ) ;
			$result = mysql_query("SELECT uvi, orig_reg FROM lvf_vehicles WHERE vid = '" . $vid . "'");
			if ($row = mysql_fetch_assoc($result)) {
				$uvi = $row['uvi'] ;
				$origreg = $row['orig_reg'] ;
				mysql_query("UPDATE lvf_where_seen SET route = '" . $route . "', line_id = '" . $lineid . "', last_seen = '" . $when . "', nearest_stop = '" . $stopid . "', dirid = '" . $dirn . "', destination = '" . $dest . "' WHERE vid = '" . $vid . "'") ;
				debug::error("about to update route day record, last_seen = " . $nowtime . ", vid=  " . $vid . ", lineid = " . $lineid . ", nowdate = " . $nowdate);
				mysql_query("UPDATE lvf_route_day SET last_seen = '" . $nowtime . "' WHERE vehicle_id = '" . $uvi . "' AND lineid = '" . $lineid . "' AND date = '" . $nowdate . "'") ;
				$updatews++ ;
				if ( mysql_affected_rows() == 0 ) {
					mysql_query("INSERT INTO lvf_route_day (route, lineid, last_seen, first_seen, vehicle_id, date, registration) VALUES ('" . $route . "', '" . $lineid . "', '" . $nowtime . "', '" . $nowtime . "' , '" . $uvi . "', '" . $nowdate . "', '" . $origreg .  "')");
				}
			}
		}
	}
	debug::error($updatews . " where seen records updated");
}

function get_vinfo ($vehicleId) {
	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$checkdate = date( "d-m-Y" ) ;

	$result = mysql_query("SELECT note, operator, fnum, cur_reg FROM lvf_vehicles WHERE vid = '" . $vehicleId . "'");
	if ($row = mysql_fetch_assoc($result)) {
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
		$result = mysql_query("SELECT operator_name FROM lvf_operators WHERE operator_code = '" . $oper . "'");
		if ($row = mysql_fetch_assoc($result)) {
			$opername = $row['operator_name'] ;
		}
		$result = mysql_query("SELECT route, nearest_stop, last_seen, dirid, destination, line_id FROM lvf_where_seen WHERE vid = '" . $vehicleId . "'");
		if ($row = mysql_fetch_assoc($result)) {
			$last_seen = $row['last_seen'] ;
			$routenum = $row['route'] ;
			$dirn = $row['dirid'] ;
			$dest = $row['destination'] ;
			$stop_id = $row['nearest_stop'] ;
			$lineid = $row['line_id'] ;
			if ( strlen ( $dest) < 2 ) {
				$desta = array() ;
				$desta = get_dest ( $routenum, $dirn, $lineid ) ;
				$dest = $desta[0] ;
			}
			$when = substr ( $last_seen, 11, 5 ) . " " . substr ( $last_seen, 0, 10 ) ;
			$whendate = substr( $when, 0, 6 ) . substr( $when, 14, 2) . substr( $when, 10, 4) . substr( $when, 6, 4) ;
			$when = $whendate ;
			$whendate = substr( $when, 6, 10 ) ;
			if ( $whendate == $checkdate ) {
				$when = substr( $when, 0, 5 ) ;
			}
			$stop_info = get_stop_info ( $stop_id, $routenum ) ;
		} else {
			debug::error("failed to find where seen record in get_vinfo, vid = " . $vehicleId);
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