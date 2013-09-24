#!/usr/local/bin/php
<?php

include "connect.php";
include "../check_vehicle.php";

$data = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?RegistrationNumber=zzzzz&ReturnList=LineName");
$r = json_decode($data[0]);
if ( $r[0] == 4 ) {
	$tfltime = $r[2]/1000 ;
}

$loctime = date("U");
if ( $tfltime > $loctime ) {
	$difftime = $tfltime - $loctime ;
} else {
	$difftime = $loctime - $tfltime ;
}

config::getInstance()->cronStart();


$data = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?RegistrationNumber=%&ReturnList=LineName,LineId,destinationtext,DirectionId,StopCode1,EstimatedTime,RegistrationNumber,VehicleId");

if ( config::getInstance()->lastbatch->sec > date("U") ) {
	debug::info("current nowdatetime = " . date("Y-m-d H:i") . " previous nowtime = " . date("Y-m-d H:i", config::getInstance()->lastbatch->sec) . " - no processing to take place");
} else {
	config::getInstance()->dataFetched();

	$none_count = 0;
	$busArr = array();
	$rdArr = array();
	foreach ($data as $row) {
		$r = json_decode($row);
		if ($r[0] == 1) {
			if (!isset($busArr[$r[6]]) || $busArr[$r[6]][8] > $r[8]) {
				if ( $r[1] == 'NONE' ) {
					$none_count++ ;
				} else {
					$busArr[$r[6]] = $r;
				}
			}
		}
	}

	if ( date("G") == 0 && date("i") < 15 ) {
		query(
			'lvf_vehicles',
			'update',
			array(
				array(),
				array(
					'$unset' => array(
						'nfy' => 1
					)
				),
				array(
					'multiple' => true
				)
			)
		);
		query(
			'lvf_destinations',
			'update',
			array(
				array(),
				array(
					'$set' => array(
						'dest_cnt' => 0,
						'notify' => false
					)
				),
				array(
					'multiple' => true
				)
			)
		);
	}

	$num_entries = 0 ;
	$insert_route_day = 0 ;

	foreach ($busArr as $r) {
		$num_entries++ ;
		$stop_id = s($r[1]) ;
		$lineid = s($r[2]) ;
		$route = s($r[3]) ;
		$dir = s($r[4]) ;
		$dest = s($r[5]) ;
		$vid = s($r[6]) ;
		$creg = s($r[7]) ;

		// save route destination data
		$rdArr[] = array($route, $lineid, $dir, $dest);

		if (( $vid == '0' ) || ( $vid == '' )) {
			debug::info("record with null vid = " . $r[0] . ", " . $r[1] . ", " . $r[2] . ", " . $r[3] . ", " . $r[4] . ", " . $r[5] . ", " . $r[6] . ", " . $r[7] . ", " . $r[8]);
		} else {
			$tfltime = (s($r[8])/1000) ;
			if ( $tfltime < $loctime ) {
				  = $loctime - $tfltime ;
				if ( $difftime > 60 ) {
					debug::info("record with time before now vid= " . $vid . " nowdatetime = " . $nowdatetime . " expected time = " . $when);
				}
			}

			check_veh_update ( $vid, $creg ) ;

			if (( $vid == '0' ) || ( $vid == '' )) {
				debug::info("record with null vid, third check = " . $r[0] . ", " . $r[1] . ", " . $r[2] . ", " . $r[3] . ", " . $r[4] . ", " . $r[5] . ", " . $r[6] . ", " . $r[7] . ", " . $r[8]);
			} else {
				$mdate = new MongoDate();
				$ldate = new MongoDate($tfltime);
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
									'dirid' => $dir,
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
			}
		}
	}

	asort( $rdArr ) ;
	$lastroute = "" ;
	$lastline = "" ;
	$lastdir = 0 ;
	$lastdest = "" ;
	$destcount = 0 ;
	foreach ($rdArr as $rdrec) {
		if (( $rdrec[0] == $lastroute ) && ( $rdrec[1] == $lastline ) && ( $rdrec[2] == $lastdir ) && ( $rdrec[3] == $lastdest )) {
			$destcount++ ;
		} else {
			if ( $lastroute != " " ) {
				update_destrec ( $lastroute, $lastline, $lastdir, $destcount, $lastdest ) ;
			}
			$lastroute = $rdrec[0] ;
			$lastline = $rdrec[1] ;
			$lastdir = $rdrec[2] ;
			$lastdest = $rdrec[3] ;
			$destcount = 1 ;
		}
	}
	update_destrec ( $lastroute, $lastline, $lastdir, $destcount, $lastdest ) ;

	config::getInstance()->updateWhereSeen();

	if ( date("G") == 0 && date("i") < 15 ) {
		$stopcnt = 0 ;
		$stopsame = 0 ;
		$stopchng = 0 ;
		$stopadds = 0 ;
		$stopign = 0 ;

		$data = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?LineName=%&ReturnList=StopCode1,StopPointName");
		foreach ($data as $row) {
			$stopcnt++ ;
			$r = json_decode($row);
			if ($r[0] == 0) {
				if (( $r[2] == 'NULL' ) || ( $r[2] == 'null' ) || ( $r[2] == '' ) || ( $r[2] == 'NONE' )) {
					$stopign++ ;
				} else {
					$newstopname = trim(str_replace(array("'", " / ", " /"), array("", "/", "/"), $r[1] ));
					$row = query(
						'lvf_stops',
						'findAndModify',
						array(
							array(
								'_id' => intval($r[2])
							),
							array(
								'$set' => array(
									'name' => $newstopname
								)
							),
							array(
								'_id' => 0
							),
							array(
								'upsert' => true
							)
						)
					);
					if (!is_null($row)) {
						if ( $newstopname == $row['name'] ) {
							$stopsame++ ;
						} else {
							debug::info("Stop entry updated - StopId= " . $r[2] . ", New Stoptext= " . $newstopname . ", Old stop text= " . $row['name']);
							$stopchng++ ;
						}
					} else {
						debug::info("New entry for Stop table - StopId= " . $r[2] . ", Stoptext= " . $newstopname);
						$stopadds++ ;
					}
				}
			}
		}
		debug::info("Lines of stop data= " . $stopcnt . ", ignored= " . $stopign . ", same= " . $stopsame . ", records updated= " . $stopchng . ", records added= " . $stopadds);

// calculate number of days since last writing usage data
	$todaydt = strtotime( $nowdatetime ) ;
	$lastflush = strtotime( $purgeday ) ;
	$daysinfile = 2 ;
	$numdays = intval(($todaydt - $lastflush )/(60*60*24)) ;
	if ( $numdays > $daysinfile ) {
		$startdate = date ("Ymd", $lastflush ) ;
		$enddt = $lastflush + (60*60*24*( $daysinfile - 1)) ; 
		$nextdt = $lastflush + (60*60*24*$daysinfile) ; 
		$enddate = date ("Ymd", $enddt ) ;
		$nextdate = date ("Y-m-d", $nextdt ) ;
		$enddatesel = date ("Y-m-d", $enddt ) ;
		$filename = "lvf_route_day " . $startdate . "-" . $enddate . ".csv" ;
		$audit_text = "cron job has usage records to flush to disk - number of days = " . $numdays . " filename = " . $filename . ", next purge date = " . $nextdate ;
		mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
		$fhandle = fopen( $filename, "w" ) ;
		fwrite( $fhandle, "vehicle_id,lineid,date,route,last_seen,first_seen,registration\r\n" ) ;
		$result = mysql_query("SELECT * FROM lvf_route_day WHERE date >= '" . $purgeday . "' AND date <= '" . $enddatesel . "' order by date, vehicle_id");
		while ($row = mysql_fetch_assoc($result)) {
			$csvline = $row['vehicle_id'] . "," . $row['lineid'] . "," . $row['date'] . "," . $row['route'] . "," . $row['last_seen'] . "," . $row['first_seen'] . "," . $row['registration'] . "\r\n" ;
			fwrite( $fhandle, $csvline ) ;
		}
		fclose( $fhandle ) ;
		mysql_query("UPDATE lvf_config SET ArchiveDate = '" . $nextdate . "'") ;
 	}
	 
// update hits record for the last day

	$result = mysql_query("SELECT date, requests, route_req, veh_req, stop_req, history, eta, list_req, error_req, dumpv, sing_req, dest_req, stxt_req FROM lvf_hits ORDER BY date desc LIMIT 1");
	$row = mysql_fetch_assoc($result) ;
	$rowdate = $row['date'] ;
	$prdate = substr( $rowdate, 8, 2) . substr( $rowdate, 4, 4) . substr( $rowdate, 0, 4) ;
	$reqs = $row['requests'] ;
	$routereq = $row['route_req'] ;
	$vehreq = $row['veh_req'] ;
	$stopreq = $row['stop_req'] ;
	$histreq = $row['history'] ;
	$listreq = $row['list_req'] ;
	$etareq = $row['eta'] ;
	$errreq = $row['error_req'] ;
	$dumreq = $row['dumpv'] ;
	$singreq = $row['sing_req'] ;
	$destreq = $row['dest_req'] ;
	$stxtreq = $row['stxt_req'] ;
	$audit_text = "read from lvf_hits, date= " . $rowdate . ", reqs= " . $reqs . ", routereq= " . $routereq . ", veh_req= " . $vehreq . ", stopreq= " . $stopreq . ", history= " . $histreq . ", listreq= " . $listreq . ", eta= " . $etareq . ", errorreq= " . $errreq . ", dumpv= " . $dumreq . ", sing_req= " . $singreq . ", dest_req= " . $destreq . ", stxtreq= " . $stxtreq ;
	mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");

	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'request' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$reqs += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'route_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$routereq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'veh_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$vehreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'stop_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$stopreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'HISTORY' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$histreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'list_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$listreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'ETA' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$etareq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'error_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$errreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'dumpv' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$dumreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'sing_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$singreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'dest_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$destreq += $rowr['count(*)'] ;
	$resultr = mysql_query("SELECT count(*) FROM lvf_stats_events WHERE evname = 'stxt_req' AND evdate = '" . $rowdate . "'");
	$rowr = mysql_fetch_assoc($resultr) ;
	$stxtreq += $rowr['count(*)'] ;
	mysql_query("UPDATE lvf_hits SET requests= '" . $reqs . "', route_req= '" . $routereq . "', veh_req= '" . $vehreq . "', stop_req= '" . $stopreq . "', history= '" . $histreq . "', eta= '" . $etareq . "', list_req= '" . $listreq . "', error_req= '" . $errreq . "', dumpv= '" . $dumreq . "', sing_req= '" . $singreq . "', dest_req= '" . $destreq . "', stxt_req= '" . $stxtreq . "' WHERE date= '" . $rowdate . "'");
	$audit_text = "written to lvf_hits, date= " . $rowdate . ", reqs= " . $reqs . ", routereq= " . $routereq . ", veh_req= " . $vehreq . ", stopreq= " . $stopreq . ", history= " . $histreq . ", listreq= " . $listreq . ", eta= " . $etareq . ", errorreq= " . $errreq . ", dumpv= " . $dumreq . ", sing_req= " . $singreq . ", dest_req= " . $destreq . ", stxt_req= " . $stxtreq ;
	mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
	mysql_query("DELETE from lvf_stats_events WHERE evdate= '" . $rowdate . "'");
	mysql_query("INSERT INTO lvf_hits ( date, requests, route_req, veh_req, stop_req, history, eta, list_req, error_req, dumpv, sing_req, dest_req, stxt_req) VALUES ('" . date( "Y-m-d" ) . "', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0')");

   }
   $nowdatetime = date( "Y-m-d H:i:s" ) ;
   mysql_query("UPDATE lvf_config SET CronFinish = '" . $nowdatetime . "', tot_ent = '" . $num_entries . "', insert_rd = '" . $insert_route_day . "'");
//   $audit_text = "cron job at " . $nowdatetime . ", Total entries= " . $num_entries . ", Inserts into route day= " . $insert_route_day . ", Lines with NONE stopid= " . $none_count ;
//   mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
}

?>