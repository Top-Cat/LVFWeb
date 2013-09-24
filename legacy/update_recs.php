#!/usr/local/bin/php
<?php

include "connect.php";
include "check_vehicle.php";


	 
// update hits record for the last day

	$active = 0 ;
	$error = 0 ;
	$wdrawn = 0 ;
	$preset = 0 ;
	$recs = 0 ;
	
	$result = mysql_query("SELECT vid, uvi, cdreg, orig_reg FROM lvf_vehicles ORDER BY vid");
	while ( $row = mysql_fetch_assoc($result)) {
		$vid= $row['vid'] ;
		$uvi= $row['uvi'] ;
		$cdreg= $row['cdreg'] ;
		$orig_reg= $row['orig_reg'] ;
		$recs += 1 ;

		if ( $vid > '98000' ) {
//		withdrawn vehicle, check no cdreg
			$wdrawn += 1 ;
			if ( $cdreg != '' ) {
				$error += 1 ;
			}		
		} else if ( $vid > '90000' ) {
//		preentered vehicle, check cdreg and orig_reg valid
			$preset += 1 ;
			if (( $cdreg == '' ) || ($cdreg != $orig_reg )) {
				$error += 1 ;
			}
		} else {
			$active += 1 ;
			$resulta = mysql_query("SELECT last_seen FROM lvf_where_seen WHERE vid = '" . $vid . "'");
			$rowa = mysql_fetch_assoc($resulta) ;
			$ws_last_seen = $rowa['last_seen'] ;
			$ws_date = substr( $ws_last_seen, 0, 10 ) ;
			$ws_time = substr( $ws_last_seen, 10 ) ;

			$resultb = mysql_query("SELECT date, last_seen, registration, route FROM lvf_route_day WHERE vehicle_id = '" . $uvi . "' ORDER BY date desc, last_seen desc LIMIT 1");
			$rowb = mysql_fetch_assoc($resultb) ;
			$rd_date = $rowb['date'] ;
			$rd_last_seen = $rowb['last_seen'] ;
			if ( $ws_date < $rd_date ) {
		
				if ( $error < 10 ) {
					$audit_text = "for vehicle vid = " . $vid . ", where_seen date = " . $ws_date . ", where_seen time = " . $ws_time . ", route_day date = " . $rd_date . ", last_seen = " . $rd_last_seen ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
				}
				$error += 1 ;
			}
		
		}

	}

$audit_text = "records read from lvf_vehicles = " . $recs . ", active = " . $active . ", withdrawn = " . $wdrawn . ", pre entered = " . $preset . ", errors found = " . $error ;
mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");


?>