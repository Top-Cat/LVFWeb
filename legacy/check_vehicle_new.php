<?php

function check_veh_update ( $vid, $creg ) {

	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;

	$insert_vehicles = 0 ;
	$insert_where_seen = 0 ;
	$newuvi = $vid ;
	$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi = '" . $vid . "'");
	if ($row = mysql_fetch_assoc($result)) {
		$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi > '19000' AND uvi < '20000' order by uvi desc limit 1  ");
		if ($row = mysql_fetch_assoc($result)) {
			$newuvi = $row['uvi'] + 1 ;
		}
	}
	$result = mysql_query("SELECT cur_reg, uvi, cdreg, nfy, operator, fnum, orig_reg, keep FROM lvf_vehicles WHERE vid= '" . $vid . "'");
	if ($row = mysql_fetch_assoc($result)) {
		$cdnreg = $row['cdreg'] ;
		$keep = $row['keep'] ;
		$uvi = $row['uvi'] ;
		$realreg = $row['cur_reg'] ;
		$origreg = $row['orig_reg'] ;
		$ooper = $row['operator'] ;
		$ofnum = $row['fnum'] ;
		if (( $cdnreg != $creg ) && ( $row['nfy'] == '0' )) {
// registration change
		   if ( $keep != 'Y' ) {
			$result = mysql_query("SELECT vid, fnum FROM lvf_vehicles WHERE cdreg = '" . $creg . "'");
			if ($row = mysql_fetch_assoc($result)) {
				$oldvid = $row['vid'] ;
				$nfnum = $row['fnum'] ;
				$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '99000' LIMIT 1 ");
	   			$row = mysql_fetch_assoc($result) ;
				$newvid = $row['vid'] - 1 ;
				$audit_text = "Registration already exists in vehicle data - VehicleId = " . $oldvid . ", new vid = " . $newvid . ", registration = " . $creg . ", old registration = " . $cdnreg . ", fleetnumer = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "' WHERE cdreg = '" . $creg . "'");
			}
			$audit_text = "Registration changed in vehicle data - VehicleId = " . $vid . ", new registration = " . $creg . ", old registration = " . $cdnreg . ", operator = " . $ooper . ", fleetnumer = " . $ofnum ;
			mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			mysql_query("UPDATE lvf_vehicles SET cdreg = '" . $creg . "', nfy = '1' WHERE vid= '" . $vid . "'");
			if ( $cdnreg == $realreg ) {
				$audit_text = "Registration changed in vehicle data - Both original registrations were the same, vid = " . $vid . ", new registriation = " . $cdnreg ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			}
		   } else {
			$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '99000' LIMIT 1 ");
	   		$row = mysql_fetch_assoc($result) ;
			$newvid = $row['vid'] - 1 ;
// not allowed to update current record so withdraw it - VID in use
			mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $uvi . "'") ;
			$audit_text = "Vehicle record withdrawn - registration = " . $cdnreg . ", new Vid = " . $newvid . ", old VehicleId = " . $vid . ", uvi = " . $uvi . ", operator = " . $ooper . ", fleetnumber = " . $ofnum ;
			mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			$result = mysql_query("SELECT uvi, vid, fnum FROM lvf_vehicles WHERE cdreg= '" . $creg . "'");
			if ($row = mysql_fetch_assoc($result)) {
				if ($row['vid'] < '90000') {
// not allowed to update current record so withdraw it - registration in use
					$newvid = $newvid - 1 ;
					$wduvi = $row['uvi']
					mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $wduvi . "'") ;
					$audit_text = "Vehicle record withdrawn - registration = " . $cdnreg . ", new Vid = " . $newvid . ", old VehicleId = " . $vid . ", uvi = " . $uvi . ", operator = " . $ooper . ", fleetnumber = " . $ofnum ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep, operator ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N', '" . $ooper . "' )");
					mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
					$audit_text = "New entry in vehicles table - Vid = " . $vid . " uvi = " . $newuvi  . " Regns = " . $creg  ;
//					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				} else {
					$audit_text = "New registration already in database (prepopuated), - VehicleId = " . $row['vid'] . ", reg = " . $creg . ", fleetnumber = " . $row['fnum'] ;
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("UPDATE lvf_vehicles SET vid= '" . $vid . "', uvi = '" . $newuvi . "', nfy ='1' WHERE cdreg= '" . $creg . "'") ;
				}
			} else {
				mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep, operator ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N', '" . $ooper . "' )");
				mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
				$audit_text = "New entry in vehicles table - Vid = " . $vid . " uvi = " . $newuvi  . " Regns = " . $creg  ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			}
		   }
		}
	} else {
// vid not currently in lvf_vehicles
		$result = mysql_query("SELECT nfy, vid, uvi, fnum, orig_reg FROM lvf_vehicles WHERE cdreg= '" . $creg . "'");
		if ($row = mysql_fetch_assoc($result)) {
// pre-populated or vehicle changing operators
			$oldvid = $row['vid'] ;
			$olduvi = $row['uvi'] ;
			$nfnum = $row['fnum'] ;
			$origreg = $row['orig_reg'] ;
			if ( $oldvid > 90000 ) {
// pre-populated
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $vid . "', uvi = '" . $newuvi . "', nfy = '1' WHERE cdreg = '" . $creg . "'") ;
				$audit_text = "Vehicle id changed in pre-entered vehicle data - registration = " . $creg . ", new Vid = " . $vid . ", new UVI = " . $newuvi . ", old VehicleId = " . $oldvid . ", fleetnumber = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			} else {
// registration changing vids
				$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '99000' order by vid LIMIT 1 ");
	   			$row = mysql_fetch_assoc($result) ;
				$newvid = $row['vid'] - 1 ;
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $olduvi . "'") ;
				$audit_text = "Vehicle id changed in vehicle data - registration = " . $creg . ", new Vid = " . $newvid . ", old VehicleId = " . $oldvid . ", old uvi = " . $olduvi . ", fleetnumber = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("DELETE FROM lvf_where_seen WHERE vid = '" . $oldvid . "'") ;
				$audit_text = "record deleted from lvf_where_seen - VehicleId = " . $oldvid ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N' )");
				mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
				$audit_text = "New entry in vehicles table - Vid = " . $vid . ", uvi = " . $newuvi . " Regns = " . $creg  ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			}
		}
		else {
// new vehicle record
			if (( $vid == '0' ) || ( $vid == '' )) {
				$audit_text = "record with null vid, second check = " . $r[0] . ", " . $r[1] . ", " . $r[2] . ", " . $r[3] . ", " . $r[4] . ", " . $r[5] . ", " . $r[6] . ", " . $r[7] . ", " . $r[8];
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			} else {
				$uvi = $vid ;
				$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE uvi = '" . $vid . "'");
				if ($row = mysql_fetch_assoc($result)) {
					$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi > '19000' AND uvi < '20000' order by uvi desc limit 1  ");
					if ($row = mysql_fetch_assoc($result)) {
						$uvi = $row['uvi'] + 1 ;
					}
				}
				mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep ) VALUES ('" . $creg . "', '" . $vid . "', '" . $uvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N' )");
				mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
				$audit_text = "New entry in vehicles table - Vid = " . $vid . ", uvi = " . $uvi . ", Regns = " . $creg  ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			}
		}
// new where seen record
		mysql_query("INSERT INTO lvf_where_seen ( vid ) VALUES ('" . $vid . "')");
		mysql_query("UPDATE lvf_config SET insert_ws = insert_ws + 1 ");
	}
}

?>